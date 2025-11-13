<?php

use App\Support\View;

View::extends('layouts.app');

$config = $usersConfig ?? [
    'baseUrl' => BASE_URL,
];

require_once __DIR__ . '/../../../includes/section_descriptions.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1>Управление пользователями</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Добавить пользователя
            </button>
        </div>
    </div>

    <?php renderSectionDescription('users'); ?>

    <div id="alert-container"></div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>ФИО</th>
                            <th>Телефон</th>
                            <th>Зарплата</th>
                            <th>Тип</th>
                            <th>Статус</th>
                            <th>Создан</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr>
                            <td colspan="9" class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Загрузка...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Добавить пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userId" name="id">

                    <div class="mb-3">
                        <label for="userLogin" class="form-label">Логин <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="userLogin" name="login" required>
                    </div>

                    <div class="mb-3">
                        <label for="userPassword" class="form-label">
                            Пароль <span class="text-danger">*</span>
                            <small class="text-muted" id="passwordHint">(минимум 6 символов)</small>
                        </label>
                        <input type="password" class="form-control" id="userPassword" name="password" minlength="6">
                    </div>

                    <div class="mb-3">
                        <label for="userType" class="form-label">Тип пользователя <span class="text-danger">*</span></label>
                        <select class="form-select" id="userType" name="user_type" required>
                            <option value="user">Пользователь</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="userFullName" class="form-label">ФИО</label>
                        <input type="text" class="form-control" id="userFullName" name="full_name">
                    </div>

                    <div class="mb-3">
                        <label for="userEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="userEmail" name="email">
                    </div>

                    <div class="mb-3">
                        <label for="userPhone" class="form-label">Телефон</label>
                        <input type="tel" class="form-control" id="userPhone" name="phone" placeholder="+7 (___) ___-__-__">
                        <small class="text-muted">Укажите номер в формате +7 (XXX) XXX-XX-XX</small>
                    </div>

                    <div class="mb-3">
                        <label for="userPayrollPhone" class="form-label">Телефон для зарплаты</label>
                        <input type="tel" class="form-control" id="userPayrollPhone" name="payroll_phone" placeholder="+7 (___) ___-__-__">
                        <small class="text-muted">Номер распределения зарплаты, формат +7 (XXX) XXX-XX-XX</small>
                    </div>

                    <div class="mb-3">
                        <label for="userPayrollBank" class="form-label">Банк для зарплаты</label>
                        <input type="text" class="form-control" id="userPayrollBank" name="payroll_bank" maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label for="userSalary" class="form-label">Зарплата (₽)</label>
                        <input type="number" class="form-control" id="userSalary" name="salary" min="0" step="0.01">
                        <small class="text-muted">Укажите сумму в рублях, например 45000 или 45000.50</small>
                    </div>

                    <div class="mb-3" id="isActiveContainer" style="display: none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="userIsActive" name="is_active" value="1">
                            <label class="form-check-label" for="userIsActive">
                                Активен
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.usersConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>


