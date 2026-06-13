<?php
session_start();
$page_title = 'About - CProg Tracker';
require_once 'includes/head.php';

// Check if user is logged in to show the correct navigation
$is_logged_in = isset($_SESSION['user_id']);
if ($is_logged_in) {
    require_once 'includes/nav_dashboard.php';
} else {
    // Basic header for non-logged in users visiting About page
    echo '<header class="dashboard-header d-flex justify-between align-center">';
    echo '<a href="index.php" class="header-logo"><img src="assets/img/logo.png" alt="CProg Logo" class="custom-logo-img"><span>CProg <span class="text-accent-yellow">Tracker</span></span></a>';
    echo '<div class="user-profile d-flex align-center gap-md"><a href="login.php" class="btn btn-primary btn-sm">Sign In</a></div>';
    echo '</header>';
}
?>

<main class="dashboard-container">
    <div class="card card-hover" style="max-width: 800px; margin: 0 auto;">
        <h1 class="page-title text-center mb-md">About <span class="text-accent-cyan">CProg Tracker</span></h1>
        
        <div style="line-height: 1.8;">
            <p class="mb-sm text-dim">
                <strong>CProg Tracker</strong> is an advanced analytics platform designed specifically for competitive programmers who train across multiple different coding platforms.
            </p>
            
            <h3 class="mt-md mb-xs">The Problem</h3>
            <p class="mb-sm text-dim">
                If you solve problems on LeetCode, participate in Codeforces contests, and practice dynamic programming on AtCoder, your progress is fragmented. There is no easy way to visualize your overall growth, track your exact difficulty baseline, or prove your full capabilities to recruiters using just one link.
            </p>

            <h3 class="mt-md mb-xs">The Solution</h3>
            <p class="mb-sm text-dim">
                We built a unified engine that merges all your identities into one. By utilizing public APIs from Codeforces and LeetCode, the platform automatically imports your accepted submissions and contest ratings. For platforms without public APIs (like CSES or HackerRank), you can securely log manual entries with proof screenshots.
            </p>
            
            <h3 class="mt-md mb-xs">The Unified Rating System</h3>
            <p class="mb-sm text-dim">
                CProg Tracker features a proprietary pivot calculation that translates entirely different rating systems (like LeetCode's Easy/Medium/Hard) into a standardized Codeforces-equivalent Elo rating scale. This allows the system to accurately calculate your <em>Average Capability Rating</em> across the entire internet and recommend perfectly-tailored problems to push your skills forward.
            </p>

            <h3 class="mt-md mb-xs">Technology Stack</h3>
            <ul class="text-dim mb-md" style="list-style-type: disc; margin-left: 20px;">
                <li><strong>Backend:</strong> PHP 8.x</li>
                <li><strong>Database:</strong> MySQL</li>
                <li><strong>Frontend:</strong> Vanilla HTML/CSS with Custom Design System</li>
                <li><strong>Data Visualization:</strong> Chart.js</li>
            </ul>

            <div class="d-flex justify-center mt-lg">
                <?php if (!$is_logged_in): ?>
                    <a href="register.php" class="btn btn-primary btn-lg btn-glow">Start Tracking Now</a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-secondary btn-lg">Return to Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
