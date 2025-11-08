-- Добавляем настройки для пороговых значений падежа (в штуках)
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('mortality_threshold_green', '5', 'Порог падежа (шт) для зеленого цвета - меньше или равно'),
('mortality_threshold_yellow', '10', 'Порог падежа (шт) для желтого цвета - меньше или равно')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

