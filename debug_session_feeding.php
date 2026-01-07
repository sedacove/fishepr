<?php
/**
 * Пошаговая отладка расчета коэффициента кормления для сессии ID=9
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/Support/FeedTableParser.php';
require_once __DIR__ . '/app/Services/SessionDetailsService.php';
require_once __DIR__ . '/app/Services/WorkService.php';

$pdo = getDBConnection();
$sessionId = 9;

echo "═══════════════════════════════════════════════════════════════\n";
echo "  ПОШАГОВЫЙ РАСЧЕТ КОЭФФИЦИЕНТА КОРМЛЕНИЯ ДЛЯ СЕССИИ #{$sessionId}\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// ============================================================
// ШАГ 1: Получение данных сессии
// ============================================================
echo "ШАГ 1: Получение данных сессии\n";
echo str_repeat("-", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT s.*, 
           p.name AS pool_name,
           f.name AS feed_name,
           f.formula_normal
    FROM sessions s
    LEFT JOIN pools p ON p.id = s.pool_id
    LEFT JOIN feeds f ON f.id = s.feed_id
    WHERE s.id = ?
");
$stmt->execute([$sessionId]);
$sessionData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sessionData) {
    die("Сессия #{$sessionId} не найдена\n");
}

echo "Сессия ID: {$sessionData['id']}\n";
echo "Название: {$sessionData['name']}\n";
echo "Бассейн: {$sessionData['pool_name']} (ID: {$sessionData['pool_id']})\n";
echo "Корм: {$sessionData['feed_name']} (ID: {$sessionData['feed_id']})\n";
echo "Стратегия кормления: {$sessionData['feeding_strategy']}\n";
echo "Кормлений в день: {$sessionData['daily_feedings']}\n";
echo "Начальная масса: {$sessionData['start_mass']} кг\n";
echo "Начальное количество: {$sessionData['start_fish_count']} шт\n";
$startAvgKg = $sessionData['start_mass'] / $sessionData['start_fish_count'];
echo "Начальный средний вес: " . round($startAvgKg, 3) . " кг (" . round($startAvgKg * 1000, 1) . " г)\n";
echo "\n";

$poolId = (int)$sessionData['pool_id'];

// ============================================================
// ШАГ 2: Получение последнего измерения температуры
// ============================================================
echo "ШАГ 2: Получение последнего измерения температуры\n";
echo str_repeat("-", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT temperature, measured_at
    FROM measurements
    WHERE pool_id = ?
    ORDER BY measured_at DESC
    LIMIT 1
");
$stmt->execute([$poolId]);
$measurement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$measurement) {
    die("Измерения температуры для бассейна #{$poolId} не найдены\n");
}

$temperature = (float)$measurement['temperature'];
echo "Температура: {$temperature}°C\n";
echo "Дата измерения: {$measurement['measured_at']}\n";
echo "\n";

// ============================================================
// ШАГ 3: Определение среднего веса рыбы
// ============================================================
echo "ШАГ 3: Определение среднего веса рыбы\n";
echo str_repeat("-", 60) . "\n";

// 3.1: Ищем навески с session_id текущей сессии
echo "3.1: Поиск навесок с session_id = {$sessionId}\n";
$stmt = $pdo->prepare("
    SELECT id, weight, fish_count, recorded_at,
           (weight/fish_count) AS avg_weight_kg,
           (weight/fish_count)*1000 AS avg_weight_g
    FROM weighings
    WHERE session_id = ?
    ORDER BY recorded_at DESC
    LIMIT 1
");
$stmt->execute([$sessionId]);
$weighingBySession = $stmt->fetch(PDO::FETCH_ASSOC);

if ($weighingBySession) {
    echo "  ✓ Найдена навеска с session_id:\n";
    echo "    ID: {$weighingBySession['id']}\n";
    echo "    Вес: {$weighingBySession['weight']} кг\n";
    echo "    Количество: {$weighingBySession['fish_count']} шт\n";
    echo "    Средний вес: " . round($weighingBySession['avg_weight_kg'], 3) . " кг (" . round($weighingBySession['avg_weight_g'], 1) . " г)\n";
    echo "    Дата: {$weighingBySession['recorded_at']}\n";
    $avgWeightKg = (float)$weighingBySession['avg_weight_kg'];
    $avgWeightSource = 'weighing (session_id)';
} else {
    echo "  ✗ Навесок с session_id не найдено\n";
    
    // 3.2: Используем начальные данные сессии
    echo "\n3.2: Использование начальных данных сессии\n";
    $avgWeightKg = $startAvgKg;
    $avgWeightSource = 'session (start data)';
}

$avgWeightGrams = $avgWeightKg * 1000;
echo "\nИТОГ: Средний вес рыбы = " . round($avgWeightKg, 3) . " кг (" . round($avgWeightGrams, 1) . " г)\n";
echo "Источник: {$avgWeightSource}\n";
echo "\n";

// ============================================================
// ШАГ 4: Расчет текущей биомассы
// ============================================================
echo "ШАГ 4: Расчет текущей биомассы\n";
echo str_repeat("-", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(h.weight), 0) AS harvested_weight,
        COALESCE(SUM(h.fish_count), 0) AS harvested_count,
        COALESCE(SUM(m.weight), 0) AS mortality_weight,
        COALESCE(SUM(m.fish_count), 0) AS mortality_count
    FROM sessions s
    LEFT JOIN harvests h ON h.session_id = s.id
    LEFT JOIN mortality m ON m.session_id = s.id
    WHERE s.id = ?
    GROUP BY s.id
");
$stmt->execute([$sessionId]);
$totals = $stmt->fetch(PDO::FETCH_ASSOC);

$currentWeight = max(0, $sessionData['start_mass'] - $totals['harvested_weight'] - $totals['mortality_weight']);
$currentCount = max(0, $sessionData['start_fish_count'] - $totals['harvested_count'] - $totals['mortality_count']);

echo "Начальная масса: {$sessionData['start_mass']} кг\n";
echo "Отобрано: {$totals['harvested_weight']} кг\n";
echo "Падеж: {$totals['mortality_weight']} кг\n";
echo "Текущая биомасса: {$currentWeight} кг\n";
echo "Текущее количество: {$currentCount} шт\n";
echo "\n";

// ============================================================
// ШАГ 5: Парсинг таблицы кормления
// ============================================================
echo "ШАГ 5: Парсинг таблицы кормления\n";
echo str_repeat("-", 60) . "\n";

if (!$sessionData['formula_normal']) {
    die("Таблица кормления (formula_normal) не заполнена для корма ID {$sessionData['feed_id']}\n");
}

echo "Исходная таблица (YAML):\n";
echo substr($sessionData['formula_normal'], 0, 200) . "...\n\n";

$table = \App\Support\FeedTableParser::parse($sessionData['formula_normal']);

echo "Распарсенная таблица:\n";
echo "  Единица измерения: {$table['unit']}\n";
echo "  Температуры: " . implode(', ', $table['temperatures']) . "°C\n";
echo "  Диапазоны веса:\n";
foreach ($table['weight_ranges'] as $range) {
    $min = $range['min'] ?? 0;
    $max = $range['max'] ?? '∞';
    echo "    - {$range['label']}: {$min}-{$max} г\n";
}
echo "\n";

// ============================================================
// ШАГ 6: Поиск подходящего диапазона веса
// ============================================================
echo "ШАГ 6: Поиск подходящего диапазона веса\n";
echo str_repeat("-", 60) . "\n";

echo "Вес для поиска: " . round($avgWeightGrams, 1) . " г\n\n";

$selectedRange = null;
foreach ($table['weight_ranges'] as $range) {
    $min = $range['min'] ?? 0;
    $max = $range['max'] ?? null;
    $matches = $avgWeightGrams >= $min && ($max === null || $avgWeightGrams < $max);
    
    $status = $matches ? "✓ ВЫБРАН" : " ";
    echo "{$status} {$range['label']}: {$min}-" . ($max ?? '∞') . " г";
    
    if ($matches && !$selectedRange) {
        $selectedRange = $range;
        echo " ← ИСПОЛЬЗУЕТСЯ";
    }
    echo "\n";
}

if (!$selectedRange) {
    die("Диапазон веса не найден для веса " . round($avgWeightGrams, 1) . " г\n");
}

echo "\nВыбранный диапазон: {$selectedRange['label']}\n";
echo "Min: {$selectedRange['min']} г, Max: " . ($selectedRange['max'] ?? '∞') . " г\n";
echo "\n";

// ============================================================
// ШАГ 7: Поиск диапазона температуры
// ============================================================
echo "ШАГ 7: Поиск диапазона температуры\n";
echo str_repeat("-", 60) . "\n";

echo "Температура для поиска: {$temperature}°C\n\n";

$sortedTemps = $table['temperatures'];
sort($sortedTemps, SORT_NUMERIC);

$tempLower = null;
$tempUpper = null;
$coeffLower = null;
$coeffUpper = null;

// Проверяем граничные случаи
if ($temperature <= $sortedTemps[0]) {
    $tempLower = $sortedTemps[0];
    $tempUpper = $sortedTemps[0];
    $tempKey = (string)$tempLower;
    if (isset($table['values'][$tempKey][$selectedRange['label']])) {
        $coeffLower = (float)$table['values'][$tempKey][$selectedRange['label']];
        $coeffUpper = $coeffLower;
        echo "Температура ≤ минимальной ({$sortedTemps[0]}°C), используем минимальную\n";
    }
} elseif ($temperature >= $sortedTemps[count($sortedTemps) - 1]) {
    $tempUpper = $sortedTemps[count($sortedTemps) - 1];
    $tempLower = $tempUpper;
    $tempKey = (string)$tempUpper;
    if (isset($table['values'][$tempKey][$selectedRange['label']])) {
        $coeffUpper = (float)$table['values'][$tempKey][$selectedRange['label']];
        $coeffLower = $coeffUpper;
        echo "Температура ≥ максимальной ({$tempUpper}°C), используем максимальную\n";
    }
} else {
    // Ищем между двумя значениями
    for ($i = 0; $i < count($sortedTemps) - 1; $i++) {
        if ($temperature >= $sortedTemps[$i] && $temperature <= $sortedTemps[$i + 1]) {
            $tempLower = $sortedTemps[$i];
            $tempUpper = $sortedTemps[$i + 1];
            break;
        }
    }
    
    if ($tempLower !== null && $tempUpper !== null) {
        $tempLowerKey = (string)$tempLower;
        $tempUpperKey = (string)$tempUpper;
        
        if (isset($table['values'][$tempLowerKey][$selectedRange['label']]) && 
            isset($table['values'][$tempUpperKey][$selectedRange['label']])) {
            $coeffLower = (float)$table['values'][$tempLowerKey][$selectedRange['label']];
            $coeffUpper = (float)$table['values'][$tempUpperKey][$selectedRange['label']];
            echo "Температура между {$tempLower}°C и {$tempUpper}°C\n";
        }
    }
}

if ($tempLower === null || $coeffLower === null) {
    die("Не удалось найти значения для температуры {$temperature}°C и диапазона '{$selectedRange['label']}'\n");
}

echo "\nНайденные значения:\n";
echo "  Температура нижняя: {$tempLower}°C → коэффициент: {$coeffLower}\n";
echo "  Температура верхняя: {$tempUpper}°C → коэффициент: {$coeffUpper}\n";
echo "\n";

// ============================================================
// ШАГ 8: Расчет коэффициента с учетом стратегии
// ============================================================
echo "ШАГ 8: Расчет коэффициента с учетом стратегии\n";
echo str_repeat("-", 60) . "\n";

$strategy = $sessionData['feeding_strategy'];
echo "Стратегия: {$strategy}\n\n";

$finalCoeff = null;
if ($strategy === 'econom') {
    echo "Стратегия 'Эконом': выбираем МЕНЬШИЙ коэффициент\n";
    $finalCoeff = min($coeffLower, $coeffUpper);
    echo "  min({$coeffLower}, {$coeffUpper}) = {$finalCoeff}\n";
} elseif ($strategy === 'growth') {
    echo "Стратегия 'Рост': выбираем БОЛЬШИЙ коэффициент\n";
    $finalCoeff = max($coeffLower, $coeffUpper);
    echo "  max({$coeffLower}, {$coeffUpper}) = {$finalCoeff}\n";
} else {
    echo "Стратегия 'Норма': линейная интерполяция\n";
    if ($tempLower === $tempUpper) {
        $finalCoeff = $coeffLower;
        echo "  Температуры совпадают, используем: {$finalCoeff}\n";
    } else {
        $ratio = ($temperature - $tempLower) / ($tempUpper - $tempLower);
        $finalCoeff = $coeffLower + ($coeffUpper - $coeffLower) * $ratio;
        echo "  Формула: coeff = coeffLower + (coeffUpper - coeffLower) * (temp - tempLower) / (tempUpper - tempLower)\n";
        echo "  Подстановка: {$coeffLower} + ({$coeffUpper} - {$coeffLower}) * ({$temperature} - {$tempLower}) / ({$tempUpper} - {$tempLower})\n";
        echo "  Расчет: {$coeffLower} + " . ($coeffUpper - $coeffLower) . " * " . round($ratio, 4) . "\n";
        echo "  Результат: {$finalCoeff}\n";
    }
}

echo "\nИТОГОВЫЙ КОЭФФИЦИЕНТ: {$finalCoeff}\n";
echo "Округленный до 2 знаков: " . round($finalCoeff, 2) . "\n";
echo "\n";

// ============================================================
// ШАГ 9: Расчет количества корма
// ============================================================
echo "ШАГ 9: Расчет количества корма\n";
echo str_repeat("-", 60) . "\n";

$ratioPer100Kg = max(0, (float)$finalCoeff);
$recommendedPerDay = max(0, ($ratioPer100Kg / 100) * $currentWeight);
$perFeeding = max(0, $recommendedPerDay / (int)$sessionData['daily_feedings']);

echo "Коэффициент на 100 кг биомассы: {$ratioPer100Kg}\n";
echo "Текущая биомасса: {$currentWeight} кг\n";
echo "Рекомендуемое в сутки: ({$ratioPer100Kg} / 100) * {$currentWeight} = {$recommendedPerDay} кг\n";
echo "Кормлений в день: {$sessionData['daily_feedings']}\n";
echo "На одно кормление: {$recommendedPerDay} / {$sessionData['daily_feedings']} = " . round($perFeeding, 2) . " кг\n";
echo "\n";

// ============================================================
// СРАВНЕНИЕ С WorkService
// ============================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "  СРАВНЕНИЕ С WorkService\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Проверяем, что вернет WorkService для этой сессии...\n\n";

$workService = new \App\Services\WorkService($pdo);
$pools = $workService->getPools();

// Ищем сессию в результатах WorkService
$found = false;
foreach ($pools as $pool) {
    if (isset($pool['active_session']) && $pool['active_session']['id'] == $sessionId) {
        $found = true;
        echo "Найдена сессия в WorkService:\n";
        echo "  Бассейн: {$pool['name']} (ID: {$pool['id']})\n";
        if (isset($pool['active_session']['feeding_plan'])) {
            $plan = $pool['active_session']['feeding_plan'];
            echo "  Коэффициент из WorkService: " . ($plan['ratio_per_100kg'] ?? 'N/A') . "\n";
            echo "  Вес из WorkService: " . ($plan['avg_weight_g'] ?? 'N/A') . " г\n";
            echo "  Температура из WorkService: " . ($plan['temperature'] ?? 'N/A') . "°C\n";
            echo "  Диапазон веса: " . ($plan['weight_label'] ?? 'N/A') . "\n";
        } else {
            echo "  План кормления не рассчитан\n";
        }
        break;
    }
}

if (!$found) {
    echo "Сессия не найдена в результатах WorkService (возможно, не активна)\n";
}

echo "\n";

// ============================================================
// ИТОГ
// ============================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "  ИТОГОВЫЙ РЕЗУЛЬТАТ\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "Коэффициент кормления: " . round($ratioPer100Kg, 2) . "\n";
echo "Количество на одно кормление: " . round($perFeeding, 2) . " кг\n";
echo "\n";

