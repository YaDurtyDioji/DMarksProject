<?php
// admin/promotions.php
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

// Handle Excel / CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=barewithtea_promotions_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Code', 'Type', 'Title', 'Description', 'Discount Type', 'Discount Value', 'Promo Price', 'Applicable Service', 'Start Date', 'End Date', 'Usage Limit', 'Status']);
    
    $rows = $pdo->query("SELECT id, code, type, title, description, discount_type, discount_value, promo_price, applicable_service, start_date, end_date, usage_limit, status FROM promotions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_promotion') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = $_POST['type'] ?? 'promo_code';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $discType = $_POST['discount_type'] ?? 'percent';
        $discVal = floatval($_POST['discount_value'] ?? 0);
        $promoPrice = floatval($_POST['promo_price'] ?? 0);
        $appService = trim($_POST['applicable_service'] ?? 'all');
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $usageLimit = intval($_POST['usage_limit'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (empty($code)) {
            $code = 'TEA-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO promotions (code, type, title, description, discount_type, discount_value, promo_price, applicable_service, start_date, end_date, usage_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $type, $title, $description, $discType, $discVal, $promoPrice, $appService, $startDate, $endDate, $usageLimit, $status]);
            
            logAudit($pdo, $currentUser, "Created Promotion Campaign: {$code}");
            $_SESSION['flash_msg'] = "Promotion / Combo campaign '{$code}' created successfully.";
        } catch (Exception $e) {
            $_SESSION['flash_msg'] = "Error: Promo Code '{$code}' already exists.";
            $_SESSION['flash_type'] = "danger";
        }

    } elseif ($action === 'delete_promotion') {
        $id = intval($_POST['promo_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
            $stmt->execute([$id]);
            logAudit($pdo, $currentUser, "Deleted Promotion #{$id}");
            $_SESSION['flash_msg'] = "Promotion campaign deleted.";
        }
    } elseif ($action === 'upload_excel') {
        // Bulk Upload CSV / Excel processing
        if (isset($_FILES['promo_file']) && $_FILES['promo_file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['promo_file']['tmp_name'];
            $handle = fopen($tmpPath, 'r');
            if ($handle !== false) {
                $header = fgetcsv($handle); // Skip header row
                $count = 0;
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) >= 4) {
                        $pCode = strtoupper(trim($data[0] ?? ''));
                        $pType = trim($data[1] ?? 'promo_code');
                        $pTitle = trim($data[2] ?? 'Campaign');
                        $pDiscVal = floatval($data[3] ?? 0);
                        $pPromoPrice = floatval($data[4] ?? 0);
                        
                        if (!empty($pCode)) {
                            $stmt = $pdo->prepare("INSERT OR REPLACE INTO promotions (code, type, title, discount_value, promo_price, status) VALUES (?, ?, ?, ?, ?, 'active')");
                            $stmt->execute([$pCode, $pType, $pTitle, $pDiscVal, $pPromoPrice]);
                            $count++;
                        }
                    }
                }
                fclose($handle);
                logAudit($pdo, $currentUser, "Bulk Uploaded {$count} Promotions via CSV/Excel");
                $_SESSION['flash_msg'] = "Successfully imported/updated {$count} promotions from file.";
            } else {
                $_SESSION['flash_msg'] = "Unable to read uploaded file.";
                $_SESSION['flash_type'] = "danger";
            }
        }
    }

    header('Location: promotions.php');
    exit;
}

$promotions = $pdo->query("SELECT * FROM promotions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$services = $pdo->query("SELECT name FROM prices ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions & Combos - barewithtea</title>
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
            <h3 class="fw-bold mb-1 text-uppercase"><i class="fa-solid fa-tags text-warning me-2"></i> Promotions, Combos & Campaigns</h3>
            <p class="text-muted small mb-0">Create promo codes, limited-time price reductions, and special treatment combo offers.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="promotions.php?export=csv" class="btn btn-outline-dark btn-sm rounded-0 fw-bold"><i class="fa-solid fa-download me-1"></i> Export CSV/Excel</a>
            <button class="btn btn-sm btn-outline-primary rounded-0 fw-bold" data-bs-toggle="modal" data-bs-target="#uploadExcelModal"><i class="fa-solid fa-file-excel me-1"></i> Bulk Upload</button>
            <button class="btn btn-sm btn-dark rounded-0 fw-bold text-uppercase" data-bs-toggle="modal" data-bs-target="#createPromoModal"><i class="fa-solid fa-plus me-1"></i> New Promo / Combo</button>
        </div>
    </div>

    <!-- Active Promotions Grid -->
    <div class="card card-custom p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle border mb-0">
                <thead style="background-color: #e4dcc6;">
                    <tr>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Campaign Title</th>
                        <th>Offer Details</th>
                        <th>Validity Period</th>
                        <th>Usage</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($promotions)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No promotional campaigns active yet. Click "New Promo / Combo" to create one.</td></tr>
                    <?php else: ?>
                        <?php foreach ($promotions as $p): ?>
                            <tr>
                                <td class="fw-bold text-primary"><code><?= htmlspecialchars($p['code']) ?></code></td>
                                <td>
                                    <?php if ($p['type'] === 'special_combo'): ?>
                                        <span class="badge bg-success rounded-0">Special Combo</span>
                                    <?php elseif ($p['type'] === 'price_reduction'): ?>
                                        <span class="badge bg-warning text-dark rounded-0">Price Cut</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark rounded-0">Promo Code</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold"><?= htmlspecialchars($p['title']) ?></span><br>
                                    <small class="text-muted"><?= htmlspecialchars($p['description']) ?></small>
                                </td>
                                <td>
                                    <?php if ($p['type'] === 'special_combo' || $p['promo_price'] > 0): ?>
                                        <strong class="text-success">R <?= number_format($p['promo_price'], 2) ?></strong>
                                    <?php else: ?>
                                        <strong><?= $p['discount_type'] === 'percent' ? $p['discount_value'] . '%' : 'R ' . $p['discount_value'] ?> OFF</strong>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?= !empty($p['start_date']) ? $p['start_date'] : 'Immediate' ?> to <?= !empty($p['end_date']) ? $p['end_date'] : 'Ongoing' ?>
                                </td>
                                <td class="small">
                                    <?= $p['times_used'] ?> / <?= $p['usage_limit'] > 0 ? $p['usage_limit'] : '∞' ?>
                                </td>
                                <td>
                                    <span class="badge <?= $p['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?> rounded-0"><?= strtoupper($p['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="promotions.php" class="d-inline" onsubmit="return confirm('Delete this promotion?');">
                                        <input type="hidden" name="action" value="delete_promotion">
                                        <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
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

<!-- Create Promotion Modal -->
<div class="modal fade" id="createPromoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-0">
            <form method="POST" action="promotions.php">
                <input type="hidden" name="action" value="create_promotion">
                <div class="modal-header border-bottom-0" style="background-color: #e4dcc6;">
                    <h5 class="modal-title fw-bold text-uppercase"><i class="fa-solid fa-tag me-2"></i> New Promotion / Combo Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Promotion Type</label>
                            <select name="type" id="promoTypeSelect" class="form-select rounded-0" required>
                                <option value="promo_code">Discount Code Campaign</option>
                                <option value="special_combo">Special Combo Offer</option>
                                <option value="price_reduction">Limited Time Price Cut</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Promo Code</label>
                            <div class="input-group">
                                <input type="text" name="code" id="promoCodeInput" class="form-control rounded-0 text-uppercase" placeholder="e.g. SPRING2026">
                                <button type="button" class="btn btn-outline-secondary rounded-0" id="genCodeBtn"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> Auto Generate</button>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Campaign Title</label>
                            <input type="text" name="title" class="form-control rounded-0" placeholder="e.g. Full Body + Lash Special" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Applicable Treatment</label>
                            <select name="applicable_service" class="form-select rounded-0">
                                <option value="all">All Treatments & Services</option>
                                <?php foreach ($services as $srv): ?>
                                    <option value="<?= htmlspecialchars($srv) ?>"><?= htmlspecialchars($srv) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Description / Terms</label>
                            <textarea name="description" class="form-control rounded-0" rows="2" placeholder="Brief campaign details..."></textarea>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Discount Method</label>
                            <select name="discount_type" class="form-select rounded-0">
                                <option value="percent">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (ZAR)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Discount Value</label>
                            <input type="number" step="0.01" name="discount_value" class="form-control rounded-0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Special Combo Price (ZAR)</label>
                            <input type="number" step="0.01" name="promo_price" class="form-control rounded-0" value="0" placeholder="e.g. 450.00">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Start Date</label>
                            <input type="date" name="start_date" class="form-control rounded-0" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">End Date</label>
                            <input type="date" name="end_date" class="form-control rounded-0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Usage Limit (0 = ∞)</label>
                            <input type="number" name="usage_limit" class="form-control rounded-0" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="submit" class="btn btn-dark w-100 rounded-0 text-uppercase fw-bold">Save Campaign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Excel Modal -->
<div class="modal fade" id="uploadExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <form method="POST" action="promotions.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_excel">
                <div class="modal-header border-bottom-0" style="background-color: #e4dcc6;">
                    <h5 class="modal-title fw-bold text-uppercase"><i class="fa-solid fa-file-excel me-2"></i> Bulk Import Promotions (Excel / CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Upload a CSV or Excel spreadsheet containing promotional campaigns. Expected columns: <code>Code, Type, Title, DiscountValue, PromoPrice</code>.</p>
                    <div class="mb-3">
                        <input type="file" name="promo_file" class="form-control rounded-0" accept=".csv, .xlsx" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="submit" class="btn btn-dark w-100 rounded-0 text-uppercase fw-bold">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('genCodeBtn').addEventListener('click', function() {
    let randCode = 'TEA-' + Math.random().toString(36).substring(2, 8).toUpperCase();
    document.getElementById('promoCodeInput').value = randCode;
});
</script>
</body>
</html>
