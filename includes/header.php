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
    
    <!-- Определяем BASE_URL для JavaScript -->
    <script>
        if (typeof window.BASE_URL === 'undefined') {
            window.BASE_URL = <?php echo json_encode(BASE_URL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        }
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
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>">
                <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="ERP Система" class="navbar-logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>" title="Дашборд">
                            <i class="bi bi-speedometer2"></i>
                        </a>
                    </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>work">Рабочая</a>
                                </li>
                    <li class="nav-item nav-item-has-submenu">
                        <a class="nav-link nav-link-toggle" href="#" data-submenu-trigger="pools">
                            Внести <i class="bi bi-chevron-down ms-1 d-none d-lg-inline"></i>
                        </a>
                        <div class="mobile-submenu d-lg-none">
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>meter-readings">Приборы учета</a>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>measurements">Замеры</a>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>harvests">Отборы</a>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>mortality">Падеж</a>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>weighings">Навески</a>
                            <?php if (isAdmin()): ?>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>partial-transplants">Частичная пересадка</a>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>tasks">Задачи</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>duty-calendar">
                            Календарь
                        </a>
                    </li>
                    <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>payroll">
                            ФЗП
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>finances">
                            Финансы
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isAdmin()): ?>
                    <li class="nav-item nav-item-has-submenu me-3">
                        <a class="nav-link nav-link-toggle" href="#" data-submenu-trigger="admin" title="Настройки">
                            <i class="bi bi-gear"></i> <i class="bi bi-chevron-down ms-1 d-none d-lg-inline"></i>
                        </a>
                        <div class="mobile-submenu d-lg-none">
                            <div class="mobile-submenu-group mb-2">
                                <div class="text-muted small mb-1">Конфигурация</div>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>pools">Бассейны</a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>plantings">Посадки</a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>sessions">Сессии</a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>feeds">Корма</a>
                            </div>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>users">Пользователи</a>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>counterparties">Контрагенты</a>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>meters">Приборы учета</a>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>news">Новости</a>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>shift-tasks">Задания смены</a>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>settings">Системные настройки</a>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>logs">Логи</a>
                        </div>
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
    <div class="navbar-submenu-wrapper" id="navbarSubmenuWrapper">
        <div class="container">
            <div class="submenu-panel" data-submenu-panel="pools">
                <div class="submenu-links">
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>meter-readings">
                        <span class="submenu-title">Приборы учета</span>
                        <span class="submenu-desc">Внесение показаний счетчиков</span>
                    </a>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>measurements">
                        <span class="submenu-title">Замеры</span>
                        <span class="submenu-desc">Параметры воды</span>
                    </a>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>harvests">
                        <span class="submenu-title">Отборы</span>
                        <span class="submenu-desc">История выборок рыбы</span>
                    </a>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>mortality">
                        <span class="submenu-title">Падеж</span>
                        <span class="submenu-desc">Статистика по смертности</span>
                    </a>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>weighings">
                        <span class="submenu-title">Навески</span>
                        <span class="submenu-desc">Живой вес по бассейнам</span>
                    </a>
                    <?php if (isAdmin()): ?>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>partial-transplants">
                        <span class="submenu-title">Частичная пересадка</span>
                        <span class="submenu-desc">Перемещение биомассы между сессиями</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (isAdmin()): ?>
            <div class="submenu-panel" data-submenu-panel="admin">
                <div class="submenu-links">
                    <div class="submenu-group">
                        <button class="submenu-link has-children" type="button" data-submenu-dropdown-toggle="admin-config">
                            <span class="submenu-icon"><i class="bi bi-diagram-3"></i></span>
                            <span class="submenu-info">
                                <span class="submenu-title">Конфигурация</span>
                                <span class="submenu-desc">Бассейны, посадки, сессии</span>
                            </span>
                            <i class="bi bi-chevron-down ms-2"></i>
                        </button>
                        <div class="submenu-dropdown" data-submenu-dropdown="admin-config">
                            <a class="submenu-dropdown-item" href="<?php echo BASE_URL; ?>pools">Бассейны</a>
                            <a class="submenu-dropdown-item" href="<?php echo BASE_URL; ?>plantings">Посадки</a>
                            <a class="submenu-dropdown-item" href="<?php echo BASE_URL; ?>sessions">Сессии</a>
                            <a class="submenu-dropdown-item" href="<?php echo BASE_URL; ?>feeds">Корма</a>
                        </div>
                    </div>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>users">
                        <span class="submenu-icon"><i class="bi bi-people"></i></span>
                        <span class="submenu-info">
                            <span class="submenu-title">Пользователи</span>
                            <span class="submenu-desc">Права доступа и учетные записи</span>
                        </span>
                    </a>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>counterparties">
                        <span class="submenu-icon"><i class="bi bi-building"></i></span>
                        <span class="submenu-info">
                            <span class="submenu-title">Контрагенты</span>
                            <span class="submenu-desc">Поставщики и покупатели</span>
                        </span>
                    </a>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>meters">
                        <span class="submenu-icon"><i class="bi bi-speedometer"></i></span>
                        <span class="submenu-info">
                            <span class="submenu-title">Приборы учета</span>
                            <span class="submenu-desc">Счетчики и показания</span>
                        </span>
                    </a>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>news">
                        <span class="submenu-icon"><i class="bi bi-newspaper"></i></span>
                        <span class="submenu-info">
                            <span class="submenu-title">Новости</span>
                            <span class="submenu-desc">Актуальные объявления</span>
                        </span>
                    </a>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>shift-tasks">
                        <span class="submenu-icon"><i class="bi bi-check2-square"></i></span>
                        <span class="submenu-info">
                            <span class="submenu-title">Задания смены</span>
                            <span class="submenu-desc">Шаблоны и чек-листы</span>
                        </span>
                    </a>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>settings">
                        <span class="submenu-icon"><i class="bi bi-sliders"></i></span>
                        <span class="submenu-info">
                            <span class="submenu-title">Системные настройки</span>
                            <span class="submenu-desc">Параметры платформы</span>
                        </span>
                    </a>
                    <a class="submenu-link" href="<?php echo BASE_URL; ?>logs">
                        <span class="submenu-icon"><i class="bi bi-clipboard-data"></i></span>
                        <span class="submenu-info">
                            <span class="submenu-title">Логи</span>
                            <span class="submenu-desc">Журнал системных событий</span>
                        </span>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <main class="main-content">