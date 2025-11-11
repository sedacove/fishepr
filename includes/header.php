<?php
if (!function_exists('asset_url')) {
    require_once __DIR__ . '/../config/config.php';
}

if (!function_exists('asset_url')) {
    /**
     * Резервная функция на случай, если конфигурация не подгрузила asset_url.
     */
    function asset_url($relativePath)
    {
        $relativePath = ltrim((string)$relativePath, '/');
        if ($relativePath === '') {
            return BASE_URL;
        }

        $separator = strpos($relativePath, '?') === false ? '?' : '&';

        return BASE_URL . $relativePath . $separator . 'v=' . time();
    }
}

if (!isset($page_title)) {
    $page_title = 'ERP Система';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>assets/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>assets/favicon/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>assets/favicon/apple-touch-icon.png">
    <link rel="manifest" href="<?php echo BASE_URL; ?>assets/favicon/site.webmanifest">
    <meta name="theme-color" content="#0d6efd">
    <!-- Google Fonts - Bitter (для заголовков) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bitter:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    
    <!-- Google Fonts - Roboto (для основного текста) -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">
    
    <!-- DS-Digital Font -->
    <link rel="preconnect" href="https://fonts.cdnfonts.com">
    <link href="https://fonts.cdnfonts.com/css/ds-digital" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Основные стили -->
    <link href="<?php echo asset_url('assets/css/style.css'); ?>" rel="stylesheet">

    <?php if (!empty($extra_styles) && is_array($extra_styles)): ?>
        <?php foreach ($extra_styles as $stylePath): ?>
            <?php
                $isAbsolute = is_string($stylePath) && preg_match('~^https?://~i', $stylePath);
                $href = $isAbsolute ? $stylePath : asset_url(ltrim($stylePath, '/'));
            ?>
            <link href="<?php echo $href; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Инициализация темы (предотвращает мерцание) -->
    <script>
        (function() {
            const theme = localStorage.getItem('app_theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (!empty($extra_head_scripts) && is_array($extra_head_scripts)): ?>
        <?php foreach ($extra_head_scripts as $scriptPath): ?>
            <?php
                $isAbsolute = is_string($scriptPath) && preg_match('~^https?://~i', $scriptPath);
                $src = $isAbsolute ? $scriptPath : asset_url(ltrim($scriptPath, '/'));
            ?>
            <script src="<?php echo $src; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Обновление времени в хедере -->
    <script>
        function updateTime() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = hours + ':' + minutes;
            }
        }
        
        // Обновляем время сразу и каждую секунду
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                updateTime();
                setInterval(updateTime, 1000);
            });
        } else {
            updateTime();
            setInterval(updateTime, 1000);
        }
    </script>
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>index.php">
                <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="ERP Система" class="navbar-logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>index.php" title="Дашборд">
                            <i class="bi bi-speedometer2"></i>
                        </a>
                    </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>work">Рабочая</a>
                                </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="poolsDropdown" role="button" data-bs-toggle="dropdown">
                            Бассейны
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>measurements">Замеры</a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>harvests">Отборы</a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>mortality">Падеж</a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>weighings">Навески</a>
                            </li>
                        </ul>
                    </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>meter-readings">Приборы</a>
                                </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>tasks">Задачи</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>pages/duty_calendar.php">
                            Календарь
                        </a>
                    </li>
                    <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>pages/payroll.php">
                            ФЗП
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>pages/finances.php">
                            Финансы
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>pages/logs.php">Логи</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isAdmin()): ?>
                    <li class="nav-item dropdown d-flex align-items-center me-3">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" title="Администрирование">
                            <i class="bi bi-gear"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>users">
                                    <i class="bi bi-people"></i> Пользователи
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>pages/pools.php">
                                    <i class="bi bi-water"></i> Бассейны
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>pages/plantings.php">
                                    <i class="bi bi-inbox"></i> Посадки
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>pages/sessions.php">
                                    <i class="bi bi-diagram-3"></i> Сессии
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>counterparties">
                                    <i class="bi bi-building"></i> Контрагенты
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>meters">
                                    <i class="bi bi-speedometer"></i> Приборы учета
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>news">
                                    <i class="bi bi-newspaper"></i> Новости
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>pages/settings.php">
                                    <i class="bi bi-sliders"></i> Настройки
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item d-flex align-items-center">
                        <span class="clock-time me-3" id="currentTime"></span>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($_SESSION['user_full_name'] ?? $_SESSION['user_login']); ?>
                            <?php if (isAdmin()): ?>
                                <span class="badge bg-danger">Админ</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="#" onclick="toggleTheme(); return false;">
                                    <i id="theme-toggle-icon" class="bi bi-moon-fill"></i>
                                    <span id="theme-toggle-text">Темная тема</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>auth/logout.php">Выход</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <main class="main-content">