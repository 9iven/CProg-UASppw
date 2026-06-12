<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
    <header class="dashboard-header d-flex justify-between align-center">
        <a href="dashboard.php" class="header-logo">
            <img src="assets/img/logo.png" alt="CProg Logo" class="custom-logo-img">
            <span>CProg <span class="text-accent-yellow">Tracker</span></span>
        </a>
        <div class="user-profile d-flex align-center gap-md">
            <?php if ($current_page == 'dashboard.php'): ?>
                <a href="sync_all.php" class="btn btn-primary btn-sm d-flex align-center gap-xs">&#8635; Sync Now</a>
                <a href="manage_problems.php" class="nav-link d-flex align-center gap-xs">&#128218; Manage Problems</a>
                <a href="settings.php" class="nav-link d-flex align-center gap-xs">&#9881; Settings</a>
                <a href="logout.php" class="btn btn-secondary btn-sm">Sign Out</a>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
            <?php endif; ?>
        </div>
    </header>
