<?php
/**
 * Скрипт миграции данных: определение session_id для записей weighings
 * 
 * Этот скрипт определяет для каждой навески соответствующую сессию
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
 * php migrate_weighings_to_sessions.php
 * 
 * ВАЖНО: Перед запуском убедитесь, что:
 * 1. Выполнена миграция 044_add_session_id_to_weighings.sql
 * 2. Сделана резервная копия базы данных
 * 3. Скрипт запущен на той же БД, где была выполнена миграция структуры
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDBConnection();

if (!$pdo) {
    die("Ошибка подключения к базе данных\n");
}

echo "Начало миграции данных: определение session_id для weighings\n";
echo "=======================================================================\n\n";

// Проверяем наличие колонки session_id
$checkWeighings = $pdo->query("SHOW COLUMNS FROM weighings LIKE 'session_id'");

if ($checkWeighings->rowCount() === 0) {
    die("ОШИБКА: Колонка session_id не найдена в таблице weighings.\n" .
        "Сначала выполните миграцию 044_add_session_id_to_weighings.sql (шаг 1).\n");
}

$pdo->beginTransaction();

try {
    // Миграция weighings
    echo "Обработка таблицы weighings...\n";
    
    $weighings = $pdo->query("
        SELECT w.id, w.pool_id, w.recorded_at
        FROM weighings w
        WHERE w.session_id IS NULL
        ORDER BY w.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $weighingsCount = count($weighings);
    echo "Найдено записей для обработки: {$weighingsCount}\n";
    
    $weighingsUpdated = 0;
    $weighingsSkipped = 0;
    
    $stmtUpdate = $pdo->prepare("UPDATE weighings SET session_id = ? WHERE id = ?");
    $stmtFindSession = $pdo->prepare("
        SELECT id
        FROM sessions
        WHERE pool_id = ?
          AND start_date <= DATE(?)
          AND (end_date IS NULL OR end_date >= DATE(?))
        ORDER BY start_date DESC
        LIMIT 1
    ");
    
    foreach ($weighings as $weighing) {
        $recordedAt = $weighing['recorded_at'];
        $poolId = $weighing['pool_id'];
        
        // Ищем сессию для этой записи
        $stmtFindSession->execute([$poolId, $recordedAt, $recordedAt]);
        $session = $stmtFindSession->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            $stmtUpdate->execute([$session['id'], $weighing['id']]);
            $weighingsUpdated++;
            
            if ($weighingsUpdated % 100 === 0) {
                echo "  Обработано: {$weighingsUpdated} / {$weighingsCount}\n";
            }
        } else {
            echo "  ПРЕДУПРЕЖДЕНИЕ: Не найдена сессия для weighing id={$weighing['id']}, pool_id={$poolId}, recorded_at={$recordedAt}\n";
            $weighingsSkipped++;
        }
    }
    
    echo "Weighings: обновлено {$weighingsUpdated}, пропущено {$weighingsSkipped}\n\n";
    
    // Проверяем, что все записи получили session_id
    $weighingsWithoutSession = $pdo->query("SELECT COUNT(*) FROM weighings WHERE session_id IS NULL")->fetchColumn();
    
    if ($weighingsWithoutSession > 0) {
        echo "ВНИМАНИЕ: Остались записи без session_id: {$weighingsWithoutSession}\n";
        
        // В неинтерактивном режиме (например, в Docker) автоматически подтверждаем
        if (php_sapi_name() === 'cli' && !stream_isatty(STDIN)) {
            echo "Неинтерактивный режим: автоматически подтверждаем коммит\n";
        } else {
            echo "\nПродолжить коммит транзакции? (yes/no): ";
            
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($line) !== 'yes') {
                throw new Exception("Миграция отменена пользователем");
            }
        }
    }
    
    $pdo->commit();
    
    echo "\n=======================================================================\n";
    echo "Миграция данных завершена успешно!\n";
    echo "\nОпционально: можно сделать session_id обязательным (NOT NULL):\n";
    echo "ALTER TABLE weighings MODIFY COLUMN session_id INT(11) UNSIGNED NOT NULL;\n";
    echo "\nВАЖНО: pool_id остается в таблице для обратной совместимости.\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\nОШИБКА: " . $e->getMessage() . "\n";
    echo "Транзакция отменена. Данные не изменены.\n";
    exit(1);
}

