-- Добавляем настройки для нормальных значений температуры
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('temp_bad_below', '10', 'Температура: ниже этого значения - плохо (°C)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('temp_acceptable_min', '10', 'Температура: начало допустимого диапазона (°C)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('temp_good_min', '14', 'Температура: начало хорошего диапазона (°C)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('temp_good_max', '17', 'Температура: конец хорошего диапазона (°C)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('temp_acceptable_max', '20', 'Температура: конец допустимого диапазона (°C)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('temp_bad_above', '20', 'Температура: выше этого значения - плохо (°C)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Добавляем настройки для нормальных значений кислорода
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('oxygen_bad_below', '8', 'Кислород: ниже этого значения - плохо (мг/л)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('oxygen_acceptable_min', '8', 'Кислород: начало допустимого диапазона (мг/л)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('oxygen_good_min', '11', 'Кислород: начало хорошего диапазона (мг/л)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('oxygen_good_max', '16', 'Кислород: конец хорошего диапазона (мг/л)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('oxygen_acceptable_max', '20', 'Кислород: конец допустимого диапазона (мг/л)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('oxygen_bad_above', '20', 'Кислород: выше этого значения - плохо (мг/л)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

