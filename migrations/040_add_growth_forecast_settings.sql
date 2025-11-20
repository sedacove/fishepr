-- Настройки для формулы прогноза роста рыбы
-- Формула: W(t) = max_weight / (1 + exp(-coefficient * (t - inflection_point)))
-- где t - возраст в днях, W(t) - вес в граммах

INSERT INTO `settings` (`key`, `value`, `description`, `updated_at`) VALUES
('growth_forecast_max_weight', '2500', 'Максимальный целевой вес рыбы в граммах для идеальной кривой роста', NOW()),
('growth_forecast_coefficient', '0.015', 'Коэффициент роста в формуле прогноза (0.015)', NOW()),
('growth_forecast_inflection_point', '220', 'Точка перегиба кривой роста в днях (220)', NOW())
ON DUPLICATE KEY UPDATE 
    `description` = VALUES(`description`), 
    `updated_at` = NOW();

