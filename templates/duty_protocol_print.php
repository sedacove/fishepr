<?php
/**
 * Шаблон для печати протокола дежурства
 * Можно открыть напрямую в браузере: templates/duty_protocol_print.php?date=2024-01-15
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/duty_helpers.php';
require_once __DIR__ . '/../app/Support/Autoloader.php';

// Регистрация автозагрузчика классов
$autoloader = new App\Support\Autoloader();
$autoloader->addNamespace('App', __DIR__ . '/../app');
$autoloader->register();

// Проверка авторизации
if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

// Получаем дату из GET параметра или используем текущую дату с учетом смены в 8:00
$date = $_GET['date'] ?? getTodayDutyDate();

// Подключение к БД
$pdo = getDBConnection();

// Получаем дежурного на указанную дату
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        u.login as user_login,
        u.full_name as user_full_name
    FROM duty_schedule d
    LEFT JOIN users u ON d.user_id = u.id
    WHERE d.date = ?
");
$stmt->execute([$date]);
$duty = $stmt->fetch();

$dutyName = 'Не назначен';
if ($duty) {
    $dutyName = $duty['user_full_name'] ?: $duty['user_login'] ?: 'Не назначен';
    $isFasting = (bool)$duty['is_fasting'];
} else {
    $isFasting = false;
}

// Получаем предыдущего дежурного (на предыдущий день)
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        u.login as user_login,
        u.full_name as user_full_name
    FROM duty_schedule d
    LEFT JOIN users u ON d.user_id = u.id
    WHERE d.date = ?
    ORDER BY d.date DESC
    LIMIT 1
");
$stmt->execute([$prevDate]);
$prevDuty = $stmt->fetch();

$prevDutyName = 'Не назначен';
if ($prevDuty) {
    $prevDutyName = $prevDuty['user_full_name'] ?: $prevDuty['user_login'] ?: 'Не назначен';
}

// Получаем бассейны с активными сессиями
$workService = new \App\Services\WorkService($pdo);
$pools = $workService->getPools();

$poolsWithFeeding = [];
foreach ($pools as $pool) {
    if (!isset($pool['active_session']) || !$pool['active_session']) {
        continue;
    }
    
    $session = $pool['active_session'];
    $feedingPlan = $session['feeding_plan'] ?? null;
    
    $feedingAmount = null;
    if (!$isFasting && $feedingPlan && isset($feedingPlan['per_feeding'])) {
        $feedingAmount = round($feedingPlan['per_feeding'], 2);
    }
    
    // Получаем информацию о корме и стратегии
    $feedName = $session['feed_name'] ?? null;
    $feedingStrategy = $session['feeding_strategy'] ?? null;
    $strategyLabel = null;
    if ($feedingStrategy) {
        $strategyLabels = [
            'econom' => 'Эконом',
            'normal' => 'Норма',
            'growth' => 'Рост'
        ];
        $strategyLabel = $strategyLabels[$feedingStrategy] ?? $feedingStrategy;
    }
    
    // Если есть feeding_plan, берем strategy_label и коэффициент кормления оттуда
    $feedingCoefficient = null;
    if ($feedingPlan && isset($feedingPlan['strategy_label'])) {
        $strategyLabel = $feedingPlan['strategy_label'];
    }
    if ($feedingPlan && isset($feedingPlan['ratio_per_100kg'])) {
        $feedingCoefficient = $feedingPlan['ratio_per_100kg'];
    }
    
    $poolsWithFeeding[] = [
        'pool_id' => $pool['id'],
        'pool_name' => $pool['name'],
        'session_name' => $session['name'] ?? null,
        'feeding_amount' => $feedingAmount,
        'feed_name' => $feedName,
        'feeding_strategy' => $feedingStrategy,
        'strategy_label' => $strategyLabel,
        'feeding_coefficient' => $feedingCoefficient
    ];
}

// Получаем список всех приборов учета
$meterService = new \App\Services\MeterReadingService($pdo);
$meters = $meterService->getAllMeters();

// Форматируем дату для отображения
$dateObj = DateTime::createFromFormat('Y-m-d', $date);
$formattedDate = $dateObj ? $dateObj->format('d.m.Y') : $date;

// Получаем имя текущего пользователя (администратора, который создает бланк)
$currentUserName = $_SESSION['user_full_name'] ?? $_SESSION['user_login'] ?? 'Администратор';

// Функция для экранирования HTML
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Протокол дежурства</title>
    <!-- Google Fonts - Bitter (для заголовков) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bitter:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    
    <!-- Google Fonts - Roboto (для основного текста) -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">
    
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 0.8cm;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .print-content {
                width: 100%;
                max-width: 100%;
            }
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            font-size: 10.8pt;
            margin: 0;
            padding: 15px;
            background: #fff;
        }
        
        .print-content {
            width: 1123px;
            max-width: 1123px;
            margin: 0 auto;
            padding: 12px;
            box-sizing: border-box;
        }
        
        .print-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
            line-height: 1.4;
            gap: 15px;
        }
        
        .print-logo {
            width: 120px;
            flex-shrink: 0;
            margin-right: 25px;
        }
        
        .print-logo img {
            width: 120px;
            height: 115px;
            object-fit: contain;
        }
        
        .print-title {
            flex: 0 1 auto;
            min-width: 280px;
        }
        
        .print-signatures {
            width: 220px;
            flex-shrink: 0;
        }
        
        .signature-lines {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .signature-line {
            white-space: nowrap;
            font-size: 10.8pt;
            line-height: 1.6;
        }
        
        .signature-field {
            display: inline-block;
            min-width: 100px;
            border-bottom: 1px solid #333;
            margin-left: 5px;
            padding-bottom: 2px;
        }
        
        .print-title h1 {
            font-family: 'Bitter', serif;
            font-size: 16.8pt;
            font-weight: bold;
            margin: 0 0 6px 0;
            color: #333;
            line-height: 1.3;
        }
        
        .print-title .subtitle {
            font-size: 12pt;
            margin: 3px 0;
            color: #555;
            line-height: 1.4;
        }
        
        
        .print-content-body {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .print-left, .print-right {
            flex: 1;
        }
        
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9.6pt;
        }
        
        .print-table th,
        .print-table td {
            border: 0.5px solid #999;
            padding: 6px;
            text-align: left;
        }
        
        .print-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: center;
            font-size: 10.2pt;
        }
        
        @media print {
            .unified-table {
                page-break-inside: avoid;
            }
            .feeding-info {
                page-break-inside: avoid;
            }
        }
        
        .print-field {
            min-height: 24px;
            border-bottom: 0.5px solid #999;
        }
        
        .feeding-info {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px;
        }
        
        .feeding-info h4 {
            font-family: 'Bitter', serif;
            margin: 0 0 6px 0;
            font-size: 12pt;
            font-weight: bold;
            color: #333;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
        }
        
        .feeding-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        
        .feeding-list li {
            margin: 5px 0;
            line-height: 1.5;
            font-size: 10.8pt;
        }
        
        .feeding-item {
            display: block;
        }
        
        .feeding-pool-name {
            font-weight: 600;
        }
        
        .feeding-amount {
            color: #000;
            font-weight: bold;
        }
        
        .feeding-details {
            font-size: 10.8pt;
            color: #666;
            margin-left: 8px;
            font-style: normal;
        }
        
        .unified-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10.2pt;
            page-break-inside: avoid;
        }
        
        .unified-table th {
            background-color: #333;
            color: #fff;
            font-weight: bold;
            font-size: 10.8pt;
            padding: 12px 5px;
            text-align: center;
            border: 0.5px solid #999;
            vertical-align: middle;
        }
        
        .unified-table .pool-header {
            font-family: 'Bitter', serif;
            font-size: 16pt;
            font-weight: bold;
        }
        
        .unified-table .time-column {
            background-color: #444;
            color: #fff;
            font-weight: bold;
            text-align: center;
            width: 60px;
            padding: 12px;
            border: 0.5px solid #999;
            vertical-align: middle;
        }
        
        .unified-table .param-column {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: center;
            width: 75px;
            padding: 12px;
            border: 0.5px solid #999;
            vertical-align: middle;
        }
        
        .unified-table .time-cell {
            background-color: #f0f0f0;
            font-weight: 600;
            text-align: center;
            padding: 12px 4px;
            border: 0.5px solid #999;
            vertical-align: middle;
            font-size: 10.2pt;
        }
        
        .unified-table td {
            border: 0.5px solid #999;
            padding: 12px 5px;
            text-align: center;
            vertical-align: middle;
        }
        
        .unified-table .param-label {
            background-color: #f8f9fa;
            font-weight: normal;
            text-align: center;
            padding: 12px 4px;
            font-size: 10.2pt;
        }
        
        .unified-table .param-field {
            min-height: 40px;
            border-bottom: 0.5px solid #999;
            background-color: #fff;
        }
        
        .session-name {
            font-size: 9pt;
            color: #666;
            font-weight: normal;
            display: block;
            margin-top: 2px;
            font-family: 'Roboto', sans-serif;
        }
        
        .print-controls {
            text-align: center;
            margin-bottom: 10px;
            padding: 8px;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">Печать</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; margin-left: 10px;">Закрыть</button>
    </div>
    
    <div class="print-content">
        <div class="print-header">
            <div class="print-logo">
                <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="Логотип">
            </div>
            <div class="print-title">
                <h1>Протокол дежурства за <?php echo h($formattedDate); ?></h1>
                <div class="subtitle">Дежурный: <?php echo h($dutyName); ?></div>
            </div>
            <div class="print-signatures">
                <div class="signature-lines">
                    <div class="signature-line">
                        Смену сдал: <strong><?php echo h($prevDutyName); ?></strong> <span class="signature-field"></span>
                    </div>
                    <div class="signature-line">
                        Смену принял: <strong><?php echo h($dutyName); ?></strong> <span class="signature-field"></span>
                    </div>
                    <div class="signature-line">
                        Старший рыбовод: <strong><?php echo h($currentUserName); ?></strong> <span class="signature-field"></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="print-content-body">
            <div class="print-left">
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>Прибор учета</th>
                            <th>Показание</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($meters as $meter): ?>
                        <tr>
                            <td><?php echo h($meter['name'] ?? 'Прибор ' . $meter['id']); ?></td>
                            <td class="print-field"></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td>Температура Воздуха в цехе</td>
                            <td class="print-field"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="print-right">
                <div class="feeding-info">
                    <h4>Нормы кормления</h4>
                    <?php if (empty($poolsWithFeeding)): ?>
                    <p style="margin: 0; color: #999;">Нет активных сессий</p>
                    <?php else: ?>
                    <ul class="feeding-list">
                        <?php foreach ($poolsWithFeeding as $pool): ?>
                        <li>
                            <span class="feeding-pool-name">
                                <?php echo h($pool['pool_name'] ?? 'Бассейн ' . $pool['pool_id']); ?>:
                            </span>
                            <span class="feeding-amount">
                                <?php 
                                if ($pool['feeding_amount'] !== null) {
                                    echo number_format($pool['feeding_amount'], 2, ',', ' ') . ' кг';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </span>
                            <?php if ($pool['feed_name'] || $pool['strategy_label'] || $pool['feeding_coefficient']): ?>
                            <span class="feeding-details">
                                <?php 
                                $details = [];
                                if ($pool['feed_name']) {
                                    $details[] = h($pool['feed_name']);
                                }
                                if ($pool['strategy_label']) {
                                    $details[] = h($pool['strategy_label']);
                                }
                                if ($pool['feeding_coefficient'] !== null) {
                                    $details[] = 'Коэфф.: ' . number_format($pool['feeding_coefficient'], 2, ',', ' ');
                                }
                                if (!empty($details)) {
                                    echo ' (' . implode(', ', $details) . ')';
                                }
                                ?>
                            </span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (empty($poolsWithFeeding)): ?>
        <p style="text-align: center; padding: 20px;">Нет активных сессий</p>
        <?php else: ?>
        <table class="unified-table">
            <thead>
                <tr>
                    <th class="time-column">Время</th>
                    <th class="param-column">Параметр</th>
                    <?php foreach ($poolsWithFeeding as $pool): ?>
                    <th class="pool-header">
                        <?php echo h($pool['pool_name'] ?? 'Бассейн ' . $pool['pool_id']); ?>
                        <?php if (isset($pool['session_name']) && $pool['session_name']): ?>
                        <span class="session-name"><?php echo h($pool['session_name']); ?></span>
                        <?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <!-- Утро -->
                <tr>
                    <td class="time-cell" rowspan="2">Утро</td>
                    <td class="param-label">t, °C</td>
                    <?php foreach ($poolsWithFeeding as $pool): ?>
                    <td class="param-field"></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="param-label">O₂, мг/л</td>
                    <?php foreach ($poolsWithFeeding as $pool): ?>
                    <td class="param-field"></td>
                    <?php endforeach; ?>
                </tr>
                
                <!-- День -->
                <tr>
                    <td class="time-cell" rowspan="2">День</td>
                    <td class="param-label">t, °C</td>
                    <?php foreach ($poolsWithFeeding as $pool): ?>
                    <td class="param-field"></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="param-label">O₂, мг/л</td>
                    <?php foreach ($poolsWithFeeding as $pool): ?>
                    <td class="param-field"></td>
                    <?php endforeach; ?>
                </tr>
                
                <!-- Вечер -->
                <tr>
                    <td class="time-cell" rowspan="2">Вечер</td>
                    <td class="param-label">t, °C</td>
                    <?php foreach ($poolsWithFeeding as $pool): ?>
                    <td class="param-field"></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="param-label">O₂, мг/л</td>
                    <?php foreach ($poolsWithFeeding as $pool): ?>
                    <td class="param-field"></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

<script>
// Автоматическая печать при загрузке страницы, если передан параметр autoPrint
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('autoPrint') === '1') {
        // Небольшая задержка для полной загрузки страницы
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 250);
        });
        
        // Дополнительная проверка, если событие load уже произошло
        if (document.readyState === 'complete') {
            setTimeout(function() {
                window.print();
            }, 250);
        }
    }
})();
</script>
</body>
</html>
