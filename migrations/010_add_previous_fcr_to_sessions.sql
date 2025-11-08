-- Миграция 010: Добавление поля previous_fcr в таблицу sessions
-- Дата создания: 2025-01-XX
-- Описание: Добавление поля для хранения прошлого FCR при создании/редактировании сессии

ALTER TABLE `sessions` 
ADD COLUMN `previous_fcr` DECIMAL(8,4) DEFAULT NULL COMMENT 'Прошлый FCR' AFTER `start_fish_count`;

