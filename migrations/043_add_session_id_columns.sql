-- Миграция 043: Добавление колонки session_id в таблицы harvests и mortality
-- Дата создания: 2025-01-XX
-- Описание: Добавление session_id для связи с сессиями, pool_id остается для обратной совместимости
-- 
-- После выполнения этой миграции нужно:
-- 1. Запустить скрипт migrate_harvests_mortality_to_sessions.php для заполнения session_id
-- 2. Обновить код приложения для использования session_id вместо pool_id
-- 
-- pool_id остается в таблице для обратной совместимости и может быть получен через JOIN с sessions

-- Шаг 1: Добавляем колонку session_id в таблицу harvests
ALTER TABLE `harvests`
ADD COLUMN `session_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID сессии' AFTER `pool_id`,
ADD INDEX `idx_session_id` (`session_id`);

-- Шаг 2: Добавляем колонку session_id в таблицу mortality
ALTER TABLE `mortality`
ADD COLUMN `session_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID сессии' AFTER `pool_id`,
ADD INDEX `idx_session_id` (`session_id`);

-- Шаг 3: После заполнения session_id через скрипт миграции данных делаем его обязательным
-- (выполнить после migrate_harvests_mortality_to_sessions.php)
-- ALTER TABLE `harvests` MODIFY COLUMN `session_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID сессии';
-- ALTER TABLE `mortality` MODIFY COLUMN `session_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID сессии';

-- Шаг 4: Добавляем внешние ключи на sessions
ALTER TABLE `harvests`
ADD CONSTRAINT `fk_harvests_session_id` FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE RESTRICT;

ALTER TABLE `mortality`
ADD CONSTRAINT `fk_mortality_session_id` FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE RESTRICT;

