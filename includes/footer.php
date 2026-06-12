<?php
$is_landing = isset($is_landing) && $is_landing ? 'app-footer-landing' : '';
$no_footer = isset($no_footer) && $no_footer;

if (!$no_footer):
?>
    <footer class="app-footer <?php echo $is_landing; ?>">
        <div class="footer-links d-flex justify-center gap-md flex-wrap">
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
<?php endif; ?>

    <!-- Universal Info Modal -->
    <div id="infoModal" class="modal">
        <div class="modal-content modal-info">
            <span class="close-modal" id="closeInfoModalBtn">&times;</span>
            <h3 id="infoModalTitle" class="modal-header-3">Information</h3>
            <div id="infoModalBody"></div>
        </div>
    </div>

    <script src="assets/js/script.js?v=<?php echo time(); ?>"></script>
    <?php if (isset($extra_scripts)) echo $extra_scripts; ?>
</body>
</html>
