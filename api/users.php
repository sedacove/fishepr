<?php
/**
 * API для управления пользователями
 * Доступно только администраторам
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

// Требуем права администратора
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function sanitizePhoneValue($input) {
    if ($input === null) {
        return null;
    }
    $input = trim($input);
    if ($input === '') {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $input);

    if (strlen($digits) === 10) {
        $digits = '7' . $digits;
    } elseif (strlen($digits) === 11 && $digits[0] === '8') {
        $digits = '7' . substr($digits, 1);
    }

    if (strlen($digits) !== 11 || $digits[0] !== '7') {
        throw new Exception('Телефон должен быть в формате +7XXXXXXXXXX');
    }

    return '+'.$digits;
}

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'list':
            // Получить список всех пользователей (исключая удаленных)
            $stmt = $pdo->query("SELECT id, login, user_type, full_name, email, salary, phone, payroll_phone, payroll_bank, is_active, created_at, updated_at FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC");
            $users = $stmt->fetchAll();
            
            // Преобразование дат для вывода
            foreach ($users as &$user) {
                $user['created_at'] = date('d.m.Y H:i', strtotime($user['created_at']));
                $user['updated_at'] = date('d.m.Y H:i', strtotime($user['updated_at']));
                if ($user['salary'] !== null) {
                    $user['salary'] = (float)$user['salary'];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $users
            ]);
            break;
            
        case 'get':
            // Получить данные одного пользователя (исключая удаленных)
            $id = $_GET['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID пользователя не указан');
            }
            
            $stmt = $pdo->prepare("SELECT id, login, user_type, full_name, email, salary, phone, payroll_phone, payroll_bank, is_active FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Пользователь не найден');
            }
            
            if ($user['salary'] !== null) {
                $user['salary'] = (float)$user['salary'];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $user
            ]);
            break;
            
        case 'create':
            // Создать нового пользователя
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $login = trim($data['login'] ?? '');
            $password = $data['password'] ?? '';
            $user_type = $data['user_type'] ?? 'user';
            $full_name = trim($data['full_name'] ?? '');
            $email = trim($data['email'] ?? '');
            $salaryInput = $data['salary'] ?? null;
            $phoneInput = $data['phone'] ?? null;
            $payrollPhoneInput = $data['payroll_phone'] ?? null;
            $payrollBankInput = $data['payroll_bank'] ?? null;
            $payrollBank = trim($payrollBankInput ?? '');
            
            $salary = null;
            if ($salaryInput !== null && $salaryInput !== '') {
                if (!is_numeric($salaryInput)) {
                    throw new Exception('Зарплата должна быть числом');
                }
                $salary = round((float)$salaryInput, 2);
                if ($salary < 0) {
                    throw new Exception('Зарплата не может быть отрицательной');
                }
            }
            
            $phone = sanitizePhoneValue($phoneInput);
            $payrollPhone = sanitizePhoneValue($payrollPhoneInput);
            if ($payrollBank === '') {
                $payrollBank = null;
            }
            
            // Валидация
            if (empty($login)) {
                throw new Exception('Логин обязателен для заполнения');
            }
            
            if (empty($password)) {
                throw new Exception('Пароль обязателен для заполнения');
            }
            
            if (strlen($password) < 6) {
                throw new Exception('Пароль должен содержать минимум 6 символов');
            }
            
            if (!in_array($user_type, ['admin', 'user'])) {
                throw new Exception('Неверный тип пользователя');
            }
            
            // Проверка уникальности логина (исключая удаленных)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ? AND deleted_at IS NULL");
            $stmt->execute([$login]);
            if ($stmt->fetch()) {
                throw new Exception('Пользователь с таким логином уже существует');
            }
            
            // Хеширование пароля
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Вставка
            $stmt = $pdo->prepare("INSERT INTO users (login, password, user_type, full_name, email, salary, phone, payroll_phone, payroll_bank) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $login,
                $passwordHash,
                $user_type,
                $full_name ?: null,
                $email ?: null,
                $salary,
                $phone,
                $payrollPhone,
                $payrollBank
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // Логирование с данными
            $changes = [
                'login' => $login,
                'user_type' => $user_type,
                'full_name' => $full_name,
                'email' => $email,
                'salary' => $salary,
                'phone' => $phone,
                'payroll_phone' => $payrollPhone,
                'payroll_bank' => $payrollBank
            ];
            logActivity('create', 'user', $userId, "Создан пользователь: {$login}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Пользователь успешно создан',
                'id' => $userId
            ]);
            break;
            
        case 'update':
            // Обновить пользователя
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID пользователя не указан');
            }
            
            // Проверка, что не редактируем самого себя (защита от случайной блокировки)
            if ($id == getCurrentUserId()) {
                throw new Exception('Нельзя редактировать свой собственный профиль через эту форму');
            }
            
            $user_type = $data['user_type'] ?? null;
            $full_name = trim($data['full_name'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $is_active = isset($data['is_active']) ? (int)$data['is_active'] : null;
            $salaryInput = $data['salary'] ?? null;
            $phoneInput = $data['phone'] ?? null;
            $payrollPhoneInput = $data['payroll_phone'] ?? null;
            $payrollBankInput = $data['payroll_bank'] ?? null;
            
            $salaryProvided = $salaryInput !== null;
            $salary = null;
            $salarySetNull = false;
            if ($salaryProvided) {
                if ($salaryInput === '' || $salaryInput === null) {
                    $salarySetNull = true;
                } else {
                    if (!is_numeric($salaryInput)) {
                        throw new Exception('Зарплата должна быть числом');
                    }
                    $salary = round((float)$salaryInput, 2);
                    if ($salary < 0) {
                        throw new Exception('Зарплата не может быть отрицательной');
                    }
                }
            }
            
            $phoneProvided = array_key_exists('phone', $data);
            $phone = null;
            $phoneSetNull = false;
            if ($phoneProvided) {
                $phoneInput = is_string($phoneInput) ? trim($phoneInput) : ($phoneInput === null ? null : (string)$phoneInput);
                if ($phoneInput === '' || $phoneInput === null) {
                    $phoneSetNull = true;
                } else {
                    $phone = sanitizePhoneValue($phoneInput);
                }
            }
            
            $payrollPhoneProvided = array_key_exists('payroll_phone', $data);
            $payrollPhone = null;
            $payrollPhoneSetNull = false;
            if ($payrollPhoneProvided) {
                $payrollPhoneInput = is_string($payrollPhoneInput) ? trim($payrollPhoneInput) : ($payrollPhoneInput === null ? null : (string)$payrollPhoneInput);
                if ($payrollPhoneInput === '' || $payrollPhoneInput === null) {
                    $payrollPhoneSetNull = true;
                } else {
                    $payrollPhone = sanitizePhoneValue($payrollPhoneInput);
                }
            }
            
            $payrollBankProvided = array_key_exists('payroll_bank', $data);
            $payrollBank = null;
            $payrollBankSetNull = false;
            if ($payrollBankProvided) {
                $payrollBank = trim((string)$payrollBankInput);
                if ($payrollBank === '') {
                    $payrollBankSetNull = true;
                }
            }
            
            // Проверка существования пользователя (исключая удаленных)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                throw new Exception('Пользователь не найден');
            }
            
            // Валидация
            if ($user_type !== null && !in_array($user_type, ['admin', 'user'])) {
                throw new Exception('Неверный тип пользователя');
            }
            
            if ($password && strlen($password) < 6) {
                throw new Exception('Пароль должен содержать минимум 6 символов');
            }
            
            // Построение запроса обновления
            $updates = [];
            $params = [];
            
            if ($user_type !== null) {
                $updates[] = "user_type = ?";
                $params[] = $user_type;
            }
            
            if ($full_name !== '') {
                $updates[] = "full_name = ?";
                $params[] = $full_name;
            } else {
                $updates[] = "full_name = NULL";
            }
            
            if ($email !== '') {
                $updates[] = "email = ?";
                $params[] = $email;
            } else {
                $updates[] = "email = NULL";
            }
            
            if ($password) {
                $updates[] = "password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            if ($salaryProvided) {
                if ($salarySetNull) {
                    $updates[] = "salary = NULL";
                } else {
                    $updates[] = "salary = ?";
                    $params[] = $salary;
                }
            }
        
        if ($phoneProvided) {
            if ($phoneSetNull) {
                $updates[] = "phone = NULL";
            } else {
                $updates[] = "phone = ?";
                $params[] = $phone;
            }
        }
        
        if ($payrollPhoneProvided) {
            if ($payrollPhoneSetNull) {
                $updates[] = "payroll_phone = NULL";
            } else {
                $updates[] = "payroll_phone = ?";
                $params[] = $payrollPhone;
            }
        }
        
        if ($payrollBankProvided) {
            if ($payrollBankSetNull) {
                $updates[] = "payroll_bank = NULL";
            } else {
                $updates[] = "payroll_bank = ?";
                $params[] = $payrollBank;
            }
        }
            
            if ($is_active !== null) {
                $updates[] = "is_active = ?";
                $params[] = $is_active;
            }
            
            if (empty($updates)) {
                throw new Exception('Нет данных для обновления');
            }
            
            // Получаем старые данные для логирования
            $stmt = $pdo->prepare("SELECT login, user_type, full_name, email, salary, phone, payroll_phone, payroll_bank, is_active FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $oldUser = $stmt->fetch();
            
            $params[] = $id;
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Формируем данные об изменениях
            $changes = [];
            if ($user_type !== null && $oldUser['user_type'] !== $user_type) {
                $changes['user_type'] = ['old' => $oldUser['user_type'], 'new' => $user_type];
            }
            if ($full_name !== '' && $oldUser['full_name'] !== $full_name) {
                $changes['full_name'] = ['old' => $oldUser['full_name'], 'new' => $full_name];
            }
            if ($email !== '' && $oldUser['email'] !== $email) {
                $changes['email'] = ['old' => $oldUser['email'], 'new' => $email];
            }
            if ($salaryProvided) {
                $oldSalary = $oldUser['salary'] !== null ? (float)$oldUser['salary'] : null;
                $newSalary = $salarySetNull ? null : $salary;
                if ($oldSalary !== $newSalary) {
                    $changes['salary'] = ['old' => $oldSalary, 'new' => $newSalary];
                }
            }
        if ($phoneProvided) {
            $oldPhone = $oldUser['phone'] ?? null;
            $newPhone = $phoneSetNull ? null : $phone;
            if ($oldPhone !== $newPhone) {
                $changes['phone'] = ['old' => $oldPhone, 'new' => $newPhone];
            }
        }
        if ($payrollPhoneProvided) {
            $oldPayPhone = $oldUser['payroll_phone'] ?? null;
            $newPayPhone = $payrollPhoneSetNull ? null : $payrollPhone;
            if ($oldPayPhone !== $newPayPhone) {
                $changes['payroll_phone'] = ['old' => $oldPayPhone, 'new' => $newPayPhone];
            }
        }
        if ($payrollBankProvided) {
            $oldPayBank = $oldUser['payroll_bank'] ?? null;
            $newPayBank = $payrollBankSetNull ? null : $payrollBank;
            if ($oldPayBank !== $newPayBank) {
                $changes['payroll_bank'] = ['old' => $oldPayBank, 'new' => $newPayBank];
            }
        }
            if ($is_active !== null && $oldUser['is_active'] != $is_active) {
                $changes['is_active'] = ['old' => (bool)$oldUser['is_active'], 'new' => (bool)$is_active];
            }
            if ($password) {
                $changes['password'] = ['changed' => true];
            }
            
            // Логирование с данными об изменениях
            if (!empty($changes)) {
                logActivity('update', 'user', $id, "Обновлен пользователь: {$oldUser['login']}", $changes);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Пользователь успешно обновлен'
            ]);
            break;
            
        case 'delete':
            // Мягкое удаление пользователя (soft delete)
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID пользователя не указан');
            }
            
            // Защита от удаления самого себя
            if ($id == getCurrentUserId()) {
                throw new Exception('Нельзя удалить свой собственный аккаунт');
            }
            
            // Проверка существования (исключая уже удаленных)
            $stmt = $pdo->prepare("SELECT id, login FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Пользователь не найден или уже удален');
            }
            
            // Мягкое удаление - устанавливаем deleted_at
            $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            // Логирование
            $changes = [
                'login' => $user['login'],
                'deleted_at' => date('Y-m-d H:i:s')
            ];
            logActivity('delete', 'user', $id, "Удален пользователь: {$user['login']}", $changes);
            
            echo json_encode([
                'success' => true,
                'message' => 'Пользователь успешно удален'
            ]);
            break;
            
        case 'toggle_active':
            // Блокировка/разблокировка пользователя
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID пользователя не указан');
            }
            
            // Защита от блокировки самого себя
            if ($id == getCurrentUserId()) {
                throw new Exception('Нельзя заблокировать свой собственный аккаунт');
            }
            
            // Получение текущего статуса (исключая удаленных)
            $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Пользователь не найден');
            }
            
            $newStatus = $user['is_active'] ? 0 : 1;
            
            // Обновление статуса
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
            
            echo json_encode([
                'success' => true,
                'message' => $newStatus ? 'Пользователь разблокирован' : 'Пользователь заблокирован',
                'is_active' => $newStatus
            ]);
            break;
            
        default:
            throw new Exception('Неизвестное действие');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
}
