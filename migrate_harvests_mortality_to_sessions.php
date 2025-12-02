<?php
/**
 * Скрипт миграции данных: определение session_id для записей harvests и mortality
 * 
 * Этот скрипт определяет для каждой записи отборов и падежей соответствующую сессию
 * на основе pool_id и времени recorded_at.
 * 
 * Логика определения сессии:
 * - Находим сессию с тем же pool_id
 * - recorded_at >= start_date
 * - recorded_at <= end_date (или end_date IS NULL для активных сессий)
 * 
 * Если для записи не найдена сессия, скрипт выведет предупреждение и пропустит запись.
 * 
 * Использование:
 * php migrate_harvests_mortality_to_sessions.php
 * 
 * ВАЖНО: Перед запуском убедитесь, что:
 * 1. Выполнена миграция 043_add_session_id_columns.sql
 * 2. Сделана резервная копия базы данных
 * 3. Скрипт запущен на той же БД, где была выполнена миграция структуры
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDBConnection();

if (!$pdo) {
    die("Ошибка подключения к базе данных\n");
}

echo "Начало миграции данных: определение session_id для harvests и mortality\n";
echo "=======================================================================\n\n";

// Проверяем наличие колонки session_id
$checkHarvests = $pdo->query("SHOW COLUMNS FROM harvests LIKE 'session_id'");
$checkMortality = $pdo->query("SHOW COLUMNS FROM mortality LIKE 'session_id'");

if ($checkHarvests->rowCount() === 0 || $checkMortality->rowCount() === 0) {
    die("ОШИБКА: Колонка session_id не найдена в таблицах harvests или mortality.\n" .
        "Сначала выполните миграцию 043_change_harvests_mortality_to_sessions.sql (шаги 1-2).\n");
}

$pdo->beginTransaction();

try {
    // Миграция harvests
    echo "Обработка таблицы harvests...\n";
    
    $harvests = $pdo->query("
        SELECT h.id, h.pool_id, h.recorded_at
        FROM harvests h
        WHERE h.session_id IS NULL
        ORDER BY h.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $harvestsCount = count($harvests);
    echo "Найдено записей для обработки: {$harvestsCount}\n";
    
    $harvestsUpdated = 0;
    $harvestsSkipped = 0;
    
    $stmtUpdate = $pdo->prepare("UPDATE harvests SET session_id = ? WHERE id = ?");
    $stmtFindSession = $pdo->prepare("
        SELECT id
        FROM sessions
        WHERE pool_id = ?
          AND start_date <= DATE(?)
          AND (end_date IS NULL OR end_date >= DATE(?))
        ORDER BY start_date DESC
        LIMIT 1
    ");
    
    foreach ($harvests as $harvest) {
        $recordedAt = $harvest['recorded_at'];
        $poolId = $harvest['pool_id'];
        
        // Ищем сессию для этой записи
        $stmtFindSession->execute([$poolId, $recordedAt, $recordedAt]);
        $session = $stmtFindSession->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            $stmtUpdate->execute([$session['id'], $harvest['id']]);
            $harvestsUpdated++;
            
            if ($harvestsUpdated % 100 === 0) {
                echo "  Обработано: {$harvestsUpdated} / {$harvestsCount}\n";
            }
        } else {
            echo "  ПРЕДУПРЕЖДЕНИЕ: Не найдена сессия для harvest id={$harvest['id']}, pool_id={$poolId}, recorded_at={$recordedAt}\n";
            $harvestsSkipped++;
        }
    }
    
    echo "Harvests: обновлено {$harvestsUpdated}, пропущено {$harvestsSkipped}\n\n";
    
    // Миграция mortality
    echo "Обработка таблицы mortality...\n";
    
    $mortality = $pdo->query("
        SELECT m.id, m.pool_id, m.recorded_at
        FROM mortality m
        WHERE m.session_id IS NULL
        ORDER BY m.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $mortalityCount = count($mortality);
    echo "Найдено записей для обработки: {$mortalityCount}\n";
    
    $mortalityUpdated = 0;
    $mortalitySkipped = 0;
    
    $stmtUpdateMortality = $pdo->prepare("UPDATE mortality SET session_id = ? WHERE id = ?");
    
    foreach ($mortality as $mort) {
        $recordedAt = $mort['recorded_at'];
        $poolId = $mort['pool_id'];
        
        // Ищем сессию для этой записи
        $stmtFindSession->execute([$poolId, $recordedAt, $recordedAt]);
        $session = $stmtFindSession->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            $stmtUpdateMortality->execute([$session['id'], $mort['id']]);
            $mortalityUpdated++;
            
            if ($mortalityUpdated % 100 === 0) {
                echo "  Обработано: {$mortalityUpdated} / {$mortalityCount}\n";
            }
        } else {
            echo "  ПРЕДУПРЕЖДЕНИЕ: Не найдена сессия для mortality id={$mort['id']}, pool_id={$poolId}, recorded_at={$recordedAt}\n";
            $mortalitySkipped++;
        }
    }
    
    echo "Mortality: обновлено {$mortalityUpdated}, пропущено {$mortalitySkipped}\n\n";
    
    // Проверяем, что все записи получили session_id
    $harvestsWithoutSession = $pdo->query("SELECT COUNT(*) FROM harvests WHERE session_id IS NULL")->fetchColumn();
    $mortalityWithoutSession = $pdo->query("SELECT COUNT(*) FROM mortality WHERE session_id IS NULL")->fetchColumn();
    
    if ($harvestsWithoutSession > 0 || $mortalityWithoutSession > 0) {
        echo "ВНИМАНИЕ: Остались записи без session_id:\n";
        echo "  Harvests: {$harvestsWithoutSession}\n";
        echo "  Mortality: {$mortalityWithoutSession}\n";
        echo "\nПродолжить коммит транзакции? (yes/no): ";
        
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($line) !== 'yes') {
            throw new Exception("Миграция отменена пользователем");
        }
    }
    
    $pdo->commit();
    
    echo "\n=======================================================================\n";
    echo "Миграция данных завершена успешно!\n";
    echo "\nОпционально: можно сделать session_id обязательным (NOT NULL):\n";
    echo "ALTER TABLE harvests MODIFY COLUMN session_id INT(11) UNSIGNED NOT NULL;\n";
    echo "ALTER TABLE mortality MODIFY COLUMN session_id INT(11) UNSIGNED NOT NULL;\n";
    echo "\nВАЖНО: pool_id остается в таблицах для обратной совместимости.\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\nОШИБКА: " . $e->getMessage() . "\n";
    echo "Транзакция отменена. Данные не изменены.\n";
    exit(1);
}

