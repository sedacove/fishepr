INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('telegram_bot_token', '', 'Токен Telegram-бота, используемый для отправки системных уведомлений.'),
('telegram_chat_ids', '', 'Список chat_id (через запятую) для получения Telegram-уведомлений.'),
('mortality_alert_threshold', '0', 'Порог количества рыб (шт) для уведомления о падеже.')
ON DUPLICATE KEY UPDATE
`value` = VALUES(`value`),
`description` = VALUES(`description`);


