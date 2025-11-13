    </main>
    
    <footer class="bg-light py-3 mt-auto">
        <div class="container text-center">
            <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> ERP Форель. v.0.1.0-alpha. Все права защищены.</p>
        </div>
    </footer>
    
    <!-- Theme Toggle Script -->
    <script src="<?php echo asset_url('assets/js/theme.js'); ?>"></script>

    <?php if (!empty($extra_body_scripts) && is_array($extra_body_scripts)): ?>
        <?php foreach ($extra_body_scripts as $scriptPath): ?>
            <?php
                $isAbsolute = is_string($scriptPath) && preg_match('~^https?://~i', $scriptPath);
                $src = $isAbsolute ? $scriptPath : asset_url(ltrim($scriptPath, '/'));
            ?>
            <script src="<?php echo $src; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
