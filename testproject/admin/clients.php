<?php
// admin/clients.php
session_start();
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/mail-helper.php';

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

// Handle POST actions using PRG (Post-Redirect-Get)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_client') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $indemnity = intval($_POST['indemnity_completed'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($name) && !empty($email)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO clients (name, email, phone, indemnity_completed, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $indemnity, $notes]);
                $_SESSION['flash_msg'] = "Client '{$name}' added successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = "Error adding client: " . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    } elseif ($action === 'edit_client') {
        $id = intval($_POST['client_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $indemnity = intval($_POST['indemnity_completed'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($id > 0 && !empty($name)) {
            $stmt = $pdo->prepare("UPDATE clients SET name = ?, email = ?, phone = ?, indemnity_completed = ?, notes = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $indemnity, $notes, $id]);
            
            // Also update indemnity_completed in bookings table for matching email
            $stmt2 = $pdo->prepare("UPDATE bookings SET indemnity_completed = ? WHERE client_email = ?");
            $stmt2->execute([$indemnity, $email]);

            $_SESSION['flash_msg'] = "Client details updated successfully.";
        }
    } elseif ($action === 'toggle_indemnity') {
        $id = intval($_POST['client_id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT email FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $email = $stmt->fetchColumn();

        $stmt = $pdo->prepare("UPDATE clients SET indemnity_completed = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        if ($email) {
            $stmt2 = $pdo->prepare("UPDATE bookings SET indemnity_completed = ? WHERE client_email = ?");
            $stmt2->execute([$status, $email]);
        }

        $_SESSION['flash_msg'] = "Indemnity status updated.";
    } elseif ($action === 'delete_client') {
        $id = intval($_POST['client_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['flash_msg'] = "Client record deleted.";
        }
    } elseif ($action === 'import_excel') {
        // Handle CSV / Excel file upload for existing clients
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['excel_file']['tmp_name'];
            $handle = fopen($tmpPath, 'r');
            $count = 0;
            if ($handle !== false) {
                // Read header row if exists
                $header = fgetcsv($handle, 1000, ",");
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    if (count($data) >= 2) {
                        $cName = trim($data[0] ?? '');
                        $cEmail = trim($data[1] ?? '');
                        $cPhone = trim($data[2] ?? '');
                        $cNotes = trim($data[3] ?? 'Imported Client');
                        // Existing clients imported via Excel are marked indemnity_completed = 1
                        if (!empty($cName) && !empty($cEmail)) {
                            $stmt = $pdo->prepare("INSERT OR REPLACE INTO clients (name, email, phone, indemnity_completed, notes) VALUES (?, ?, ?, 1, ?)");
                            $stmt->execute([$cName, $cEmail, $cPhone, $cNotes]);
                            
                            // Mark bookings for this client as indemnity completed too
                            $stmt2 = $pdo->prepare("UPDATE bookings SET indemnity_completed = 1 WHERE client_email = ?");
                            $stmt2->execute([$cEmail]);
                            
                            $count++;
                        }
                    }
                }
                fclose($handle);
                $_SESSION['flash_msg'] = "Successfully imported {$count} existing clients with Indemnity set to Completed.";
            } else {
                $_SESSION['flash_msg'] = "Could not parse uploaded file.";
                $_SESSION['flash_type'] = 'danger';
            }
        }
    } elseif ($action === 'send_indemnity_dispatch') {
        $email = trim($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $indemnityUrl = "{$protocol}://{$host}/index.html#indemnity";

        // Dispatch email
        sendIndemnityEmail($email, $name, $indemnityUrl);

        // Prepare WhatsApp message
        $waMsg = urlencode("Hello {$name}, please complete your digital indemnity form for barewithtea prior to your appointment: {$indemnityUrl}");
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($cleanPhone, 0, 1) === '0') {
            $cleanPhone = '27' . substr($cleanPhone, 1);
        }
        $waUrl = "https://wa.me/{$cleanPhone}?text={$waMsg}";

        $_SESSION['flash_msg'] = "Indemnity email dispatched to {$email}. <a href='{$waUrl}' target='_blank' class='btn btn-sm btn-success ms-2'><i class='fa-brands fa-whatsapp me-1'></i> Open WhatsApp Dispatch</a>";
    }

    // Redirect to prevent duplicate submission on refresh
    header('Location: clients.php');
    exit;
}

// Fetch all clients
$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY id DESC");
    $stmt->execute(["%{$search}%", "%{$search}%", "%{$search}%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY id DESC");
}
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Directory - barewithtea Dashboard</title>
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
            <h3 class="fw-bold mb-0"><i class="fa-solid fa-address-book text-dark me-2"></i> Client Management Directory</h3>
            <p class="text-muted small mb-0">Manage existing client accounts, indemnity completion status, and Excel batch uploads.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addClientModal"><i class="fa-solid fa-user-plus me-1"></i> Add New Client</button>
            <button class="btn btn-outline-success fw-bold" data-bs-toggle="modal" data-bs-target="#importExcelModal"><i class="fa-solid fa-file-excel me-1"></i> Upload Existing Excel List</button>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card card-custom p-3 mb-4 shadow-sm">
        <form method="GET" action="clients.php" class="row g-2 align-items-center">
            <div class="col-md-9">
                <input type="text" name="search" class="form-control" placeholder="Search client by name, email or phone..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> Search</button>
                <?php if (!empty($search)): ?>
                    <a href="clients.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Clients Directory Table -->
    <div class="card card-custom shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#ID</th>
                        <th>Client Name</th>
                        <th>Email Address</th>
                        <th>Phone Number</th>
                        <th>Indemnity Status</th>
                        <th>Notes / History</th>
                        <th>Created Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No clients found. Click "Add New Client" or "Upload Existing Excel List".</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $c): ?>
                            <tr>
                                <td class="fw-bold">#<?= $c['id'] ?></td>
                                <td><span class="fw-semibold"><?= htmlspecialchars($c['name']) ?></span></td>
                                <td><a href="mailto:<?= htmlspecialchars($c['email']) ?>" class="text-decoration-none"><?= htmlspecialchars($c['email']) ?></a></td>
                                <td><?= htmlspecialchars($c['phone']) ?></td>
                                <td>
                                    <?php if ($c['indemnity_completed'] == 1): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i> Pending Form</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($c['notes'] ?: 'No notes') ?></small></td>
                                <td><small class="text-muted"><?= date('Y-m-d', strtotime($c['created_at'])) ?></small></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#editClientModal<?= $c['id'] ?>" title="Edit Client"><i class="fa-solid fa-pen"></i></button>
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#dispatchModal<?= $c['id'] ?>" title="Dispatch Indemnity Form"><i class="fa-solid fa-paper-plane"></i></button>
                                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteClientModal<?= $c['id'] ?>" title="Delete"><i class="fa-solid fa-trash"></i></button>
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

<!-- ================= MODALS CONTAINER (Placed cleanly outside table tags) ================= -->

<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="clients.php">
                <input type="hidden" name="action" value="add_client">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2"></i> Add New Client</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Sarah Jenkins">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="email" class="form-control" required placeholder="sarah@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" required placeholder="+27 82 123 4567">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Indemnity Completed?</label>
                        <select name="indemnity_completed" class="form-select">
                            <option value="0">No - Pending Submission</option>
                            <option value="1">Yes - Completed / Waived</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Client Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Preferences, skin conditions, skin patch results..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark fw-bold">Save Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Excel Modal -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="clients.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_excel">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-excel me-2"></i> Import Existing Client Excel/CSV</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Uploading an existing client list will automatically mark imported clients with <strong>Indemnity Completed = YES</strong>, so you won't need to re-send indemnity forms to existing clients.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select CSV / Excel File (.csv, .xlsx)</label>
                        <input type="file" name="excel_file" class="form-control" accept=".csv, .xlsx, .xls" required>
                    </div>
                    <div class="alert alert-light border small">
                        <strong>Expected Columns Order:</strong><br>
                        <code>Name, Email, Phone, Notes</code>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($clients as $c): ?>

    <!-- Edit Client Modal -->
    <div class="modal fade" id="editClientModal<?= $c['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="clients.php">
                    <input type="hidden" name="action" value="edit_client">
                    <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-pen me-2"></i> Edit Client #<?= $c['id'] ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($c['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($c['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($c['phone']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Indemnity Completed?</label>
                            <select name="indemnity_completed" class="form-select">
                                <option value="1" <?= $c['indemnity_completed'] == 1 ? 'selected' : '' ?>>Yes - Completed / Waived</option>
                                <option value="0" <?= $c['indemnity_completed'] == 0 ? 'selected' : '' ?>>No - Pending Submission</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Client Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($c['notes']) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark fw-bold">Update Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dispatch Indemnity Modal -->
    <div class="modal fade" id="dispatchModal<?= $c['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="clients.php">
                    <input type="hidden" name="action" value="send_indemnity_dispatch">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($c['email']) ?>">
                    <input type="hidden" name="name" value="<?= htmlspecialchars($c['name']) ?>">
                    <input type="hidden" name="phone" value="<?= htmlspecialchars($c['phone']) ?>">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-paper-plane me-2"></i> Dispatch Indemnity Form</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p class="mb-2">Dispatch digital indemnity form link to <strong><?= htmlspecialchars($c['name']) ?></strong>?</p>
                        <p class="text-muted small">This will send an email dispatch and generate a direct WhatsApp message link.</p>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold"><i class="fa-solid fa-paper-plane me-1"></i> Send Dispatch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Client Modal -->
    <div class="modal fade" id="deleteClientModal<?= $c['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="clients.php">
                    <input type="hidden" name="action" value="delete_client">
                    <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-trash me-2"></i> Confirm Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p class="mb-0">Are you sure you want to delete client record <strong><?= htmlspecialchars($c['name']) ?></strong>?</p>
                        <p class="text-muted small mt-1">This action cannot be undone.</p>
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

<script href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
