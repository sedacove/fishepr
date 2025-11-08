INSERT INTO `settings` (`key`, `value`, `description`)
VALUES 
('payroll_advance_day', '15', 'День месяца, когда выплачивается аванс'),
('payroll_salary_day', '30', 'День месяца, когда выплачивается зарплата')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

