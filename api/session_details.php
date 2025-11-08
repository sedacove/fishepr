<?php
/**
 * API для получения детальной информации о сессии
 * Возвращает данные сессии и данные для графиков
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Требуем авторизацию
requireAuth();

try {
    $pdo = getDBConnection();
    
    $sessionId = $_GET['id'] ?? null;
    
    if (!$sessionId) {
        throw new Exception('ID сессии не указан');
    }
    
    // Проверяем, что ID - число
    $sessionId = (int)$sessionId;
    if ($sessionId <= 0) {
        throw new Exception('Неверный ID сессии');
    }
    
    // Получаем информацию о сессии
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            p.name as pool_name,
            pl.name as planting_name,
            pl.fish_breed,
            pl.hatch_date,
            pl.planting_date as planting_planting_date,
            pl.fish_count as planting_quantity,
            pl.biomass_weight as planting_biomass_weight,
            pl.supplier,
            pl.price as planting_price,
            pl.delivery_cost
        FROM sessions s
        LEFT JOIN pools p ON s.pool_id = p.id
        LEFT JOIN plantings pl ON s.planting_id = pl.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception('Сессия не найдена');
    }
    
    // Получаем данные для графиков
    // Температура и O2
    $stmt = $pdo->prepare("
        SELECT 
            measured_at,
            temperature,
            oxygen,
            created_by
        FROM measurements
        WHERE pool_id = ? 
        AND measured_at >= ?
        ORDER BY measured_at ASC
    ");
    $stmt->execute([$session['pool_id'], $session['start_date']]);
    $measurements = $stmt->fetchAll();
    
    // Падеж
    $stmt = $pdo->prepare("
        SELECT 
            recorded_at,
            weight,
            fish_count
        FROM mortality
        WHERE pool_id = ? 
        AND recorded_at >= ?
        ORDER BY recorded_at ASC
    ");
    $stmt->execute([$session['pool_id'], $session['start_date']]);
    $mortality = $stmt->fetchAll();
    
    // Отборы
    $stmt = $pdo->prepare("
        SELECT 
            recorded_at,
            weight,
            fish_count
        FROM harvests
        WHERE pool_id = ? 
        AND recorded_at >= ?
        ORDER BY recorded_at ASC
    ");
    $stmt->execute([$session['pool_id'], $session['start_date']]);
    $harvests = $stmt->fetchAll();
    
    // Навески
    $stmt = $pdo->prepare("
        SELECT 
            recorded_at,
            weight,
            fish_count
        FROM weighings
        WHERE pool_id = ? 
        AND recorded_at >= ?
        ORDER BY recorded_at ASC
    ");
    $stmt->execute([$session['pool_id'], $session['start_date']]);
    $weighings = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'session' => $session,
            'measurements' => $measurements,
            'mortality' => $mortality,
            'harvests' => $harvests,
            'weighings' => $weighings
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Session Details API PDO Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage(),
        'debug' => $e->getTraceAsString()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    error_log("Session Details API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $e->getTraceAsString()
    ]);
}

