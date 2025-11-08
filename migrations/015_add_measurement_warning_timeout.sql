-- Добавляем настройку для времени предупреждения о просроченных замерах
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('measurement_warning_timeout_minutes', '60', 'Время в минутах, после которого замер считается просроченным (для предупреждения на рабочей странице)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

