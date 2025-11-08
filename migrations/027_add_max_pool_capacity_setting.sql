-- Миграция 027: Добавление настройки максимального заполнения бассейна
-- Дата создания: 2025-11-08

INSERT INTO `settings` (`key`, `value`, `description`)
VALUES ('max_pool_capacity_kg', '5000', 'Максимальное заполнение бассейна (кг)')
ON DUPLICATE KEY UPDATE
    `description` = VALUES(`description`);


