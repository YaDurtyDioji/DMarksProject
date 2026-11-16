<?php
// admin/history.php
session_start();
require_once __DIR__ . '/../backend/db.php';

// Authorization Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$flashMessage = '';
$flashType = 'success';

if (isset($_SESSION['flash_msg'])) {
    $flashMessage = $_SESSION['flash_msg'];
    $flashType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// Handle Status Updates or Notes using PRG
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $id = intval($_POST['booking_id'] ?? 0);
        $status = $_POST['status'] ?? 'completed';

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            $_SESSION['flash_msg'] = "Booking #{$id} status updated to " . ucfirst($status) . ".";
        }
    } elseif ($action === 'delete_history') {
        $id = intval($_POST['booking_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['flash_msg'] = "Booking history record removed.";
        }
    }

    header('Location: history.php');
    exit;
}

// Filter Status
$statusFilter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT b.*, r.session_rating, r.overall_rating, r.review_comments 
        FROM bookings b 
        LEFT JOIN reviews r ON (b.client_email = r.client_email AND b.service = r.service)
        WHERE 1=1 ";

$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND b.status = ? ";
    $params[] = $statusFilter;
} else {
    // Default show past/completed/cancelled/no_show/mistake
    $sql .= " AND b.status IN ('completed', 'cancelled', 'no_show', 'mistake') ";
}

if (!empty($search)) {
    $sql .= " AND (b.client_name LIKE ? OR b.client_email LIKE ? OR b.service LIKE ?) ";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY b.booking_date DESC, b.booking_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$historyBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - barewithtea Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f8; font-family: system-ui, -apple-system, sans-serif; color: #212529; }
        .card-custom { border-radius: 8px; border: 1px solid #e2e8f0; background: #ffffff; }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<div class="container-fluid py-4 px-4">

    <?php if (!empty($flashMessage)): ?>
        <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <?= $flashMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left text-dark me-2"></i> Historical Booking Archive</h3>
            <p class="text-muted small mb-0">Full record of completed appointments, no-shows, cancellations, mistake bookings, and client review feedback.</p>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="card card-custom p-3 mb-4 shadow-sm">
        <form method="GET" action="history.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Historical Records</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed Appointments</option>
                    <option value="no_show" <?= $statusFilter === 'no_show' ? 'selected' : '' ?>>No-Shows</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancellations</option>
                    <option value="mistake" <?= $statusFilter === 'mistake' ? 'selected' : '' ?>>Mistake Bookings</option>
                </select>
            </div>
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search by client name, email, or service..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-secondary w-100">Filter</button>
                <?php if ($statusFilter !== 'all' || !empty($search)): ?>
                    <a href="history.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- History Table -->
    <div class="card card-custom shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#ID</th>
                        <th>Client</th>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th>Subtotal / Total</th>
                        <th>Status</th>
                        <th>Indemnity</th>
                        <th>Rating / Review</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historyBookings)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No historical records found for this filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historyBookings as $h): ?>
                            <tr>
                                <td class="fw-bold">#<?= $h['id'] ?></td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($h['client_name']) ?></span><br>
                                    <small class="text-muted"><?= htmlspecialchars($h['client_email']) ?> | <?= htmlspecialchars($h['client_phone']) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($h['service']) ?></span></td>
                                <td>
                                    <i class="fa-regular fa-calendar me-1"></i><?= htmlspecialchars($h['booking_date']) ?><br>
                                    <small class="text-muted"><i class="fa-regular fa-clock me-1"></i><?= htmlspecialchars($h['booking_time']) ?></small>
                                </td>
                                <td>
                                    <strong>R <?= number_format((float)($h['total_amount'] ?: $h['subtotal']), 2) ?></strong>
                                    <?php if ($h['discount_value'] > 0): ?>
                                        <br><small class="text-success"><i class="fa-solid fa-tag me-1"></i>Disc: <?= $h['discount_type'] === 'percent' ? $h['discount_value'].'%' : 'R'.$h['discount_value'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($h['status'] === 'completed'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Completed</span>
                                    <?php elseif ($h['status'] === 'no_show'): ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-user-xmark me-1"></i> No Show</span>
                                    <?php elseif ($h['status'] === 'cancelled'): ?>
                                        <span class="badge bg-secondary"><i class="fa-solid fa-ban me-1"></i> Cancelled</span>
                                    <?php elseif ($h['status'] === 'mistake'): ?>
                                        <span class="badge bg-dark"><i class="fa-solid fa-bug me-1"></i> Mistake</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark"><?= ucfirst($h['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($h['indemnity_completed'] == 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-check me-1"></i> Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-dark border border-warning"><i class="fa-solid fa-clock me-1"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($h['overall_rating']): ?>
                                        <div class="text-warning small">
                                            <?php for ($i=1; $i<=5; $i++): ?>
                                                <i class="fa-<?= $i <= $h['overall_rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <?php if ($h['review_comments']): ?>
                                            <small class="text-muted d-block text-truncate" style="max-width: 150px;"><?= htmlspecialchars($h['review_comments']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <small class="text-muted">No review yet</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#changeStatusModal<?= $h['id'] ?>" title="Change Status"><i class="fa-solid fa-arrows-rotate"></i></button>
                                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteHistoryModal<?= $h['id'] ?>" title="Delete Record"><i class="fa-solid fa-trash"></i></button>
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

<!-- ================= MODALS CONTAINER (Outside tables) ================= -->
<?php foreach ($historyBookings as $h): ?>

    <!-- Change Status Modal -->
    <div class="modal fade" id="changeStatusModal<?= $h['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="history.php">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="booking_id" value="<?= $h['id'] ?>">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-arrows-rotate me-2"></i> Update Booking #<?= $h['id'] ?> Status</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Client: <strong><?= htmlspecialchars($h['client_name']) ?></strong> (<?= htmlspecialchars($h['service']) ?>)</p>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select New Status</label>
                            <select name="status" class="form-select">
                                <option value="completed" <?= $h['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="no_show" <?= $h['status'] === 'no_show' ? 'selected' : '' ?>>No-Show</option>
                                <option value="cancelled" <?= $h['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="mistake" <?= $h['status'] === 'mistake' ? 'selected' : '' ?>>Mistake Booking</option>
                                <option value="confirmed" <?= $h['status'] === 'confirmed' ? 'selected' : '' ?>>Move back to Active/Confirmed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark fw-bold">Save Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete History Modal -->
    <div class="modal fade" id="deleteHistoryModal<?= $h['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="history.php">
                    <input type="hidden" name="action" value="delete_history">
                    <input type="hidden" name="booking_id" value="<?= $h['id'] ?>">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-trash me-2"></i> Confirm Record Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p class="mb-0">Are you sure you want to permanently delete history record #<strong><?= $h['id'] ?></strong> for <?= htmlspecialchars($h['client_name']) ?>?</p>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger fw-bold">Delete Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
