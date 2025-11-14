INSERT INTO `settings` (`key`, `value`, `description`, `updated_at`)
VALUES ('debug_mode', '0', 'Флаг включения режима отладки (1 = включен)', NOW())
ON DUPLICATE KEY UPDATE `value` = `value`;

