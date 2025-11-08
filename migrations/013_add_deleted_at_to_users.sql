-- Миграция 013: Добавление поля deleted_at для soft delete пользователей
-- Дата создания: 2025-01-XX
-- Описание: Добавление поля deleted_at в таблицу users для мягкого удаления

ALTER TABLE `users` 
ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_active`,
ADD INDEX `idx_deleted_at` (`deleted_at`);

