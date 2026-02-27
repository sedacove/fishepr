<?php

namespace App\Controllers\Api;

use App\Services\DatabaseBackupService;
use App\Support\JsonResponse;
use App\Support\Request;
use RuntimeException;

/**
 * API контроллер для работы с дампами базы данных
 * 
 * Предоставляет endpoints для:
 * - создания дампов
 * - получения списка дампов
 * - загрузки дампов в базу данных
 * - удаления дампов
 * - скачивания дампов
 */
class DatabaseBackupController
{
    /**
     * @var DatabaseBackupService Сервис для работы с дампами
     */
    private DatabaseBackupService $service;

    /**
     * Конструктор контроллера
     * 
     * Проверяет авторизацию и права администратора.
     * Инициализирует сервис для работы с дампами.
     */
    public function __construct()
    {
        // Авторизация уже проверена в api/_bootstrap.php (isLoggedIn()).
        // Здесь дополнительно убеждаемся, что пользователь — администратор,
        // и возвращаем JSON-ошибку вместо редиректа.
        if (!\function_exists('isAdmin') || !\isAdmin()) {
            JsonResponse::error('Доступ запрещен: требуются права администратора', 403);
            exit;
        }

        $this->service = new DatabaseBackupService();
    }

    /**
     * Обрабатывает входящий запрос
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'list');

        try {
            switch ($action) {
                case 'create':
                    $this->handleCreate($request);
                    break;
                case 'list':
                    $this->handleList();
                    break;
                case 'restore':
                    $this->handleRestore($request);
                    break;
                case 'delete':
                    $this->handleDelete($request);
                    break;
                case 'download':
                    $this->handleDownload($request);
                    break;
                default:
                    JsonResponse::error('Неизвестное действие', 400);
            }
        } catch (RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            error_log("Error in DatabaseBackupController::handle(): " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            JsonResponse::error('Внутренняя ошибка сервера', 500);
        }
    }

    /**
     * Создает новый дамп базы данных
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleCreate(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            JsonResponse::error('Метод не поддерживается', 405);
            return;
        }

        $backup = $this->service->createBackup();
        
        \logMessage('info', 'Создан дамп базы данных', [
            'filename' => $backup['filename'],
            'size' => $backup['size'],
        ]);

        JsonResponse::success($backup, 'Дамп базы данных успешно создан');
    }

    /**
     * Получает список всех дампов
     * 
     * @return void
     */
    private function handleList(): void
    {
        $backups = $this->service->listBackups();
        JsonResponse::success($backups);
    }

    /**
     * Загружает дамп в базу данных
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleRestore(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            JsonResponse::error('Метод не поддерживается', 405);
            return;
        }

        $filename = $request->getPost('filename');
        if (!$filename) {
            JsonResponse::error('Имя файла не указано', 400);
            return;
        }

        // Проверяем, что файл существует
        $backups = $this->service->listBackups();
        $exists = false;
        foreach ($backups as $backup) {
            if ($backup['filename'] === $filename) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            JsonResponse::error('Файл дампа не найден', 404);
            return;
        }

        $this->service->restoreBackup($filename);
        
        \logMessage('warning', 'Загружен дамп базы данных', [
            'filename' => $filename,
        ]);

        JsonResponse::success([], 'Дамп успешно загружен в базу данных');
    }

    /**
     * Удаляет дамп
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleDelete(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            JsonResponse::error('Метод не поддерживается', 405);
            return;
        }

        $filename = $request->getPost('filename');
        if (!$filename) {
            JsonResponse::error('Имя файла не указано', 400);
            return;
        }

        $this->service->deleteBackup($filename);
        
        \logMessage('info', 'Удален дамп базы данных', [
            'filename' => $filename,
        ]);

        JsonResponse::success([], 'Дамп успешно удален');
    }

    /**
     * Отдает файл дампа для скачивания
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleDownload(Request $request): void
    {
        $filename = $request->getQuery('filename');
        if (!$filename) {
            JsonResponse::error('Имя файла не указано', 400);
            return;
        }

        try {
            $filepath = $this->service->getBackupPath($filename);
            
            // Проверяем, что файл находится в директории дампов (безопасность)
            $backupsDir = dirname($filepath);
            $realBackupsDir = realpath($backupsDir);
            $realFilePath = realpath($filepath);
            
            if (!$realFilePath || strpos($realFilePath, $realBackupsDir) !== 0) {
                throw new RuntimeException('Неверный путь к файлу');
            }

            // Отдаем файл для скачивания
            header('Content-Type: application/gzip');
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');

            readfile($filepath);
            exit;
        } catch (RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }
}

