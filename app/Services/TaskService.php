<?php

namespace App\Services;

use App\Repositories\TaskRepository;
use App\Repositories\TaskItemRepository;
use App\Repositories\TaskFileRepository;
use App\Repositories\UserRepository;
use PDO;
use RuntimeException;
use DomainException;

/**
 * Сервис для работы с задачами
 * 
 * Содержит бизнес-логику для работы с задачами:
 * - валидация данных
 * - проверка прав доступа
 * - управление подзадачами и файлами
 * - логирование действий
 * - форматирование данных для отображения
 */
class TaskService
{
    /**
     * @var TaskRepository Репозиторий для работы с задачами
     */
    private TaskRepository $tasks;
    
    /**
     * @var TaskItemRepository Репозиторий для работы с подзадачами
     */
    private TaskItemRepository $items;
    
    /**
     * @var TaskFileRepository Репозиторий для работы с файлами задач
     */
    private TaskFileRepository $files;
    
    /**
     * @var UserRepository Репозиторий для работы с пользователями
     */
    private UserRepository $users;
    
    /**
     * @var PDO Подключение к базе данных
     */
    private PDO $pdo;

    /**
     * Конструктор сервиса
     * 
     * @param PDO $pdo Подключение к базе данных
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->tasks = new TaskRepository($pdo);
        $this->items = new TaskItemRepository($pdo);
        $this->files = new TaskFileRepository($pdo);
        $this->users = new UserRepository($pdo);
    }

    /**
     * Получает список задач в зависимости от вкладки
     * 
     * - 'assigned': задачи, назначенные пользователем (только для админов)
     * - другие: задачи, назначенные на пользователя
     * 
     * @param string $tab Вкладка ('assigned' или другие)
     * @param int $userId ID текущего пользователя
     * @param bool $isAdmin Является ли пользователь администратором
     * @return array Массив задач
     * @throws DomainException Если доступ запрещен
     */
    public function listTasks(string $tab, int $userId, bool $isAdmin): array
    {
        if ($tab === 'assigned') {
            if (!$isAdmin) {
                throw new DomainException('Доступ запрещен');
            }
            return $this->tasks->getTasksCreatedBy($userId);
        }

        return $this->tasks->getTasksAssignedTo($userId);
    }

    /**
     * Получает полную информацию о задаче, включая подзадачи и файлы
     * 
     * Права доступа:
     * - пользователь может просматривать задачи, назначенные на него
     * - администратор может просматривать задачи, созданные им
     * 
     * @param int $taskId ID задачи
     * @param int $userId ID текущего пользователя
     * @param bool $isAdmin Является ли пользователь администратором
     * @return array Массив с ключами 'task', 'items' (подзадачи), 'files' (файлы)
     * @throws RuntimeException Если задача не найдена
     * @throws DomainException Если доступ запрещен
     */
    public function getTask(int $taskId, int $userId, bool $isAdmin): array
    {
        $task = $this->tasks->findById($taskId);
        if (!$task) {
            throw new RuntimeException('Задача не найдена');
        }

        if ($task['assigned_to'] != $userId && ($task['created_by'] != $userId || !$isAdmin)) {
            throw new DomainException('Доступ запрещен');
        }

        $items = $this->items->getByTask($taskId);
        $files = $this->files->getByTask($taskId);

        $task['due_date'] = $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : null;
        $task['created_at'] = date('Y-m-d H:i', strtotime($task['created_at']));
        $task['updated_at'] = date('Y-m-d H:i', strtotime($task['updated_at']));
        $task['completed_at'] = $task['completed_at'] ? date('Y-m-d H:i', strtotime($task['completed_at'])) : null;

        foreach ($items as &$item) {
            $item['completed_at'] = $item['completed_at'] ? date('Y-m-d H:i', strtotime($item['completed_at'])) : null;
        }

        return [
            'task' => $task,
            'items' => $items,
            'files' => $files,
        ];
    }

    /**
     * Получает список активных пользователей
     * 
     * Используется для выпадающих списков при назначении задач.
     * 
     * @return array Массив пользователей (id, login, full_name)
     */
    public function listUsers(): array
    {
        return $this->users->getActiveUsers();
    }

    /**
     * Создает новую задачу
     * 
     * Валидация:
     * - название задачи обязательно
     * - пользователь, на которого назначается задача, должен быть указан и существовать
     * 
     * @param array $payload Данные задачи (title, assigned_to, description, due_date, items, files)
     * @param int $userId ID пользователя, создающего задачу
     * @return int ID созданной задачи
     * @throws DomainException Если данные некорректны
     */
    public function createTask(array $payload, int $userId): int
    {
        $title = trim($payload['title'] ?? '');
        $assignedTo = (int)($payload['assigned_to'] ?? 0);
        $description = isset($payload['description']) ? trim((string)$payload['description']) : null;
        $dueDate = $payload['due_date'] ?? null;

        if ($title === '') {
            throw new DomainException('Название задачи обязательно');
        }
        if ($assignedTo <= 0) {
            throw new DomainException('Ответственный не указан');
        }

        $user = $this->users->findActiveById($assignedTo);
        if (!$user) {
            throw new RuntimeException('Пользователь не найден или неактивен');
        }

        $this->pdo->beginTransaction();
        try {
            $taskId = $this->tasks->create($title, $description, $assignedTo, $userId, $dueDate ?: null);

            if (!empty($payload['items']) && is_array($payload['items'])) {
                foreach ($payload['items'] as $index => $item) {
                    $itemTitle = trim((string)($item['title'] ?? ''));
                    if ($itemTitle === '') {
                        continue;
                    }
                    $isCompleted = !empty($item['is_completed']);
                    $this->items->insert($taskId, $itemTitle, $isCompleted, $userId, $index);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $taskId;
    }

    public function updateTask(array $payload, int $userId): void
    {
        $taskId = (int)($payload['id'] ?? 0);
        if ($taskId <= 0) {
            throw new DomainException('ID задачи не указан');
        }

        $task = $this->tasks->findById($taskId);
        if (!$task) {
            throw new RuntimeException('Задача не найдена');
        }

        $title = trim($payload['title'] ?? $task['title']);
        $description = isset($payload['description']) ? trim((string)$payload['description']) : $task['description'];
        $assignedTo = isset($payload['assigned_to']) ? (int)$payload['assigned_to'] : (int)$task['assigned_to'];
        $dueDate = $payload['due_date'] ?? $task['due_date'];

        if ($title === '') {
            throw new DomainException('Название задачи обязательно');
        }

        $user = $this->users->findActiveById($assignedTo);
        if (!$user) {
            throw new RuntimeException('Пользователь не найден или неактивен');
        }

        $existingItems = $this->items->getByTask($taskId);
        $existingMap = [];
        foreach ($existingItems as $item) {
            $existingMap[(int)$item['id']] = $item;
        }

        $this->pdo->beginTransaction();
        try {
            $this->tasks->update($taskId, $title, $description, $assignedTo, $dueDate ?: null);

            $keepIds = [];
            if (isset($payload['items']) && is_array($payload['items'])) {
                foreach ($payload['items'] as $index => $itemPayload) {
                    $itemTitle = trim((string)($itemPayload['title'] ?? ''));
                    if ($itemTitle === '') {
                        continue;
                    }
                    $itemId = isset($itemPayload['id']) ? (int)$itemPayload['id'] : null;
                    $isCompleted = !empty($itemPayload['is_completed']);

                    if ($itemId && isset($existingMap[$itemId])) {
                        $existing = $existingMap[$itemId];
                        $completedAt = $existing['completed_at'] ? date('Y-m-d H:i:s', strtotime($existing['completed_at'])) : null;
                        $completedBy = $existing['completed_by'] ?? null;

                        if ((bool)$existing['is_completed'] !== $isCompleted) {
                            if ($isCompleted) {
                                $completedAt = date('Y-m-d H:i:s');
                                $completedBy = $userId;
                            } else {
                                $completedAt = null;
                                $completedBy = null;
                            }
                        }

                        $this->items->update($itemId, $itemTitle, $index, $isCompleted, $completedBy, $completedAt);
                        $keepIds[] = $itemId;
                    } else {
                        $newId = $this->items->insert($taskId, $itemTitle, $isCompleted, $userId, $index);
                        $keepIds[] = $newId;
                    }
                }
            }

            $this->items->deleteMissing($taskId, $keepIds);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function updateItemsOrder(int $taskId, array $items, int $userId, bool $isAdmin): void
    {
        $task = $this->tasks->findById($taskId);
        if (!$task) {
            throw new RuntimeException('Задача не найдена');
        }
        if ($task['assigned_to'] != $userId && ($task['created_by'] != $userId || !$isAdmin)) {
            throw new DomainException('Доступ запрещен');
        }
        foreach ($items as $item) {
            $itemId = isset($item['id']) ? (int)$item['id'] : 0;
            $sortOrder = isset($item['sort_order']) ? (int)$item['sort_order'] : 0;
            if ($itemId > 0) {
                $this->items->updateSortOrder($itemId, $taskId, $sortOrder);
            }
        }
    }

    public function toggleTaskCompletion(int $taskId, bool $isCompleted, int $userId): void
    {
        $task = $this->tasks->findById($taskId);
        if (!$task) {
            throw new RuntimeException('Задача не найдена');
        }
        if ($task['assigned_to'] != $userId) {
            throw new DomainException('Доступ запрещен');
        }

        if ($isCompleted) {
            $this->tasks->markCompleted($taskId, $userId);
        } else {
            $this->tasks->markInProgress($taskId);
        }
    }

    public function toggleItemCompletion(int $itemId, bool $isCompleted, int $userId): void
    {
        $item = $this->items->findWithTask($itemId);
        if (!$item) {
            throw new RuntimeException('Элемент не найден');
        }
        if ((int)$item['assigned_to'] !== $userId) {
            throw new DomainException('Доступ запрещен');
        }

        $this->items->setCompletion($itemId, $isCompleted, $userId);

        $stats = $this->items->completionStats((int)$item['task_id']);
        if ($stats['total'] > 0 && $stats['completed'] == $stats['total']) {
            $this->tasks->markCompleted((int)$item['task_id'], $userId);
        }
    }

    public function deleteTask(int $taskId, bool $isAdmin): void
    {
        if (!$isAdmin) {
            throw new DomainException('Доступ запрещен');
        }
        $task = $this->tasks->findById($taskId);
        if (!$task) {
            throw new RuntimeException('Задача не найдена');
        }

        $this->pdo->beginTransaction();
        try {
            $this->items->deleteMissing($taskId, []);
            $this->files->deleteByTask($taskId);
            $this->tasks->delete($taskId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }
}
