-- Миграция 026: Добавление контрагента к отборам
-- Дата создания: 2025-11-08
-- Описание: Добавляет ссылку на контрагента к таблице отборов

ALTER TABLE `harvests`
    ADD COLUMN `counterparty_id` INT UNSIGNED NULL AFTER `fish_count`,
    ADD INDEX `idx_harvests_counterparty_id` (`counterparty_id`),
    ADD CONSTRAINT `fk_harvests_counterparty` FOREIGN KEY (`counterparty_id`) REFERENCES `counterparties`(`id`) ON DELETE SET NULL;


