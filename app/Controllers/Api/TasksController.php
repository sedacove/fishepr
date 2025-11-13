<?php

namespace App\Controllers\Api;

use App\Services\TaskService;
use App\Support\JsonResponse;
use App\Support\Request;
use Exception;
use PDO;

class TasksController
{
    private TaskService $service;
    private int $userId;
    private bool $isAdmin;

    public function __construct()
    {
        // Авторизация проверяется в api/tasks.php
        $pdo = \getDBConnection();
        $this->service = new TaskService($pdo);
        $this->userId = \getCurrentUserId();
        $this->isAdmin = \isAdmin();
    }

    public function handle(Request $request): void
    {
        $action = $request->getQuery('action');

        try {
            switch ($action) {
                case 'get_users':
                    $this->handleGetUsers();
                    break;
                case 'list':
                    $this->handleList($request);
                    break;
                case 'get':
                    $this->handleGet($request);
                    break;
                case 'create':
                    $this->requirePost($request);
                    $this->handleCreate($request);
                    break;
                case 'update':
                    $this->requirePost($request);
                    $this->handleUpdate($request);
                    break;
                case 'complete':
                    $this->requirePost($request);
                    $this->handleCompleteTask($request);
                    break;
                case 'update_items_order':
                    $this->requirePost($request);
                    $this->handleUpdateItemsOrder($request);
                    break;
                case 'complete_item':
                    $this->requirePost($request);
                    $this->handleCompleteItem($request);
                    break;
                case 'delete':
                    $this->requirePost($request);
                    $this->handleDelete($request);
                    break;
                default:
                    throw new Exception('Неизвестное действие');
            }
        } catch (Exception $exception) {
            JsonResponse::error($exception->getMessage());
        }
    }

    private function handleGetUsers(): void
    {
        $users = $this->service->listUsers();
        JsonResponse::success($users);
    }

    private function handleList(Request $request): void
    {
        $tab = $request->getQuery('tab', 'my');
        $tasks = $this->service->listTasks($tab, $this->userId, $this->isAdmin);
        $tasks = array_map(function (array $task) {
            $task['due_date'] = $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : null;
            $task['created_at'] = date('Y-m-d H:i', strtotime($task['created_at']));
            $task['updated_at'] = date('Y-m-d H:i', strtotime($task['updated_at']));
            $task['completed_at'] = $task['completed_at'] ? date('Y-m-d H:i', strtotime($task['completed_at'])) : null;
            return $task;
        }, $tasks);
        JsonResponse::success($tasks);
    }

    private function handleGet(Request $request): void
    {
        $taskId = (int) $request->getQuery('id', 0);
        if ($taskId <= 0) {
            throw new Exception('ID задачи не указан');
        }

        $data = $this->service->getTask($taskId, $this->userId, $this->isAdmin);
        JsonResponse::success($data);
    }

    private function handleCreate(Request $request): void
    {
        if (!$this->isAdmin) {
            throw new Exception('Доступ запрещен');
        }
        $payload = $request->getJsonBody();
        $taskId = $this->service->createTask($payload, $this->userId);
        JsonResponse::success(['id' => $taskId], 'Задача успешно создана');
    }

    private function handleUpdate(Request $request): void
    {
        if (!$this->isAdmin) {
            throw new Exception('Доступ запрещен');
        }
        $payload = $request->getJsonBody();
        $this->service->updateTask($payload, $this->userId);
        JsonResponse::success([], 'Задача успешно обновлена');
    }

    private function handleUpdateItemsOrder(Request $request): void
    {
        $payload = $request->getJsonBody();
        $taskId = (int)($payload['task_id'] ?? 0);
        if ($taskId <= 0) {
            throw new Exception('ID задачи не указан');
        }
        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
        $this->service->updateItemsOrder($taskId, $items, $this->userId, $this->isAdmin);
        JsonResponse::success([], 'Порядок элементов обновлен');
    }

    private function handleCompleteTask(Request $request): void
    {
        $payload = $request->getJsonBody();
        $taskId = (int)($payload['id'] ?? 0);
        if ($taskId <= 0) {
            throw new Exception('ID задачи не указан');
        }
        $isCompleted = !empty($payload['is_completed']);
        $this->service->toggleTaskCompletion($taskId, $isCompleted, $this->userId);
        JsonResponse::success([], $isCompleted ? 'Задача отмечена как выполненная' : 'Задача возвращена в работу');
    }

    private function handleCompleteItem(Request $request): void
    {
        $payload = $request->getJsonBody();
        $itemId = (int)($payload['id'] ?? 0);
        if ($itemId <= 0) {
            throw new Exception('ID элемента не указан');
        }
        $isCompleted = !empty($payload['is_completed']);
        $this->service->toggleItemCompletion($itemId, $isCompleted, $this->userId);
        JsonResponse::success([], $isCompleted ? 'Элемент отмечен как выполненный' : 'Элемент возвращен в работу');
    }

    private function handleDelete(Request $request): void
    {
        if (!$this->isAdmin) {
            throw new Exception('Доступ запрещен');
        }
        $payload = $request->getJsonBody();
        $taskId = (int)($payload['id'] ?? 0);
        if ($taskId <= 0) {
            throw new Exception('ID задачи не указан');
        }
        $this->service->deleteTask($taskId, $this->isAdmin);
        JsonResponse::success([], 'Задача удалена');
    }

    private function requirePost(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new Exception('Метод не поддерживается');
        }
    }
}
