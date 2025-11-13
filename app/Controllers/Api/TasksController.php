<?php

namespace App\Controllers\Api;

use App\Services\TaskService;
use App\Support\JsonResponse;
use App\Support\Request;
use Exception;
use PDO;

/**
 * API контроллер для работы с задачами
 * 
 * Обрабатывает HTTP запросы к API endpoints для задач:
 * - get_users: получение списка активных пользователей
 * - list: получение списка задач (мои или назначенные мной)
 * - get: получение одной задачи с подзадачами и файлами
 * - create: создание новой задачи (только для админов)
 * - update: обновление задачи (только для админов)
 * - complete: переключение статуса выполнения задачи
 * - update_items_order: изменение порядка подзадач
 * - complete_item: переключение статуса выполнения подзадачи
 * - delete: удаление задачи (только для админов)
 * 
 * Авторизация проверяется в api/tasks.php
 */
class TasksController
{
    /**
     * @var TaskService Сервис для работы с задачами
     */
    private TaskService $service;
    
    /**
     * @var int ID текущего пользователя
     */
    private int $userId;
    
    /**
     * @var bool Является ли пользователь администратором
     */
    private bool $isAdmin;

    /**
     * Конструктор контроллера
     * 
     * Инициализирует сервис и получает информацию о текущем пользователе.
     * Авторизация проверяется в api/tasks.php.
     */
    public function __construct()
    {
        // Авторизация проверяется в api/tasks.php
        $pdo = \getDBConnection();
        $this->service = new TaskService($pdo);
        $this->userId = \getCurrentUserId();
        $this->isAdmin = \isAdmin();
    }

    /**
     * Обрабатывает входящий запрос и направляет его к соответствующему обработчику
     * 
     * @param Request $request Объект запроса
     * @return void
     */
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

    /**
     * Обрабатывает запрос на получение списка активных пользователей
     * 
     * @return void
     */
    private function handleGetUsers(): void
    {
        $users = $this->service->listUsers();
        JsonResponse::success($users);
    }

    /**
     * Обрабатывает запрос на получение списка задач
     * 
     * @param Request $request Объект запроса
     * @return void
     */
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

    /**
     * Обрабатывает запрос на получение одной задачи
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws Exception Если ID задачи не указан
     */
    private function handleGet(Request $request): void
    {
        $taskId = (int) $request->getQuery('id', 0);
        if ($taskId <= 0) {
            throw new Exception('ID задачи не указан');
        }

        $data = $this->service->getTask($taskId, $this->userId, $this->isAdmin);
        JsonResponse::success($data);
    }

    /**
     * Обрабатывает запрос на создание новой задачи
     * 
     * Доступно только администраторам.
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws Exception Если доступ запрещен
     */
    private function handleCreate(Request $request): void
    {
        if (!$this->isAdmin) {
            throw new Exception('Доступ запрещен');
        }
        $payload = $request->getJsonBody();
        $taskId = $this->service->createTask($payload, $this->userId);
        JsonResponse::success(['id' => $taskId], 'Задача успешно создана');
    }

    /**
     * Обрабатывает запрос на обновление задачи
     * 
     * Доступно только администраторам.
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws Exception Если доступ запрещен
     */
    private function handleUpdate(Request $request): void
    {
        if (!$this->isAdmin) {
            throw new Exception('Доступ запрещен');
        }
        $payload = $request->getJsonBody();
        $this->service->updateTask($payload, $this->userId);
        JsonResponse::success([], 'Задача успешно обновлена');
    }

    /**
     * Обрабатывает запрос на изменение порядка подзадач
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws Exception Если ID задачи не указан
     */
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

    /**
     * Обрабатывает запрос на переключение статуса выполнения задачи
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws Exception Если ID задачи не указан
     */
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

    /**
     * Обрабатывает запрос на переключение статуса выполнения подзадачи
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws Exception Если ID подзадачи не указан
     */
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

    /**
     * Обрабатывает запрос на удаление задачи
     * 
     * Доступно только администраторам.
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws Exception Если доступ запрещен или ID задачи не указан
     */
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

    /**
     * Проверяет, что запрос использует метод POST
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws Exception Если метод не POST
     */
    private function requirePost(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new Exception('Метод не поддерживается');
        }
    }
}
