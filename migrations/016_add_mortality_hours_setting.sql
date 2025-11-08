-- Добавляем настройку для расчета падежа за последние N часов
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('mortality_calculation_hours', '24', 'Количество часов для расчета падежа на рабочей странице')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

