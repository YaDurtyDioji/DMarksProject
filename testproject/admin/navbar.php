<?php
// admin/navbar.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg border-bottom sticky-top py-3 shadow-sm" style="background-color: #e4dcc6;">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-uppercase small tracking-wide text-dark" href="dashboard.php" style="letter-spacing: 1px;">
            barewithtea.
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-item nav-link <?= $currentPage === 'dashboard.php' ? 'active fw-bold' : 'text-dark' ?>" href="dashboard.php">
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-item nav-link <?= $currentPage === 'history.php' ? 'active fw-bold' : 'text-dark' ?>" href="history.php">
                        History
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-item nav-link <?= $currentPage === 'clients.php' ? 'active fw-bold' : 'text-dark' ?>" href="clients.php">
                        Clients
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-item nav-link <?= $currentPage === 'prices.php' ? 'active fw-bold' : 'text-dark' ?>" href="prices.php">
                        Price List
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-item nav-link <?= $currentPage === 'promotions.php' ? 'active fw-bold' : 'text-dark' ?>" href="promotions.php">
                        <i class="fa-solid fa-tags me-1"></i> Promotions & Combos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-item nav-link <?= $currentPage === 'settings.php' ? 'active fw-bold' : 'text-dark' ?>" href="settings.php">
                        Settings & Admins
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <span class="text-dark small"><i class="fa-solid fa-user me-1"></i> <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></span>
                <a href="../index.html" class="btn btn-sm btn-outline-dark" target="_blank">View Site</a>
                <a href="logout.php" class="btn btn-sm text-white" style="background-color: #212529;">Logout</a>
            </div>
        </div>
    </div>
</nav>
