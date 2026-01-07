-- Миграция 044: Добавление колонки session_id в таблицу weighings
-- Дата создания: 2025-12-25
-- Описание: Добавление session_id для связи с сессиями, pool_id остается для обратной совместимости
-- 
-- После выполнения этой миграции нужно:
-- 1. Запустить скрипт migrate_weighings_to_sessions.php для заполнения session_id
-- 2. Обновить код приложения для использования session_id вместо pool_id
-- 
-- pool_id остается в таблице для обратной совместимости и может быть получен через JOIN с sessions

-- Шаг 1: Добавляем колонку session_id в таблицу weighings
ALTER TABLE `weighings`
ADD COLUMN `session_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID сессии' AFTER `pool_id`,
ADD INDEX `idx_session_id` (`session_id`);

-- Шаг 2: После заполнения session_id через скрипт миграции данных делаем его обязательным
-- (выполнить после migrate_weighings_to_sessions.php)
-- ALTER TABLE `weighings` MODIFY COLUMN `session_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID сессии';

-- Шаг 3: Добавляем внешний ключ на sessions
ALTER TABLE `weighings`
ADD CONSTRAINT `fk_weighings_session_id` FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE RESTRICT;



