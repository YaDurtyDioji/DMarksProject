<?php
// admin/prices.php
session_start();
require_once __DIR__ . '/../backend/db.php';

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

// Handle Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=barewithtea_pricelist_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Category', 'Name', 'Duration', 'Price (ZAR)']);
    
    $rows = $pdo->query("SELECT id, category, name, duration, price FROM prices ORDER BY category ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_price') {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $duration = trim($_POST['duration'] ?? '30 mins');
        $price = trim($_POST['price'] ?? '0.00');

        if (!empty($name) && !empty($price)) {
            $stmt = $pdo->prepare("INSERT INTO prices (name, category, duration, price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $category, $duration, $price]);
            logAudit($pdo, $currentUser, "Added Price Item: {$name}");
            $_SESSION['flash_msg'] = "Service '{$name}' added to price list.";
        }
    } elseif ($action === 'edit_price') {
        $id = intval($_POST['price_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $duration = trim($_POST['duration'] ?? '30 mins');
        $price = trim($_POST['price'] ?? '0.00');

        if ($id > 0 && !empty($name)) {
            $stmt = $pdo->prepare("UPDATE prices SET name = ?, category = ?, duration = ?, price = ? WHERE id = ?");
            $stmt->execute([$name, $category, $duration, $price, $id]);
            logAudit($pdo, $currentUser, "Updated Price Item #{$id}");
            $_SESSION['flash_msg'] = "Service '{$name}' updated.";
        }
    } elseif ($action === 'delete_price') {
        $id = intval($_POST['price_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM prices WHERE id = ?");
            $stmt->execute([$id]);
            logAudit($pdo, $currentUser, "Deleted Price Item #{$id}");
            $_SESSION['flash_msg'] = "Service removed from price list.";
        }
    } elseif ($action === 'upload_excel_prices') {
        if (isset($_FILES['price_file']) && $_FILES['price_file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['price_file']['tmp_name'];
            $handle = fopen($tmpPath, 'r');
            if ($handle !== false) {
                fgetcsv($handle); // Skip header
                $count = 0;
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) >= 3) {
                        $cat = trim($data[0] ?? 'General');
                        $pName = trim($data[1] ?? '');
                        $pDur = trim($data[2] ?? '30 mins');
                        $pPrice = trim($data[3] ?? '0.00');
                        
                        if (!empty($pName)) {
                            $stmt = $pdo->prepare("INSERT INTO prices (category, name, duration, price) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$cat, $pName, $pDur, $pPrice]);
                            $count++;
                        }
                    }
                }
                fclose($handle);
                logAudit($pdo, $currentUser, "Imported {$count} Price Items via CSV/Excel");
                $_SESSION['flash_msg'] = "Imported {$count} price list items successfully.";
            }
        }
    }

    header('Location: prices.php');
    exit;
}

$prices = $pdo->query("SELECT * FROM prices ORDER BY category ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price List - barewithtea</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #faf9f6; font-family: system-ui, -apple-system, sans-serif; color: #212529; }
        .card-custom { border-radius: 0px; border: 1px solid #d7ccab; background: #ffffff; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-5">
    
    <?php if (!empty($flashMessage)): ?>
        <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <?= $flashMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1 text-uppercase"><i class="fa-solid fa-list-check me-2"></i> Treatment Price List</h3>
            <p class="text-muted small mb-0">Manage services, prices, and launch promotional campaigns.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="promotions.php" class="btn btn-outline-warning btn-sm text-dark rounded-0 fw-bold"><i class="fa-solid fa-tags me-1"></i> Promotions Manager</a>
            <a href="prices.php?export=csv" class="btn btn-outline-dark btn-sm rounded-0 fw-bold"><i class="fa-solid fa-download me-1"></i> Export Excel/CSV</a>
            <button class="btn btn-sm btn-outline-primary rounded-0 fw-bold" data-bs-toggle="modal" data-bs-target="#uploadPriceExcelModal"><i class="fa-solid fa-file-excel me-1"></i> Bulk Upload</button>
            <button class="btn btn-sm btn-dark rounded-0 fw-bold text-uppercase" data-bs-toggle="modal" data-bs-target="#createPriceModal"><i class="fa-solid fa-plus me-1"></i> Add Service</button>
        </div>
    </div>

    <div class="card card-custom p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle border mb-0">
                <thead style="background-color: #e4dcc6;">
                    <tr>
                        <th>Category</th>
                        <th>Service / Treatment</th>
                        <th>Duration</th>
                        <th>Standard Price (ZAR)</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prices)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No items in the price list. Click "Add Service" to populate.</td></tr>
                    <?php else: ?>
                        <?php foreach ($prices as $pr): ?>
                            <tr>
                                <td><span class="badge bg-secondary rounded-0"><?= htmlspecialchars($pr['category']) ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($pr['name']) ?></td>
                                <td><?= htmlspecialchars($pr['duration']) ?></td>
                                <td class="fw-bold text-success">R <?= number_format((float)$pr['price'], 2) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-dark rounded-0" data-bs-toggle="modal" data-bs-target="#editPriceModal<?= $pr['id'] ?>"><i class="fa-solid fa-pen"></i></button>
                                    <form method="POST" action="prices.php" class="d-inline" onsubmit="return confirm('Delete this service?');">
                                        <input type="hidden" name="action" value="delete_price">
                                        <input type="hidden" name="price_id" value="<?= $pr['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-0"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals for Price List -->
<div class="modal fade" id="createPriceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <form method="POST" action="prices.php">
                <input type="hidden" name="action" value="create_price">
                <div class="modal-header border-bottom-0" style="background-color: #e4dcc6;">
                    <h5 class="modal-title fw-bold text-uppercase">Add Treatment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Category</label>
                        <input type="text" name="category" class="form-control rounded-0" placeholder="e.g. Laser Hair Removal" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Service Name</label>
                        <input type="text" name="name" class="form-control rounded-0" placeholder="e.g. Underarm Laser" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Duration</label>
                        <input type="text" name="duration" class="form-control rounded-0" placeholder="e.g. 30 mins" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Standard Price (ZAR)</label>
                        <input type="number" step="0.01" name="price" class="form-control rounded-0" placeholder="250.00" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="submit" class="btn btn-dark w-100 rounded-0 text-uppercase fw-bold">Save Treatment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Excel Modal for Prices -->
<div class="modal fade" id="uploadPriceExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <form method="POST" action="prices.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_excel_prices">
                <div class="modal-header border-bottom-0" style="background-color: #e4dcc6;">
                    <h5 class="modal-title fw-bold text-uppercase"><i class="fa-solid fa-file-excel me-2"></i> Bulk Import Price List (CSV/Excel)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Upload a CSV or Excel file with treatment prices. Expected columns: <code>Category, ServiceName, Duration, Price</code>.</p>
                    <div class="mb-3">
                        <input type="file" name="price_file" class="form-control rounded-0" accept=".csv, .xlsx" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="submit" class="btn btn-dark w-100 rounded-0 text-uppercase fw-bold">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($prices as $pr): ?>
    <div class="modal fade" id="editPriceModal<?= $pr['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-0">
                <form method="POST" action="prices.php">
                    <input type="hidden" name="action" value="edit_price">
                    <input type="hidden" name="price_id" value="<?= $pr['id'] ?>">
                    <div class="modal-header border-bottom-0" style="background-color: #e4dcc6;">
                        <h5 class="modal-title fw-bold text-uppercase">Edit Treatment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <input type="text" name="category" class="form-control rounded-0" value="<?= htmlspecialchars($pr['category']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Service Name</label>
                            <input type="text" name="name" class="form-control rounded-0" value="<?= htmlspecialchars($pr['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Duration</label>
                            <input type="text" name="duration" class="form-control rounded-0" value="<?= htmlspecialchars($pr['duration']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Standard Price (ZAR)</label>
                            <input type="number" step="0.01" name="price" class="form-control rounded-0" value="<?= htmlspecialchars($pr['price']) ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="submit" class="btn btn-dark w-100 rounded-0 text-uppercase fw-bold">Update Treatment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
