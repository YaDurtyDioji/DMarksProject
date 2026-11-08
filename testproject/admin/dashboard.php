<?php
// admin/dashboard.php
session_start();
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/mail-helper.php';
require_once __DIR__ . '/../backend/gcal-helper.php';

// Authorization Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['admin_user'];

$flashMessage = '';
$flashType = 'success';

if (isset($_SESSION['flash_msg'])) {
    $flashMessage = $_SESSION['flash_msg'];
    $flashType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// Global vs Admin WA Number Logic
$globalWaNumber = getSetting($pdo, 'whatsapp_business_number', '');
$stmtWa = $pdo->prepare("SELECT phone_number FROM admins WHERE username = ?");
$stmtWa->execute([$currentUser]);
$adminWa = $stmtWa->fetchColumn();

// The active sender number for links
$senderWaNumber = !empty($adminWa) ? $adminWa : $globalWaNumber;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_booking') {
        $cName = trim($_POST['client_name'] ?? '');
        $cEmail = trim($_POST['client_email'] ?? '');
        $cPhone = trim($_POST['client_phone'] ?? '');
        $service = trim($_POST['service'] ?? '');
        $bDate = trim($_POST['booking_date'] ?? '');
        $bTime = trim($_POST['booking_time'] ?? '');
        
        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $additional = floatval($_POST['additional_costs'] ?? 0);
        $discType = $_POST['discount_type'] ?? 'fixed';
        $discVal = floatval($_POST['discount_value'] ?? 0);
        $promoCode = strtoupper(trim($_POST['redeemable_code'] ?? ''));
        $isFirstTime = isset($_POST['is_first_time']) ? 1 : 0;

        // Verify & Apply Promo Code if specified
        if (!empty($promoCode)) {
            $stmtP = $pdo->prepare("SELECT * FROM promotions WHERE code = ? AND status = 'active'");
            $stmtP->execute([$promoCode]);
            if ($promoData = $stmtP->fetch(PDO::FETCH_ASSOC)) {
                $discType = $promoData['discount_type'];
                $discVal = floatval($promoData['discount_value']);
                // Update usage counter
                $pdo->prepare("UPDATE promotions SET times_used = times_used + 1 WHERE id = ?")->execute([$promoData['id']]);
            }
        }

        // Calculate Total
        $base = $subtotal + $additional;
        if ($discType === 'percent') {
            $total = $base - ($base * ($discVal / 100));
        } else {
            $total = $base - $discVal;
        }
        if ($total < 0) $total = 0;

        $indemnityStatus = 0;
        $checkClient = $pdo->prepare("SELECT indemnity_completed FROM clients WHERE email = ?");
        $checkClient->execute([$cEmail]);
        if ($row = $checkClient->fetch(PDO::FETCH_ASSOC)) {
            $indemnityStatus = intval($row['indemnity_completed']);
        } else {
            $addClient = $pdo->prepare("INSERT OR IGNORE INTO clients (name, email, phone, indemnity_completed) VALUES (?, ?, ?, 0)");
            $addClient->execute([$cName, $cEmail, $cPhone]);
        }

        $stmt = $pdo->prepare("INSERT INTO bookings (client_name, client_email, client_phone, service, subtotal, additional_costs, discount_type, discount_value, redeemable_code, total_amount, is_first_time, booking_date, booking_time, status, payment_status, indemnity_completed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'outstanding', ?)");
        $stmt->execute([$cName, $cEmail, $cPhone, $service, $subtotal, $additional, $discType, $discVal, $promoCode, $total, $isFirstTime, $bDate, $bTime, $indemnityStatus]);
        
        $newBookingId = $pdo->lastInsertId();

        // Dispatch Admin Notifications based on Per-Function matrix
        triggerNotification($pdo, 'new_booking', [
            'client_name' => $cName,
            'client_email' => $cEmail,
            'service' => $service,
            'booking_date' => $bDate,
            'booking_time' => $bTime
        ]);

        // Auto Sync to Gcal
        syncBookingToGcal($pdo, $newBookingId);
        logAudit($pdo, $currentUser, "Created Booking #{$newBookingId} for {$cName}");

        $_SESSION['flash_msg'] = "New appointment created successfully for {$cName}.";
        
        // Auto dispatch if first time
        if ($isFirstTime) {
            $indemnityLink = getSetting($pdo, 'indemnity_form_link', 'https://barewithtea.com/indemnity');
            
            triggerNotification($pdo, 'first_time', [
                'client_name' => $cName,
                'client_email' => $cEmail,
                'service' => $service,
                'booking_date' => $bDate,
                'booking_time' => $bTime
            ]);

            sendIndemnityEmail($cEmail, $cName, $indemnityLink);
            
            $waMsg = urlencode("Welcome to barewithtea, {$cName}! We have confirmed your {$service} appointment for {$bDate} at {$bTime}. As a first-time client, please complete our digital indemnity form prior to your visit: {$indemnityLink}");
            
            if (!empty($senderWaNumber)) {
                $waUrl = "https://wa.me/" . preg_replace('/[^0-9]/', '', $senderWaNumber) . "?text={$waMsg}";
            } else {
                $cleanPhone = preg_replace('/[^0-9]/', '', $cPhone);
                if (substr($cleanPhone, 0, 1) === '0') { $cleanPhone = '27' . substr($cleanPhone, 1); }
                $waUrl = "https://wa.me/{$cleanPhone}?text={$waMsg}";
            }

            $_SESSION['flash_msg'] .= "<br>First-time client detected. Email sent. <a href='{$waUrl}' target='_blank' class='btn btn-sm btn-dark mt-2'>Send Welcome WhatsApp</a>";
        }

    } elseif ($action === 'update_status') {
        $id = intval($_POST['booking_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'pending';

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
            logAudit($pdo, $currentUser, "Updated Status to {$newStatus} for Booking #{$id}");

            // Update Gcal
            syncBookingToGcal($pdo, $id);

            if ($newStatus === 'completed') {
                $getB = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
                $getB->execute([$id]);
                $bookingData = $getB->fetch(PDO::FETCH_ASSOC);

                if ($bookingData) {
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $reviewUrl = "{$protocol}://{$host}/client/review.php?booking_id={$id}";

                    triggerNotification($pdo, 'completed', $bookingData);
                    sendCompletionInvoiceEmail($bookingData, $reviewUrl);

                    $waMsg = urlencode("Hello {$bookingData['client_name']}, thank you for visiting barewithtea! Your total is R" . number_format($bookingData['total_amount'], 2) . ". Please rate your experience here: {$reviewUrl}");
                    
                    if (!empty($senderWaNumber)) {
                        $waUrl = "https://wa.me/" . preg_replace('/[^0-9]/', '', $senderWaNumber) . "?text={$waMsg}";
                    } else {
                        $cleanPhone = preg_replace('/[^0-9]/', '', $bookingData['client_phone']);
                        if (substr($cleanPhone, 0, 1) === '0') { $cleanPhone = '27' . substr($cleanPhone, 1); }
                        $waUrl = "https://wa.me/{$cleanPhone}?text={$waMsg}";
                    }

                    $_SESSION['flash_msg'] = "Booking #{$id} COMPLETED. Invoice emailed. <a href='{$waUrl}' target='_blank' class='btn btn-sm btn-outline-dark ms-2'>Send WhatsApp</a>";
                }
            } else {
                $_SESSION['flash_msg'] = "Booking #{$id} status updated to " . strtoupper($newStatus) . ".";
            }
        }
    } elseif ($action === 'edit_booking') {
        $id = intval($_POST['booking_id'] ?? 0);
        $cName = trim($_POST['client_name'] ?? '');
        $cEmail = trim($_POST['client_email'] ?? '');
        $cPhone = trim($_POST['client_phone'] ?? '');
        $service = trim($_POST['service'] ?? '');
        $bDate = trim($_POST['booking_date'] ?? '');
        $bTime = trim($_POST['booking_time'] ?? '');
        
        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $additional = floatval($_POST['additional_costs'] ?? 0);
        $discType = $_POST['discount_type'] ?? 'fixed';
        $discVal = floatval($_POST['discount_value'] ?? 0);
        $promoCode = trim($_POST['redeemable_code'] ?? '');

        // Calculate Total
        $base = $subtotal + $additional;
        if ($discType === 'percent') {
            $total = $base - ($base * ($discVal / 100));
        } else {
            $total = $base - $discVal;
        }
        if ($total < 0) $total = 0;

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE bookings SET client_name=?, client_email=?, client_phone=?, service=?, subtotal=?, additional_costs=?, discount_type=?, discount_value=?, redeemable_code=?, total_amount=?, booking_date=?, booking_time=? WHERE id=?");
            $stmt->execute([$cName, $cEmail, $cPhone, $service, $subtotal, $additional, $discType, $discVal, $promoCode, $total, $bDate, $bTime, $id]);
            
            syncBookingToGcal($pdo, $id);
            logAudit($pdo, $currentUser, "Edited Booking #{$id}");

            $_SESSION['flash_msg'] = "Booking #{$id} updated successfully.";
        }
    } elseif ($action === 'delete_booking') {
        $id = intval($_POST['booking_id'] ?? 0);
        if ($id > 0) {
            $stmtG = $pdo->prepare("SELECT gcal_event_id FROM bookings WHERE id = ?");
            $stmtG->execute([$id]);
            $gcalId = $stmtG->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$id]);

            if ($gcalId) {
                deleteBookingFromGcal($pdo, $gcalId);
            }

            logAudit($pdo, $currentUser, "Deleted Booking #{$id}");
            $_SESSION['flash_msg'] = "Booking #{$id} deleted successfully.";
        }
    } elseif ($action === 'manual_sync_all') {
        $stmt = $pdo->query("SELECT id FROM bookings WHERE status NOT IN ('cancelled', 'mistake')");
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;
        foreach ($all as $b) {
            if(syncBookingToGcal($pdo, $b['id'])) {
                $count++;
            }
        }
        logAudit($pdo, $currentUser, "Triggered Manual GCal Sync");
        $_SESSION['flash_msg'] = "Google Calendar Sync completed for {$count} records.";
    }

    header('Location: dashboard.php');
    exit;
}

// Fetch Services & Combos/Promotions for Dropdowns
$pricesList = $pdo->query("SELECT * FROM prices ORDER BY category ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$activeCombos = $pdo->query("SELECT * FROM promotions WHERE type = 'special_combo' AND status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
$activePromoCodes = $pdo->query("SELECT * FROM promotions WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

// JSON maps for frontend script lookup
$promoMap = [];
foreach ($activePromoCodes as $pr) {
    $promoMap[strtoupper($pr['code'])] = [
        'discount_type' => $pr['discount_type'],
        'discount_value' => floatval($pr['discount_value']),
        'promo_price' => floatval($pr['promo_price'])
    ];
}

// Fetch Upcoming & Active Appointments
$upcomingStmt = $pdo->query("SELECT * FROM bookings WHERE status NOT IN ('completed', 'cancelled', 'no_show', 'mistake') ORDER BY booking_date ASC, booking_time ASC");
$upcomingBookings = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

$totalActive = count($upcomingBookings);
$pendingIndemnityCount = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status NOT IN ('completed', 'cancelled', 'mistake') AND indemnity_completed = 0")->fetchColumn();
$completedToday = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed' AND DATE(created_at) = DATE('now')")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - barewithtea</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <style>
        body { background-color: #faf9f6; font-family: system-ui, -apple-system, sans-serif; color: #212529; }
        .card-custom { border-radius: 0px; border: 1px solid #d7ccab; background: #ffffff; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .card-stats { border: 1px solid #d7ccab; border-radius: 0; background: #e4dcc6; }
        #calendar-wrapper { max-width: 800px; margin: 0 auto; }
        #calendar { background: #ffffff; border: 1px solid #d7ccab; padding: 10px; font-size: 0.9rem; }
        .fc-toolbar-title { font-size: 1.1rem !important; font-weight: bold; color: #212529; }
        .fc-button-primary { background-color: #d7ccab !important; border-color: #cbbd96 !important; color: #212529 !important; text-transform: uppercase; font-size: 0.8rem !important; }
        .fc-button-primary:hover { background-color: #cbbd96 !important; }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<div class="container-fluid py-4 px-4">

    <?php if (!empty($flashMessage)): ?>
        <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show border-1 shadow-sm mb-4" role="alert" style="border-color: #d7ccab; background-color: #e4dcc6;">
            <?= $flashMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
        <div class="col-lg-7 col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-uppercase tracking-wide">Schedule Overview</h5>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="manual_sync_all">
                    <button type="submit" class="btn btn-sm btn-outline-dark"><i class="fa-solid fa-rotate me-1"></i> Force Sync GCal</button>
                </form>
            </div>
            <div id="calendar-wrapper">
                <div id="calendar"></div>
            </div>
        </div>

        <div class="col-lg-5 col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-uppercase tracking-wide">Summary</h5>
                <button class="btn btn-sm btn-dark text-uppercase" data-bs-toggle="modal" data-bs-target="#createBookingModal"><i class="fa-solid fa-plus me-1"></i> New Booking</button>
            </div>
            
            <div class="row g-3">
                <div class="col-12">
                    <div class="card card-stats p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="small fw-bold text-uppercase" style="letter-spacing: 1px;">Active Appointments</span>
                                <h1 class="fw-bold mb-0 display-4"><?= $totalActive ?></h1>
                            </div>
                            <i class="fa-solid fa-calendar-check fa-3x" style="color: #b5a67c;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card card-stats p-3" style="background-color: #fff; border-color: #d7ccab;">
                        <span class="small fw-bold text-uppercase text-muted">Pending Forms</span>
                        <h3 class="fw-bold mb-0 text-danger"><?= $pendingIndemnityCount ?></h3>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card card-stats p-3" style="background-color: #fff; border-color: #d7ccab;">
                        <span class="small fw-bold text-uppercase text-muted">Done Today</span>
                        <h3 class="fw-bold mb-0 text-success"><?= $completedToday ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Appointments Queue -->
    <div class="d-flex justify-content-between align-items-center mb-3 mt-4" id="upcoming">
        <h5 class="fw-bold mb-0 text-uppercase tracking-wide">Upcoming Appointments Queue</h5>
    </div>

    <div class="card card-custom shadow-sm mb-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background-color: #e4dcc6;">
                    <tr>
                        <th class="text-uppercase small">ID</th>
                        <th class="text-uppercase small">Client Info</th>
                        <th class="text-uppercase small">Service</th>
                        <th class="text-uppercase small">Date & Time</th>
                        <th class="text-uppercase small">Pricing</th>
                        <th class="text-uppercase small">Status</th>
                        <th class="text-uppercase small">Form</th>
                        <th class="text-end text-uppercase small">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingBookings)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">Queue is empty. No active appointments.</td></tr>
                    <?php else: ?>
                        <?php foreach ($upcomingBookings as $b): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $b['id'] ?></td>
                                <td>
                                    <span class="fw-bold"><?= htmlspecialchars($b['client_name']) ?></span>
                                    <?php if($b['is_first_time']): ?> <span class="badge bg-primary rounded-pill x-small">1st</span> <?php endif; ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($b['client_email']) ?> | <?= htmlspecialchars($b['client_phone']) ?></small>
                                </td>
                                <td><span class="badge bg-dark fw-normal rounded-0"><?= htmlspecialchars($b['service']) ?></span></td>
                                <td>
                                    <?= htmlspecialchars($b['booking_date']) ?><br>
                                    <small class="text-muted fw-bold"><?= htmlspecialchars($b['booking_time']) ?></small>
                                </td>
                                <td>
                                    <strong>R <?= number_format((float)$b['total_amount'], 2) ?></strong>
                                    <?php if ($b['discount_value'] > 0 || $b['additional_costs'] > 0): ?>
                                        <br><small class="text-muted" style="font-size: 0.7rem;">(D: <?= $b['discount_value'] ?> | A: <?= $b['additional_costs'] ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="dashboard.php" class="d-inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                        <select name="status" class="form-select form-select-sm fw-bold border-dark rounded-0" onchange="this.form.submit()">
                                            <option value="pending" <?= $b['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="confirmed" <?= $b['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                            <option value="completed" <?= $b['status'] === 'completed' ? 'selected' : '' ?>>Done (Send Invoice)</option>
                                            <option value="no_show" <?= $b['status'] === 'no_show' ? 'selected' : '' ?>>No Show</option>
                                            <option value="cancelled" <?= $b['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($b['indemnity_completed'] == 1): ?>
                                        <i class="fa-solid fa-check text-success"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-xmark text-danger"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-dark rounded-0" data-bs-toggle="modal" data-bs-target="#editBookingModal<?= $b['id'] ?>"><i class="fa-solid fa-pen"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete booking?');">
                                            <input type="hidden" name="action" value="delete_booking">
                                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger rounded-0"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= MODALS CONTAINER ================= -->

<!-- Create New Booking Modal -->
<div class="modal fade" id="createBookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-0">
            <form method="POST" action="dashboard.php" id="createBookingForm">
                <input type="hidden" name="action" value="create_booking">
                <div class="modal-header border-bottom-0" style="background-color: #e4dcc6;">
                    <h5 class="modal-title fw-bold text-uppercase">New Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4"><label class="form-label fw-bold">Client Name</label><input type="text" name="client_name" class="form-control rounded-0" required></div>
                        <div class="col-md-4"><label class="form-label fw-bold">Email</label><input type="email" name="client_email" class="form-control rounded-0" required></div>
                        <div class="col-md-4"><label class="form-label fw-bold">Phone</label><input type="text" name="client_phone" class="form-control rounded-0" required></div>
                    </div>

                    <div class="row g-3 mb-3 border p-2 m-0 bg-light">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isFirstTime" name="is_first_time" value="1">
                                <label class="form-check-label fw-bold text-primary" for="isFirstTime">
                                    <i class="fa-solid fa-star me-1"></i> First-Time Client (Auto-sends Welcome & Indemnity Link on save)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Service / Treatment / Special Combo</label>
                            <select name="service" id="serviceSelect" class="form-select rounded-0" required>
                                <option value="" data-price="0">-- Select Treatment --</option>
                                <optgroup label="Standard Menu Treatments">
                                    <?php foreach ($pricesList as $p): ?>
                                        <option value="<?= htmlspecialchars($p['name']) ?>" data-price="<?= $p['price'] ?>"><?= htmlspecialchars($p['category']) ?> - <?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php if (!empty($activeCombos)): ?>
                                    <optgroup label="Special Promotional Combos">
                                        <?php foreach ($activeCombos as $cb): ?>
                                            <option value="<?= htmlspecialchars($cb['title']) ?>" data-price="<?= $cb['promo_price'] ?>">🔥 Special Combo: <?= htmlspecialchars($cb['title']) ?> (R<?= number_format($cb['promo_price'],2) ?>)</option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label fw-bold">Date</label><input type="date" name="booking_date" class="form-control rounded-0" required value="<?= date('Y-m-d') ?>"></div>
                        <div class="col-md-3"><label class="form-label fw-bold">Time</label><input type="time" name="booking_time" class="form-control rounded-0" required value="10:00"></div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Subtotal (ZAR)</label>
                            <input type="number" step="0.01" id="subtotalInput" name="subtotal" class="form-control rounded-0" required readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Additional Costs</label>
                            <input type="number" step="0.01" id="additionalInput" name="additional_costs" class="form-control rounded-0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Discount Type</label>
                            <select id="discType" name="discount_type" class="form-select rounded-0">
                                <option value="fixed">Fixed (R)</option>
                                <option value="percent">Percent (%)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Discount Value</label>
                            <input type="number" step="0.01" id="discVal" name="discount_value" class="form-control rounded-0" value="0">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Redeemable Code / Campaign Voucher</label>
                            <div class="input-group">
                                <input type="text" id="promoCodeInput" name="redeemable_code" class="form-control rounded-0 text-uppercase" placeholder="e.g. SPRING2026">
                                <button type="button" class="btn btn-outline-dark rounded-0" id="applyPromoBtn">Apply Code</button>
                            </div>
                            <small id="promoMsg" class="form-text"></small>
                        </div>
                        <div class="col-md-6 d-flex align-items-end justify-content-end">
                            <h4 class="fw-bold mb-0">Est. Total: <span id="calcTotal" class="text-success">R 0.00</span></h4>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="submit" class="btn btn-dark rounded-0 px-4 text-uppercase fw-bold w-100">Save Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($upcomingBookings as $b): ?>
    <!-- Edit Booking Modal -->
    <div class="modal fade" id="editBookingModal<?= $b['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-0">
                <form method="POST" action="dashboard.php">
                    <input type="hidden" name="action" value="edit_booking">
                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                    <div class="modal-header border-bottom-0" style="background-color: #e4dcc6;">
                        <h5 class="modal-title fw-bold text-uppercase">Edit Appointment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><label class="form-label fw-bold">Name</label><input type="text" name="client_name" class="form-control rounded-0" value="<?= htmlspecialchars($b['client_name']) ?>"></div>
                            <div class="col-md-4"><label class="form-label fw-bold">Email</label><input type="email" name="client_email" class="form-control rounded-0" value="<?= htmlspecialchars($b['client_email']) ?>"></div>
                            <div class="col-md-4"><label class="form-label fw-bold">Phone</label><input type="text" name="client_phone" class="form-control rounded-0" value="<?= htmlspecialchars($b['client_phone']) ?>"></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label class="form-label fw-bold">Service</label><input type="text" name="service" class="form-control rounded-0" value="<?= htmlspecialchars($b['service']) ?>"></div>
                            <div class="col-md-3"><label class="form-label fw-bold">Date</label><input type="date" name="booking_date" class="form-control rounded-0" value="<?= htmlspecialchars($b['booking_date']) ?>"></div>
                            <div class="col-md-3"><label class="form-label fw-bold">Time</label><input type="time" name="booking_time" class="form-control rounded-0" value="<?= htmlspecialchars($b['booking_time']) ?>"></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><label class="form-label fw-bold">Subtotal</label><input type="number" step="0.01" name="subtotal" class="form-control rounded-0" value="<?= htmlspecialchars($b['subtotal']) ?>"></div>
                            <div class="col-md-3"><label class="form-label fw-bold">Additional</label><input type="number" step="0.01" name="additional_costs" class="form-control rounded-0" value="<?= htmlspecialchars($b['additional_costs']) ?>"></div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Disc Type</label>
                                <select name="discount_type" class="form-select rounded-0">
                                    <option value="fixed" <?= $b['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed (R)</option>
                                    <option value="percent" <?= $b['discount_type'] === 'percent' ? 'selected' : '' ?>>Percent (%)</option>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label fw-bold">Disc Value</label><input type="number" step="0.01" name="discount_value" class="form-control rounded-0" value="<?= htmlspecialchars($b['discount_value']) ?>"></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label fw-bold">Redeemable Code</label><input type="text" name="redeemable_code" class="form-control rounded-0" value="<?= htmlspecialchars($b['redeemable_code']) ?>"></div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="submit" class="btn btn-dark rounded-0 px-4 text-uppercase w-100 fw-bold">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Active Promo Map from PHP
    const promoMap = <?= json_encode($promoMap) ?>;

    const serviceSelect = document.getElementById('serviceSelect');
    const subtotalInput = document.getElementById('subtotalInput');
    const additionalInput = document.getElementById('additionalInput');
    const discType = document.getElementById('discType');
    const discVal = document.getElementById('discVal');
    const calcTotal = document.getElementById('calcTotal');
    const promoCodeInput = document.getElementById('promoCodeInput');
    const applyPromoBtn = document.getElementById('applyPromoBtn');
    const promoMsg = document.getElementById('promoMsg');

    function calculateTotal() {
        let sub = parseFloat(subtotalInput.value) || 0;
        let add = parseFloat(additionalInput.value) || 0;
        let base = sub + add;
        
        let dType = discType.value;
        let dVal = parseFloat(discVal.value) || 0;
        let total = base;

        if (dType === 'percent') {
            total = base - (base * (dVal / 100));
        } else {
            total = base - dVal;
        }
        if (total < 0) total = 0;
        
        calcTotal.innerText = 'R ' + total.toFixed(2);
    }

    serviceSelect.addEventListener('change', function() {
        let selectedOption = this.options[this.selectedIndex];
        let price = selectedOption.getAttribute('data-price');
        if (price) {
            subtotalInput.value = price;
        } else {
            subtotalInput.value = 0;
        }
        calculateTotal();
    });

    applyPromoBtn.addEventListener('click', function() {
        let code = promoCodeInput.value.trim().toUpperCase();
        if (promoMap[code]) {
            discType.value = promoMap[code].discount_type;
            discVal.value = promoMap[code].discount_value;
            promoMsg.className = "form-text text-success fw-bold";
            promoMsg.innerText = "✓ Valid Promo Code Applied (" + promoMap[code].discount_value + (promoMap[code].discount_type === 'percent' ? '%' : ' ZAR') + " Off)";
            calculateTotal();
        } else if (code !== "") {
            promoMsg.className = "form-text text-danger";
            promoMsg.innerText = "✕ Invalid or Expired Promo Code";
        }
    });

    [additionalInput, discType, discVal].forEach(el => el.addEventListener('input', calculateTotal));

    // Calendar Rendering
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek', 
        slotMinTime: '08:00:00',
        slotMaxTime: '18:00:00',
        allDaySlot: false,
        height: 500, 
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        themeSystem: 'bootstrap5',
        events: '../backend/calendar-events.php',
        eventClick: function(info) {
            var props = info.event.extendedProps;
            alert('Appointment ID #' + info.event.id + '\nClient: ' + props.client + '\nService: ' + props.service);
        }
    });
    calendar.render();
});
</script>
</body>
</html>
