-- Миграция 005: Добавление поля для хранения изменений в логах
-- Дата создания: 2025-11-06
-- Описание: Добавление поля changes для хранения JSON с информацией об измененных данных

ALTER TABLE `activity_log` 
ADD COLUMN `changes` TEXT DEFAULT NULL COMMENT 'JSON с информацией об измененных данных' AFTER `description`;
