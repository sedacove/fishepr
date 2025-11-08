-- Миграция 028: Настройка предупреждения по навескам
-- Дата создания: 2025-11-08

INSERT INTO `settings` (`key`, `value`, `description`)
VALUES ('weighing_warning_days', '3', 'Предупреждение, если навесок не было N суток')
ON DUPLICATE KEY UPDATE
    `description` = VALUES(`description`);


