<?php

namespace App\Services;

use RuntimeException;

/**
 * Сервис для работы с дампами базы данных
 * 
 * Предоставляет функциональность для:
 * - создания дампов базы данных
 * - архивирования дампов
 * - получения списка дампов
 * - загрузки дампов в базу данных
 * - удаления дампов
 */
class DatabaseBackupService
{
    /**
     * @var string Путь к директории для хранения дампов
     */
    private string $backupsDir;

    /**
     * @var string Хост базы данных
     */
    private string $dbHost;

    /**
     * @var string Имя базы данных
     */
    private string $dbName;

    /**
     * @var string Пользователь базы данных
     */
    private string $dbUser;

    /**
     * @var string Пароль базы данных
     */
    private string $dbPass;

    /**
     * Конструктор сервиса
     */
    public function __construct()
    {
        $this->backupsDir = __DIR__ . '/../../storage/backups';
        $this->dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
        $this->dbName = defined('DB_NAME') ? DB_NAME : '';
        $this->dbUser = defined('DB_USER') ? DB_USER : 'root';
        $this->dbPass = defined('DB_PASS') ? DB_PASS : '';

        // Создаем директорию для дампов, если её нет
        if (!is_dir($this->backupsDir)) {
            if (!mkdir($this->backupsDir, 0755, true)) {
                throw new RuntimeException("Не удалось создать директорию для дампов: {$this->backupsDir}");
            }
        }
    }

    /**
     * Создает дамп базы данных
     * 
     * @return array Информация о созданном дампе
     * @throws RuntimeException Если не удалось создать дамп
     */
    public function createBackup(): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_{$this->dbName}_{$timestamp}.sql";
        $filepath = $this->backupsDir . '/' . $filename;

        // Команда для создания дампа
        // Используем mysqldump из контейнера, если работаем в Docker
        $isDocker = getenv('DOCKER_ENV') === '1' || getenv('DOCKER_ENV') === 'true';
        
        if ($isDocker) {
            // В Docker используем mysqldump из контейнера db
            // Выполняем команду и перенаправляем вывод в файл
            $command = sprintf(
                'docker exec fisherp_db mysqldump -h localhost -u %s -p%s %s 2>&1',
                escapeshellarg($this->dbUser),
                escapeshellarg($this->dbPass),
                escapeshellarg($this->dbName)
            );
            
            // Выполняем команду и записываем вывод в файл
            $output = [];
            $returnCode = 0;
            $handle = popen($command, 'r');
            
            if ($handle === false) {
                throw new RuntimeException("Не удалось запустить процесс создания дампа");
            }
            
            $fileHandle = fopen($filepath, 'w');
            if ($fileHandle === false) {
                pclose($handle);
                throw new RuntimeException("Не удалось создать файл дампа: {$filepath}");
            }
            
            $hasError = false;
            while (!feof($handle)) {
                $line = fgets($handle);
                if ($line !== false) {
                    // Проверяем, не является ли строка ошибкой (но не предупреждением)
                    if (stripos($line, 'error') !== false && stripos($line, 'using a password') === false) {
                        $output[] = trim($line);
                        $hasError = true;
                    }
                    // Записываем все в файл (включая предупреждения)
                    fwrite($fileHandle, $line);
                }
            }
            
            $returnCode = pclose($handle);
            fclose($fileHandle);
        } else {
            // Локально используем mysqldump из системы
            $command = sprintf(
                'mysqldump -h %s -u %s -p%s %s > %s 2>&1',
                escapeshellarg($this->dbHost),
                escapeshellarg($this->dbUser),
                escapeshellarg($this->dbPass),
                escapeshellarg($this->dbName),
                escapeshellarg($filepath)
            );
            
            // Выполняем команду
            exec($command, $output, $returnCode);
        }

        // Проверяем наличие критических ошибок
        if (isset($hasError) && $hasError) {
            $error = implode("\n", $output);
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            throw new RuntimeException("Не удалось создать дамп базы данных. Ошибка: {$error}");
        }
        
        if (!file_exists($filepath)) {
            $error = implode("\n", $output);
            throw new RuntimeException("Не удалось создать дамп базы данных. Файл не создан. Ошибка: {$error}");
        }

        // Проверяем, что файл не пустой
        if (filesize($filepath) === 0) {
            unlink($filepath);
            throw new RuntimeException("Созданный дамп пуст");
        }

        // Архивируем дамп
        $archivePath = $this->archiveBackup($filepath);

        // Получаем информацию о дампе
        return $this->getBackupInfo($archivePath);
    }

    /**
     * Архивирует дамп в tar.gz
     * 
     * @param string $filepath Путь к файлу дампа
     * @return string Путь к архиву
     * @throws RuntimeException Если не удалось создать архив
     */
    private function archiveBackup(string $filepath): string
    {
        $archivePath = $filepath . '.tar.gz';
        
        // Создаем архив используя tar
        $command = sprintf(
            'cd %s && tar -czf %s %s 2>&1',
            escapeshellarg(dirname($filepath)),
            escapeshellarg(basename($archivePath)),
            escapeshellarg(basename($filepath))
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($archivePath)) {
            $error = implode("\n", $output);
            throw new RuntimeException("Не удалось создать архив. Ошибка: {$error}");
        }

        // Удаляем исходный SQL файл после архивации
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        return $archivePath;
    }

    /**
     * Получает список всех дампов
     * 
     * @return array Массив информации о дампах
     */
    public function listBackups(): array
    {
        $backups = [];
        $files = glob($this->backupsDir . '/*.tar.gz');

        foreach ($files as $filepath) {
            $backups[] = $this->getBackupInfo($filepath);
        }

        // Сортируем по дате создания (новые первыми)
        usort($backups, function ($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });

        return $backups;
    }

    /**
     * Получает информацию о дампе
     * 
     * @param string $filepath Путь к файлу дампа
     * @return array Информация о дампе
     */
    private function getBackupInfo(string $filepath): array
    {
        $filename = basename($filepath);
        $size = filesize($filepath);
        $createdAt = filemtime($filepath);

        // Парсим имя файла для получения даты
        if (preg_match('/backup_([^_]+)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.tar\.gz/', $filename, $matches)) {
            $dbName = $matches[1];
            $timestamp = $matches[2];
        } else {
            $dbName = $this->dbName;
            $timestamp = date('Y-m-d_H-i-s', $createdAt);
        }

        return [
            'filename' => $filename,
            'basename' => str_replace('.tar.gz', '', $filename),
            'path' => $filepath,
            'size' => $size,
            'size_formatted' => $this->formatBytes($size),
            'created_at' => $createdAt,
            'created_at_formatted' => date('d.m.Y H:i:s', $createdAt),
            'db_name' => $dbName,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Форматирует размер файла в читаемый вид
     * 
     * @param int $bytes Размер в байтах
     * @return string Отформатированный размер
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Загружает дамп в базу данных
     * 
     * @param string $filename Имя файла дампа
     * @return void
     * @throws RuntimeException Если не удалось загрузить дамп
     */
    public function restoreBackup(string $filename): void
    {
        $filepath = $this->backupsDir . '/' . $filename;

        if (!file_exists($filepath)) {
            throw new RuntimeException("Файл дампа не найден: {$filename}");
        }

        // Распаковываем архив
        $extractedPath = $this->extractBackup($filepath);

        // Загружаем дамп в базу данных
        $isDocker = getenv('DOCKER_ENV') === '1' || getenv('DOCKER_ENV') === 'true';

        if ($isDocker) {
            // В Docker используем mysql из контейнера db
            // Читаем файл и передаем через stdin
            $sqlContent = file_get_contents($extractedPath);
            if ($sqlContent === false) {
                throw new RuntimeException("Не удалось прочитать файл дампа");
            }
            
            $command = sprintf(
                'docker exec -i fisherp_db mysql -h localhost -u %s -p%s %s 2>&1',
                escapeshellarg($this->dbUser),
                escapeshellarg($this->dbPass),
                escapeshellarg($this->dbName)
            );
            
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            
            $process = proc_open($command, $descriptorspec, $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException("Не удалось запустить процесс восстановления");
            }
            
            fwrite($pipes[0], $sqlContent);
            fclose($pipes[0]);
            
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $returnCode = proc_close($process);
            
            if ($returnCode !== 0) {
                throw new RuntimeException("Не удалось загрузить дамп в базу данных. Ошибка: " . ($errors ?: $output));
            }
            
            // Удаляем распакованный файл
            if (file_exists($extractedPath)) {
                unlink($extractedPath);
            }
            
            return;
        } else {
            // Локально используем mysql из системы
            $command = sprintf(
                'mysql -h %s -u %s -p%s %s < %s 2>&1',
                escapeshellarg($this->dbHost),
                escapeshellarg($this->dbUser),
                escapeshellarg($this->dbPass),
                escapeshellarg($this->dbName),
                escapeshellarg($extractedPath)
            );
        }

        exec($command, $output, $returnCode);

        // Удаляем распакованный файл
        if (file_exists($extractedPath)) {
            unlink($extractedPath);
        }

        if ($returnCode !== 0) {
            $error = implode("\n", $output);
            throw new RuntimeException("Не удалось загрузить дамп в базу данных. Ошибка: {$error}");
        }
    }

    /**
     * Распаковывает архив дампа
     * 
     * @param string $filepath Путь к архиву
     * @return string Путь к распакованному файлу
     * @throws RuntimeException Если не удалось распаковать архив
     */
    private function extractBackup(string $filepath): string
    {
        $extractDir = $this->backupsDir . '/tmp';
        if (!is_dir($extractDir)) {
            mkdir($extractDir, 0755, true);
        }

        $command = sprintf(
            'cd %s && tar -xzf %s -C %s 2>&1',
            escapeshellarg(dirname($filepath)),
            escapeshellarg(basename($filepath)),
            escapeshellarg($extractDir)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $error = implode("\n", $output);
            throw new RuntimeException("Не удалось распаковать архив. Ошибка: {$error}");
        }

        // Ищем распакованный SQL файл
        $sqlFiles = glob($extractDir . '/*.sql');
        if (empty($sqlFiles)) {
            throw new RuntimeException("В архиве не найден SQL файл");
        }

        return $sqlFiles[0];
    }

    /**
     * Удаляет дамп
     * 
     * @param string $filename Имя файла дампа
     * @return void
     * @throws RuntimeException Если не удалось удалить дамп
     */
    public function deleteBackup(string $filename): void
    {
        $filepath = $this->backupsDir . '/' . $filename;

        if (!file_exists($filepath)) {
            throw new RuntimeException("Файл дампа не найден: {$filename}");
        }

        if (!unlink($filepath)) {
            throw new RuntimeException("Не удалось удалить файл дампа: {$filename}");
        }
    }

    /**
     * Получает путь к файлу дампа для скачивания
     * 
     * @param string $filename Имя файла дампа
     * @return string Путь к файлу
     * @throws RuntimeException Если файл не найден
     */
    public function getBackupPath(string $filename): string
    {
        $filepath = $this->backupsDir . '/' . $filename;

        if (!file_exists($filepath)) {
            throw new RuntimeException("Файл дампа не найден: {$filename}");
        }

        return $filepath;
    }
}

