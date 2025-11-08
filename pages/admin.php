<?php
/**
 * Страница администратора
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireAdmin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/section_descriptions.php';

$page_title = 'Администрирование';
?>
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Панель администратора</h1>
            <?php renderSectionDescription('users_admin'); ?>
            
            <div class="alert alert-info">
                <h5>Доступ только для администраторов</h5>
                <p class="mb-0">Здесь будут размещены функции управления системой, пользователями и настройками.</p>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Управление пользователями</h5>
                    <p class="card-text">Функционал управления пользователями будет добавлен позже.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
