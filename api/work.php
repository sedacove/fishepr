<?php
/**
 * API для получения данных для рабочей страницы
 * Возвращает бассейны с их активными сессиями
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

// Требуем авторизацию
requireAuth();

try {
    $pdo = getDBConnection();
    
    // Загружаем настройки для определения страт
    $tempSettings = [
        'bad_below' => (float)getSetting('temp_bad_below', 10),
        'acceptable_min' => (float)getSetting('temp_acceptable_min', 10),
        'good_min' => (float)getSetting('temp_good_min', 14),
        'good_max' => (float)getSetting('temp_good_max', 17),
        'acceptable_max' => (float)getSetting('temp_acceptable_max', 20),
        'bad_above' => (float)getSetting('temp_bad_above', 20)
    ];
    
    $oxygenSettings = [
        'bad_below' => (float)getSetting('oxygen_bad_below', 8),
        'acceptable_min' => (float)getSetting('oxygen_acceptable_min', 8),
        'good_min' => (float)getSetting('oxygen_good_min', 11),
        'good_max' => (float)getSetting('oxygen_good_max', 16),
        'acceptable_max' => (float)getSetting('oxygen_acceptable_max', 20),
        'bad_above' => (float)getSetting('oxygen_bad_above', 20)
    ];
    
    // Функция для определения страты значения
    function getValueStratum($value, $settings) {
        if ($value < $settings['bad_below'] || $value > $settings['bad_above']) {
            return 'bad'; // Плохо - красный
        } elseif (($value >= $settings['acceptable_min'] && $value < $settings['good_min']) || 
                  ($value > $settings['good_max'] && $value <= $settings['acceptable_max'])) {
            return 'acceptable'; // Допустимо - желтый
        } elseif ($value >= $settings['good_min'] && $value <= $settings['good_max']) {
            return 'good'; // Хорошо - зеленый
        }
        return 'bad'; // По умолчанию плохо
    }
    
    // Функция для определения направления динамики (улучшается или ухудшается)
    function getTrendDirection($currentValue, $previousValue, $settings) {
        if ($previousValue === null) {
            return null; // Нет данных для сравнения
        }
        
        $currentStratum = getValueStratum($currentValue, $settings);
        $previousStratum = getValueStratum($previousValue, $settings);
        
        // Определяем, улучшается ли значение (движется к хорошему диапазону)
        $goodCenter = ($settings['good_min'] + $settings['good_max']) / 2;
        $currentDistance = abs($currentValue - $goodCenter);
        $previousDistance = abs($previousValue - $goodCenter);
        
        if ($currentDistance < $previousDistance) {
            return 'improving'; // Улучшается - зеленый
        } elseif ($currentDistance > $previousDistance) {
            return 'worsening'; // Ухудшается - красный
        } else {
            // Если расстояние одинаковое, проверяем страту
            if ($currentStratum === 'good' && $previousStratum !== 'good') {
                return 'improving';
            } elseif ($currentStratum !== 'good' && $previousStratum === 'good') {
                return 'worsening';
            }
            return null; // Без изменений
        }
    }
    
    function formatPlural($number, $forms) {
        $number = abs($number) % 100;
        $n1 = $number % 10;
        if ($number > 10 && $number < 20) {
            return $forms[2];
        }
        if ($n1 > 1 && $n1 < 5) {
            return $forms[1];
        }
        if ($n1 == 1) {
            return $forms[0];
        }
        return $forms[2];
    }
    
    function formatDiffLabel($minutes) {
        if ($minutes === null) {
            return 'ещё не проводился';
        }
        if ($minutes <= 1) {
            return 'менее минуты';
        }
        
        $minutes = (int)$minutes;
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;
        $parts = [];
        
        if ($days > 0) {
            $parts[] = $days . ' ' . formatPlural($days, ['день', 'дня', 'дней']);
        }
        if ($hours > 0) {
            $parts[] = $hours . ' ' . formatPlural($hours, ['час', 'часа', 'часов']);
        }
        if ($mins > 0 && $days === 0) {
            $parts[] = $mins . ' ' . formatPlural($mins, ['минута', 'минуты', 'минут']);
        }
        
        if (empty($parts)) {
            $parts[] = 'менее минуты';
        }
        
        return implode(' ', $parts);
    }
    
    // Получаем все активные бассейны
    $stmt = $pdo->query("
        SELECT 
            p.*,
            u.login as created_by_login,
            u.full_name as created_by_name
        FROM pools p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.is_active = 1
        ORDER BY p.sort_order ASC, p.name ASC
    ");
    $pools = $stmt->fetchAll();
    
    // Для каждого бассейна получаем активную сессию
    foreach ($pools as &$pool) {
        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                pl.name as planting_name,
                pl.fish_breed as planting_fish_breed
            FROM sessions s
            LEFT JOIN plantings pl ON s.planting_id = pl.id
            WHERE s.pool_id = ? AND s.is_completed = 0
            ORDER BY s.start_date DESC
            LIMIT 1
        ");
        $stmt->execute([$pool['id']]);
        $session = $stmt->fetch();
        
        if ($session) {
            // Получаем последнюю навеску для этой сессии (после начала сессии)
            $stmt = $pdo->prepare("
                SELECT 
                    weight,
                    fish_count,
                    recorded_at
                FROM weighings
                WHERE pool_id = ? AND recorded_at >= ?
                ORDER BY recorded_at DESC
                LIMIT 1
            ");
            $stmt->execute([$pool['id'], $session['start_date']]);
            $lastWeighing = $stmt->fetch();
            
            // Рассчитываем средний вес особи
            if ($lastWeighing && $lastWeighing['fish_count'] > 0) {
                // Используем последнюю навеску
                $session['avg_fish_weight'] = $lastWeighing['weight'] / $lastWeighing['fish_count'];
                $session['avg_weight_source'] = 'weighing';
            } elseif ($session['start_fish_count'] > 0) {
                // Используем начальные значения сессии
                $session['avg_fish_weight'] = $session['start_mass'] / $session['start_fish_count'];
                $session['avg_weight_source'] = 'session';
            } else {
                $session['avg_fish_weight'] = null;
                $session['avg_weight_source'] = null;
            }
            
            // Получаем последний и предпоследний замеры для этого бассейна
            $stmt = $pdo->prepare("
                SELECT 
                    temperature,
                    oxygen,
                    measured_at
                FROM measurements
                WHERE pool_id = ?
                ORDER BY measured_at DESC
                LIMIT 2
            ");
            $stmt->execute([$pool['id']]);
            $measurements = $stmt->fetchAll();
            
            if (count($measurements) > 0) {
                $lastTemp = (float)$measurements[0]['temperature'];
                $lastOxygen = (float)$measurements[0]['oxygen'];
                
                $session['last_measurement_at'] = $measurements[0]['measured_at'];
                
                 // Расчёт времени с момента последнего замера
                $lastMeasurementDateTime = new DateTime($measurements[0]['measured_at']);
                $now = new DateTime();
                $diffMinutes = max(0, (int)floor(($now->getTimestamp() - $lastMeasurementDateTime->getTimestamp()) / 60));
                $session['last_measurement_diff_minutes'] = $diffMinutes;
                $session['last_measurement_diff_label'] = formatDiffLabel($diffMinutes);
                
                $session['last_measurement'] = [
                    'temperature' => $lastTemp,
                    'oxygen' => $lastOxygen,
                    'temperature_stratum' => getValueStratum($lastTemp, $tempSettings),
                    'oxygen_stratum' => getValueStratum($lastOxygen, $oxygenSettings)
                ];
                
                // Предпоследний замер для определения динамики
                if (count($measurements) > 1) {
                    $prevTemp = (float)$measurements[1]['temperature'];
                    $prevOxygen = (float)$measurements[1]['oxygen'];
                    
                    $session['previous_measurement'] = [
                        'temperature' => $prevTemp,
                        'oxygen' => $prevOxygen
                    ];
                    
                    // Определяем динамику
                    $tempTrend = getTrendDirection($lastTemp, $prevTemp, $tempSettings);
                    $oxygenTrend = getTrendDirection($lastOxygen, $prevOxygen, $oxygenSettings);
                    
                    $session['last_measurement']['temperature_trend'] = $lastTemp > $prevTemp ? 'up' : ($lastTemp < $prevTemp ? 'down' : 'same');
                    $session['last_measurement']['temperature_trend_direction'] = $tempTrend;
                    $session['last_measurement']['oxygen_trend'] = $lastOxygen > $prevOxygen ? 'up' : ($lastOxygen < $prevOxygen ? 'down' : 'same');
                    $session['last_measurement']['oxygen_trend_direction'] = $oxygenTrend;
                } else {
                    $session['previous_measurement'] = null;
                    $session['last_measurement']['temperature_trend'] = null;
                    $session['last_measurement']['temperature_trend_direction'] = null;
                    $session['last_measurement']['oxygen_trend'] = null;
                    $session['last_measurement']['oxygen_trend_direction'] = null;
                }
            } else {
                $session['last_measurement_at'] = null;
                $session['last_measurement'] = null;
                $session['previous_measurement'] = null;
                $session['last_measurement_diff_minutes'] = null;
                $session['last_measurement_diff_label'] = formatDiffLabel(null);
            }
            
            // Получаем падеж за последние N часов (в штуках)
            $mortalityHours = (int)getSetting('mortality_calculation_hours', 24);
            $mortalityThresholdGreen = (int)getSetting('mortality_threshold_green', 5);
            $mortalityThresholdYellow = (int)getSetting('mortality_threshold_yellow', 10);
            
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(fish_count), 0) as total_count
                FROM mortality
                WHERE pool_id = ? 
                AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ");
            $stmt->execute([$pool['id'], $mortalityHours]);
            $mortalityResult = $stmt->fetch();
            $totalCount = (int)$mortalityResult['total_count'];
            
            // Определяем цвет на основе пороговых значений
            $mortalityColorClass = 'text-danger'; // Красный по умолчанию
            if ($totalCount <= $mortalityThresholdGreen) {
                $mortalityColorClass = 'text-success'; // Зеленый
            } elseif ($totalCount <= $mortalityThresholdYellow) {
                $mortalityColorClass = 'text-warning'; // Желтый
            }
            
            $session['mortality_last_hours'] = [
                'hours' => $mortalityHours,
                'total_count' => $totalCount,
                'color_class' => $mortalityColorClass
            ];
        }
        
        $pool['active_session'] = $session ?: null;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $pools
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
