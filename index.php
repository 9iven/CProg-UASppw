<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CProg Tracker - Competitive Programming Analytics</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>

    <main class="hero-section d-flex flex-col align-center justify-center">
        <!-- Logo Branding (Using official logo.png image) -->
        <div class="landing-logo-container d-flex align-center gap-sm">
            <img src="assets/img/logo.png" alt="CProg Tracker Logo" class="custom-logo-img">
            <span class="landing-logo-text">CProg <span class="text-accent-yellow">Tracker</span></span>
        </div>

        <!-- Headline & Subtitle (Clear Value Proposition) -->
        <h1 class="hero-title">
            Merge and Improve Faster in <span class="text-accent-cyan">Competitive Programming</span>
        </h1>
        <p class="hero-subtitle">
            An analytics tool for competitive programmers. Connect your accounts to analyze your solve history, visualize rating trends, and get personalized problem recommendations tailored to your exact capabilities.
        </p>
        
        <!-- One Dominant CTA -->
        <a href="register.php" class="btn btn-primary btn-lg btn-glow">
            Start Tracking
        </a>
        <p class="text-dim text-sm mt-sm">User authentication required to Connect and Save handles in seconds.</p>

        <!-- Social Proof: Recognizable Platform Badges -->
        <section class="integrations-container">
            <h2 class="integrations-title">Supported Competitive Platforms</h2>
            <div class="integrations-grid d-flex justify-center align-center gap-lg flex-wrap">
                <div class="integration-badge d-flex align-center gap-xs">
                    <span class="text-accent-cyan text-accent-blue-size">&#9729;</span> Codeforces
                </div>
                <div class="integration-badge d-flex align-center gap-xs">
                    <span class="text-accent-yellow text-accent-yellow-size">&#9733;</span> LeetCode
                </div>
                <div class="integration-badge d-flex align-center gap-xs">
                    <span class="text-accent-pink text-accent-pink-size">&#9670;</span> Other Platforms
                </div>
            </div>
        </section>

        <!-- Dynamic Content: How to Use CProg Tracker -->
        <section class="steps-container">
            <h2 class="steps-title">How to Use CProg Tracker</h2>
            <div class="steps-grid">
                <!-- Step 1 -->
                <div class="card card-hover card-step border-t-pink">
                    <div class="step-number">01</div>
                    <div class="step-headline">Register Your Account</div>
                    <p class="text-muted text-sm mt-xs">
                        Create a free developer account in seconds with only your email address. No complex details required.
                    </p>
                </div>
                <!-- Step 2 -->
                <div class="card card-hover card-step border-t-cyan">
                    <div class="step-number text-magenta">02</div>
                    <div class="step-headline">Link Handles & Sync</div>
                    <p class="text-muted text-sm mt-xs">
                        Go to Settings, add your Codeforces or LeetCode handle. The platform automatically fetches your solved history.
                    </p>
                </div>
                <!-- Step 3 -->
                <div class="card card-hover card-step border-t-yellow">
                    <div class="step-number text-yellow">03</div>
                    <div class="step-headline">Analyze & Practice</div>
                    <p class="text-muted text-sm mt-xs">
                        Track rating graphs, view your difficulty trends, and solve personalized problem recommendations dynamically.
                    </p>
                </div>
            </div>
        </section>

    </main>

    <footer class="app-footer app-footer-landing">
        <div class="footer-links">
            <a href="#" class="footer-modal-trigger" data-type="pivot">Rating Pivot</a>
            <span class="footer-divider">|</span>
            <a href="#" class="footer-modal-trigger" data-type="guide">How to Use</a>
            <span class="footer-divider">|</span>
            <a href="https://github.com/9iven/CProg-UASppw" target="_blank">GitHub Repository</a>
        </div>
        <div class="footer-copyright">
            &copy; <?php echo date('Y'); ?> CProg Tracker. All rights reserved.
        </div>
    </footer>

    <!-- Universal Info Modal -->
    <div id="infoModal" class="modal">
        <div class="modal-content modal-info">
            <span class="close-modal" id="closeInfoModalBtn">&times;</span>
            <h3 id="infoModalTitle" class="modal-header-3">Information</h3>
            <div id="infoModalBody"></div>
        </div>
    </div>

    <script src="assets/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>