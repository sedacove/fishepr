    </main>
    
    <footer class="bg-light py-3 mt-auto">
        <div class="container text-center">
            <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> ERP Форель. v.0.1.0-alpha. Все права защищены.</p>
        </div>
    </footer>

    <?php
    $debugPanelActive = !empty($debugModeEnabled) && function_exists('isAdmin') && isAdmin() && class_exists('DebugProfiler');
    if ($debugPanelActive):
        $debugStatsPayload = DebugProfiler::getSummary();
    ?>
        <div class="debug-dot" id="debugInfoDot" title="Отладочная информация"></div>
        <div class="debug-panel-overlay" id="debugInfoOverlay">
            <div class="debug-panel-content">
                <div class="debug-panel-header">
                    <h6 class="mb-0">Отладочная информация</h6>
                    <button type="button" class="debug-panel-close" data-debug-close>&times;</button>
                </div>
                <ul class="debug-summary-list" data-debug-summary>
                    <li><span>Время генерации</span><strong>—</strong></li>
                </ul>
                <div class="mb-4">
                    <h6 class="mb-2">AJAX-запросы</h6>
                    <div class="debug-queries-list" data-debug-ajax></div>
                </div>
                <div>
                    <h6 class="mb-2">SQL-запросы</h6>
                    <div class="debug-queries-list" data-debug-queries></div>
                </div>
            </div>
        </div>
        <script>
            window.DEBUG_STATS = <?php echo json_encode($debugStatsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        </script>
        <script src="<?php echo asset_url('assets/js/debug-panel.js'); ?>"></script>
    <?php endif; ?>
    
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
