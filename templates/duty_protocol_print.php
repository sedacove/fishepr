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
    
    $poolsWithFeeding[] = [
        'pool_id' => $pool['id'],
        'pool_name' => $pool['name'],
        'session_name' => $session['name'] ?? null,
        'feeding_amount' => $feedingAmount
    ];
}

// Получаем список всех приборов учета
$meterService = new \App\Services\MeterReadingService($pdo);
$meters = $meterService->getAllMeters();

// Форматируем дату для отображения
$dateObj = DateTime::createFromFormat('Y-m-d', $date);
$formattedDate = $dateObj ? $dateObj->format('d.m.Y') : $date;

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
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 1cm;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            margin: 0;
            padding: 20px;
        }
        
        .print-content {
            padding: 20px;
        }
        
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .print-logo {
            width: 150px;
        }
        
        .print-logo img {
            max-width: 100%;
            height: auto;
        }
        
        .print-title {
            flex: 1;
            margin-left: 20px;
        }
        
        .print-title h1 {
            font-size: 18pt;
            font-weight: bold;
            margin: 0 0 10px 0;
        }
        
        .print-title .subtitle {
            font-size: 12pt;
            margin: 5px 0;
        }
        
        .print-title .signature-line {
            margin-top: 10px;
            font-size: 11pt;
        }
        
        .print-content-body {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .print-left, .print-right {
            flex: 1;
        }
        
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11pt;
        }
        
        .print-table th,
        .print-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        
        .print-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .print-field {
            min-height: 20px;
            border-bottom: 1px solid #000;
        }
        
        .print-time-block {
            margin-bottom: 20px;
        }
        
        .print-time-block h3 {
            font-size: 14pt;
            font-weight: bold;
            margin: 0 0 10px 0;
        }
        
        .print-time-block .print-table {
            margin-bottom: 10px;
        }
        
        .session-name {
            font-size: 9pt;
            color: #666;
            font-weight: normal;
        }
        
        .print-controls {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
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
                <div class="signature-line">Смену принял ___________</div>
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
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>Бассейн</th>
                            <th>Норма кормления</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($poolsWithFeeding)): ?>
                        <tr>
                            <td colspan="2" style="text-align: center;">Нет активных сессий</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($poolsWithFeeding as $pool): ?>
                        <tr>
                            <td>
                                <?php echo h($pool['pool_name'] ?? 'Бассейн ' . $pool['pool_id']); ?>
                                <?php if ($pool['session_name']): ?>
                                <span class="session-name">(<?php echo h($pool['session_name']); ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($pool['feeding_amount'] !== null) {
                                    echo number_format($pool['feeding_amount'], 2, ',', ' ') . ' кг';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="print-time-block">
            <h3>Утро</h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Бассейн</th>
                        <th>t, °C</th>
                        <th>O₂, мг/л</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($poolsWithFeeding)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">Нет активных сессий</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($poolsWithFeeding as $pool): ?>
                    <tr>
                        <td><?php echo h($pool['pool_name'] ?? 'Бассейн ' . $pool['pool_id']); ?></td>
                        <td class="print-field"></td>
                        <td class="print-field"></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="print-time-block">
            <h3>День</h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Бассейн</th>
                        <th>t, °C</th>
                        <th>O₂, мг/л</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($poolsWithFeeding)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">Нет активных сессий</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($poolsWithFeeding as $pool): ?>
                    <tr>
                        <td><?php echo h($pool['pool_name'] ?? 'Бассейн ' . $pool['pool_id']); ?></td>
                        <td class="print-field"></td>
                        <td class="print-field"></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="print-time-block">
            <h3>Вечер</h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Бассейн</th>
                        <th>t, °C</th>
                        <th>O₂, мг/л</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($poolsWithFeeding)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">Нет активных сессий</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($poolsWithFeeding as $pool): ?>
                    <tr>
                        <td><?php echo h($pool['pool_name'] ?? 'Бассейн ' . $pool['pool_id']); ?></td>
                        <td class="print-field"></td>
                        <td class="print-field"></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
