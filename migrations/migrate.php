<?php
/**
 * Скрипт для запуска миграций базы данных
 * 
 * Использование:
 * php migrations/migrate.php
 */

require_once __DIR__ . '/../config/config.php';

class MigrationRunner {
    private $pdo;
    private $migrationsDir;
    
    public function __construct() {
        try {
            // Подключение к БД без указания конкретной базы для создания таблицы migrations
            $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Создание базы данных, если не существует
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->pdo->exec("USE `" . DB_NAME . "`");
            
            $this->migrationsDir = __DIR__;
        } catch (PDOException $e) {
            die("Ошибка подключения к базе данных: " . $e->getMessage() . "\n");
        }
    }
    
    /**
     * Создание таблицы для отслеживания миграций
     */
    private function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `batch` INT(11) NOT NULL,
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_migration` (`migration`),
            INDEX `idx_batch` (`batch`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Получение списка выполненных миграций
     */
    private function getExecutedMigrations() {
        $stmt = $this->pdo->query("SELECT migration FROM migrations ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Получение списка всех файлов миграций
     */
    private function getMigrationFiles() {
        $files = glob($this->migrationsDir . '/*_*.sql');
        $migrations = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/^(\d+)_(.+)\.sql$/', $filename, $matches)) {
                $migrations[$matches[1]] = [
                    'file' => $file,
                    'name' => $filename,
                    'number' => (int)$matches[1]
                ];
            }
        }
        
        ksort($migrations);
        return $migrations;
    }
    
    /**
     * Выполнение миграции
     */
    private function runMigration($file, $name) {
        echo "Выполнение миграции: {$name}...\n";
        
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new Exception("Не удалось прочитать файл миграции: {$file}");
        }
        
        // Разделение SQL на отдельные запросы
        // Удаляем комментарии из SQL
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Разбиваем по точкам с запятой
        $queries = array_filter(
            array_map('trim', explode(';', $sql)),
            function($query) {
                $query = trim($query);
                return !empty($query) && strlen($query) > 10; // Минимальная длина запроса
            }
        );
        
        $transactionStarted = false;
        if (!$this->pdo->inTransaction()) {
            $transactionStarted = $this->pdo->beginTransaction();
        }

        try {
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $this->pdo->exec($query);
                }
            }
            
            // Получение текущего batch номера
            $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM migrations");
            $result = $stmt->fetch();
            $batch = ($result['max_batch'] ?? 0) + 1;
            
            // Запись о выполненной миграции
            $stmt = $this->pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
            $stmt->execute([$name, $batch]);
            
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            echo "✓ Миграция {$name} выполнена успешно\n";
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            echo "✗ Ошибка при выполнении миграции {$name}: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Запуск всех новых миграций
     */
    public function run() {
        echo "=== Запуск миграций ===\n\n";
        
        // Создание таблицы миграций
        $this->createMigrationsTable();
        echo "Таблица migrations создана/проверена\n\n";
        
        // Получение списков миграций
        $executedMigrations = $this->getExecutedMigrations();
        $migrationFiles = $this->getMigrationFiles();
        
        if (empty($migrationFiles)) {
            echo "Миграции не найдены\n";
            return;
        }
        
        echo "Найдено миграций: " . count($migrationFiles) . "\n";
        echo "Выполнено миграций: " . count($executedMigrations) . "\n\n";
        
        $newMigrations = 0;
        
        foreach ($migrationFiles as $migration) {
            if (!in_array($migration['name'], $executedMigrations)) {
                try {
                    $this->runMigration($migration['file'], $migration['name']);
                    $newMigrations++;
                } catch (Exception $e) {
                    echo "\nОстановка выполнения миграций из-за ошибки.\n";
                    exit(1);
                }
            } else {
                echo "○ Миграция {$migration['name']} уже выполнена, пропускаем\n";
            }
        }
        
        echo "\n=== Завершено ===\n";
        echo "Выполнено новых миграций: {$newMigrations}\n";
        
        if (function_exists('refresh_asset_version')) {
            $newAssetVersion = refresh_asset_version();
            echo "Обновлена версия статических ресурсов: {$newAssetVersion}\n";
        }
    }
    
    /**
     * Показать статус миграций
     */
    public function status() {
        echo "=== Статус миграций ===\n\n";
        
        $this->createMigrationsTable();
        
        $executedMigrations = $this->getExecutedMigrations();
        $migrationFiles = $this->getMigrationFiles();
        
        echo "Всего файлов миграций: " . count($migrationFiles) . "\n";
        echo "Выполнено миграций: " . count($executedMigrations) . "\n\n";
        
        if (empty($migrationFiles)) {
            echo "Миграции не найдены\n";
            return;
        }
        
        echo "Список миграций:\n";
        echo str_repeat("-", 60) . "\n";
        
        foreach ($migrationFiles as $migration) {
            $status = in_array($migration['name'], $executedMigrations) ? "✓ Выполнена" : "○ Ожидает";
            printf("%-40s %s\n", $migration['name'], $status);
        }
    }
}

// Запуск скрипта
$command = $argv[1] ?? 'run';

$runner = new MigrationRunner();

switch ($command) {
    case 'run':
        $runner->run();
        break;
    case 'status':
        $runner->status();
        break;
    default:
        echo "Использование: php migrations/migrate.php [run|status]\n";
        echo "  run    - выполнить все новые миграции (по умолчанию)\n";
        echo "  status - показать статус миграций\n";
        exit(1);
}
