<?php

namespace App\Services;

use App\Models\User\User;
use App\Repositories\UserRepository;
use App\Support\Exceptions\ValidationException;
use PDO;
use RuntimeException;

require_once __DIR__ . '/../../includes/activity_log.php';
require_once __DIR__ . '/../../includes/settings.php';

/**
 * Сервис для работы с пользователями
 * 
 * Содержит бизнес-логику для работы с пользователями:
 * - валидация данных
 * - хеширование паролей
 * - санитизация телефонов
 * - логирование действий
 * - управление активностью пользователей
 */
class UserService
{
    /**
     * @var UserRepository Репозиторий для работы с пользователями
     */
    private UserRepository $users;

    /**
     * Конструктор сервиса
     * 
     * @param PDO $pdo Подключение к базе данных
     */
    public function __construct(PDO $pdo)
    {
        $this->users = new UserRepository($pdo);
    }

    /**
     * Получает список всех активных пользователей
     * 
     * @return array<int,array<string,mixed>> Массив пользователей
     */
    public function list(): array
    {
        $rows = $this->users->findAllActive();
        $result = [];
        foreach ($rows as $row) {
            $row['salary'] = $row['salary'] !== null ? (float)$row['salary'] : null;
            $row['is_active'] = (bool)$row['is_active'];
            $row['created_at'] = $this->formatDateTime($row['created_at']);
            $row['updated_at'] = $this->formatDateTime($row['updated_at']);

            $user = new User($row);
            $result[] = $user->toArray();
        }
        return $result;
    }

    /**
     * Получает пользователя по ID
     * 
     * @param int $id ID пользователя
     * @return array Данные пользователя
     * @throws ValidationException Если ID не указан
     * @throws RuntimeException Если пользователь не найден
     */
    public function get(int $id): array
    {
        if ($id <= 0) {
            throw new ValidationException('id', 'ID пользователя не указан', 400);
        }
        $user = $this->users->findById($id);
        if (!$user) {
            throw new RuntimeException('Пользователь не найден', 404);
        }
        $user['salary'] = $user['salary'] !== null ? (float)$user['salary'] : null;
        $user['is_active'] = (int)$user['is_active'];
        return $user;
    }

    /**
     * Создает нового пользователя
     * 
     * Валидация:
     * - логин обязателен и должен быть уникальным
     * - пароль обязателен, минимум 6 символов
     * - тип пользователя должен быть 'admin' или 'user'
     * 
     * Автоматически:
     * - хеширует пароль
     * - санитизирует телефоны
     * - нормализует зарплату
     * 
     * @param array $payload Данные пользователя (login, password, user_type, full_name, email, salary, phone, etc.)
     * @param int $currentUserId ID текущего пользователя (для логирования)
     * @return int ID созданного пользователя
     * @throws ValidationException Если данные некорректны
     * @throws RuntimeException Если пользователь с таким логином уже существует
     */
    public function create(array $payload, int $currentUserId): int
    {
        $login = trim($payload['login'] ?? '');
        $password = (string)($payload['password'] ?? '');
        $userType = (string)($payload['user_type'] ?? 'user');
        $fullName = trim($payload['full_name'] ?? '');
        $email = trim($payload['email'] ?? '');

        if ($login === '') {
            throw new ValidationException('login', 'Логин обязателен для заполнения');
        }
        if ($password === '') {
            throw new ValidationException('password', 'Пароль обязателен для заполнения');
        }
        if (strlen($password) < 6) {
            throw new ValidationException('password', 'Пароль должен содержать минимум 6 символов');
        }
        if (!in_array($userType, ['admin', 'user'], true)) {
            throw new ValidationException('user_type', 'Неверный тип пользователя');
        }

        if ($this->users->findByLogin($login)) {
            throw new RuntimeException('Пользователь с таким логином уже существует', 400);
        }

        $salary = $this->parseSalary($payload['salary'] ?? null);
        $phone = $this->sanitizePhone($payload['phone'] ?? null, 'phone');
        $payrollPhone = $this->sanitizePhone($payload['payroll_phone'] ?? null, 'payroll_phone');
        $payrollBank = $this->normalizeNullableString($payload['payroll_bank'] ?? null);

        $userId = $this->users->insert([
            'login' => $login,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'user_type' => $userType,
            'full_name' => $fullName !== '' ? $fullName : null,
            'email' => $email !== '' ? $email : null,
            'salary' => $salary,
            'phone' => $phone,
            'payroll_phone' => $payrollPhone,
            'payroll_bank' => $payrollBank,
        ]);

        \logActivity('create', 'user', $userId, "Создан пользователь: {$login}", [
            'login' => $login,
            'user_type' => $userType,
            'full_name' => $fullName,
            'email' => $email,
            'salary' => $salary,
            'phone' => $phone,
            'payroll_phone' => $payrollPhone,
            'payroll_bank' => $payrollBank,
        ]);

        return $userId;
    }

    /**
     * Обновляет данные пользователя
     * 
     * Валидация:
     * - ID пользователя должен быть указан
     * - нельзя редактировать свой собственный профиль через эту форму
     * - пароль (если указан) должен быть минимум 6 символов
     * - тип пользователя должен быть 'admin' или 'user'
     * 
     * Обновляет только те поля, которые переданы в payload.
     * Автоматически хеширует пароль и санитизирует телефоны.
     * 
     * @param int $id ID пользователя для обновления
     * @param array $payload Данные для обновления (user_type, full_name, email, password, salary, phone, etc.)
     * @param int $currentUserId ID текущего пользователя (для проверки и логирования)
     * @return void
     * @throws ValidationException Если данные некорректны
     * @throws RuntimeException Если пользователь не найден или нет данных для обновления
     */
    public function update(int $id, array $payload, int $currentUserId): void
    {
        if ($id <= 0) {
            throw new ValidationException('id', 'ID пользователя не указан', 400);
        }
        if ($id === $currentUserId) {
            throw new RuntimeException('Нельзя редактировать свой собственный профиль через эту форму', 400);
        }

        $existing = $this->users->findById($id);
        if (!$existing) {
            throw new RuntimeException('Пользователь не найден', 404);
        }

        $updates = [];
        $changes = [];

        if (isset($payload['user_type'])) {
            $userType = (string)$payload['user_type'];
            if (!in_array($userType, ['admin', 'user'], true)) {
                throw new ValidationException('user_type', 'Неверный тип пользователя');
            }
            if ($userType !== $existing['user_type']) {
                $updates['user_type'] = $userType;
                $changes['user_type'] = ['old' => $existing['user_type'], 'new' => $userType];
            }
        }

        if (array_key_exists('full_name', $payload)) {
            $fullName = $this->normalizeNullableString($payload['full_name']);
            if ($fullName !== $existing['full_name']) {
                $updates['full_name'] = $fullName;
                $changes['full_name'] = ['old' => $existing['full_name'], 'new' => $fullName];
            }
        }

        if (array_key_exists('email', $payload)) {
            $email = $this->normalizeNullableString($payload['email']);
            if ($email !== $existing['email']) {
                $updates['email'] = $email;
                $changes['email'] = ['old' => $existing['email'], 'new' => $email];
            }
        }

        if (!empty($payload['password'])) {
            $password = (string)$payload['password'];
            if (strlen($password) < 6) {
                throw new ValidationException('password', 'Пароль должен содержать минимум 6 символов');
            }
            $updates['password'] = password_hash($password, PASSWORD_DEFAULT);
            $changes['password'] = ['changed' => true];
        }

        if (array_key_exists('salary', $payload)) {
            $salary = $this->parseSalary($payload['salary']);
            $existingSalary = $existing['salary'] !== null ? (float)$existing['salary'] : null;
            if ($salary !== $existingSalary) {
                $updates['salary'] = $salary;
                $changes['salary'] = ['old' => $existingSalary, 'new' => $salary];
            }
        }

        if (array_key_exists('phone', $payload)) {
            $phone = $this->sanitizePhone($payload['phone'], 'phone');
            if ($phone !== $existing['phone']) {
                $updates['phone'] = $phone;
                $changes['phone'] = ['old' => $existing['phone'], 'new' => $phone];
            }
        }

        if (array_key_exists('payroll_phone', $payload)) {
            $payrollPhone = $this->sanitizePhone($payload['payroll_phone'], 'payroll_phone');
            if ($payrollPhone !== $existing['payroll_phone']) {
                $updates['payroll_phone'] = $payrollPhone;
                $changes['payroll_phone'] = ['old' => $existing['payroll_phone'], 'new' => $payrollPhone];
            }
        }

        if (array_key_exists('payroll_bank', $payload)) {
            $payrollBank = $this->normalizeNullableString($payload['payroll_bank']);
            if ($payrollBank !== $existing['payroll_bank']) {
                $updates['payroll_bank'] = $payrollBank;
                $changes['payroll_bank'] = ['old' => $existing['payroll_bank'], 'new' => $payrollBank];
            }
        }

        if (array_key_exists('is_active', $payload)) {
            $isActive = (int)$payload['is_active'] ? 1 : 0;
            if ((int)$existing['is_active'] !== $isActive) {
                $updates['is_active'] = $isActive;
                $changes['is_active'] = ['old' => (bool)$existing['is_active'], 'new' => (bool)$isActive];
            }
        }

        if (empty($updates)) {
            throw new RuntimeException('Нет данных для обновления', 400);
        }

        $this->users->update($id, $updates);

        if (!empty($changes)) {
            \logActivity('update', 'user', $id, 'Обновлен пользователь: ' . $existing['login'], $changes);
        }
    }

    /**
     * Удаляет пользователя (мягкое удаление)
     * 
     * Валидация:
     * - ID пользователя должен быть указан
     * - нельзя удалить свой собственный аккаунт
     * 
     * Выполняет мягкое удаление (soft delete) - устанавливает deleted_at,
     * пользователь остается в базе данных, но не отображается в списках.
     * 
     * @param int $id ID пользователя для удаления
     * @param int $currentUserId ID текущего пользователя (для проверки)
     * @return void
     * @throws ValidationException Если ID не указан
     * @throws RuntimeException Если пользователь не найден или попытка удалить свой аккаунт
     */
    public function delete(int $id, int $currentUserId): void
    {
        if ($id <= 0) {
            throw new ValidationException('id', 'ID пользователя не указан', 400);
        }
        if ($id === $currentUserId) {
            throw new RuntimeException('Нельзя удалить свой собственный аккаунт', 400);
        }

        $user = $this->users->findById($id);
        if (!$user) {
            throw new RuntimeException('Пользователь не найден или уже удален', 404);
        }

        $this->users->softDelete($id);

        \logActivity('delete', 'user', $id, 'Удален пользователь: ' . $user['login'], [
            'login' => $user['login'],
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Переключает статус активности пользователя
     * 
     * Валидация:
     * - ID пользователя должен быть указан
     * - нельзя заблокировать свой собственный аккаунт
     * 
     * @param int $id ID пользователя
     * @param int $currentUserId ID текущего пользователя (для проверки)
     * @return bool Новый статус активности (true = активен, false = заблокирован)
     * @throws ValidationException Если ID не указан
     * @throws RuntimeException Если пользователь не найден или попытка заблокировать свой аккаунт
     */
    public function toggleActive(int $id, int $currentUserId): bool
    {
        if ($id <= 0) {
            throw new ValidationException('id', 'ID пользователя не указан', 400);
        }
        if ($id === $currentUserId) {
            throw new RuntimeException('Нельзя заблокировать свой собственный аккаунт', 400);
        }

        $user = $this->users->findById($id);
        if (!$user) {
            throw new RuntimeException('Пользователь не найден', 404);
        }

        $newStatus = !$user['is_active'];
        $this->users->setActive($id, $newStatus);
        return $newStatus;
    }

    /**
     * Парсит и валидирует значение зарплаты
     * 
     * @param mixed $value Значение зарплаты
     * @return float|null Зарплата как число с 2 знаками после запятой, или null если пусто
     * @throws ValidationException Если значение не является числом или отрицательное
     */
    private function parseSalary($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new ValidationException('salary', 'Зарплата должна быть числом');
        }
        $salary = round((float)$value, 2);
        if ($salary < 0) {
            throw new ValidationException('salary', 'Зарплата не может быть отрицательной');
        }
        return $salary;
    }

    /**
     * Санитизирует и валидирует номер телефона
     * 
     * Удаляет все символы, кроме цифр, плюса и дефиса.
     * Если результат пустой, возвращает null.
     * 
     * @param mixed $value Значение телефона
     * @param string $field Имя поля (для сообщений об ошибках)
     * @return string|null Санитизированный телефон или null если пусто
     */
    private function sanitizePhone($value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        } elseif (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }

        if (strlen($digits) !== 11 || $digits[0] !== '7') {
            throw new ValidationException($field, 'Телефон должен быть в формате +7XXXXXXXXXX');
        }

        return '+' . $digits;
    }

    /**
     * Нормализует строковое значение (trim и преобразование пустой строки в null)
     * 
     * @param mixed $value Значение для нормализации
     * @return string|null Обрезанная строка или null если пусто
     */
    private function normalizeNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    /**
     * Форматирует дату и время для отображения
     * 
     * @param string $value Дата в формате БД (Y-m-d H:i:s)
     * @return string Дата в формате для отображения (d.m.Y H:i)
     */
    private function formatDateTime(string $value): string
    {
        return date('d.m.Y H:i', strtotime($value));
    }
}


