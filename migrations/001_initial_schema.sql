-- Миграция 001: Начальная схема базы данных
-- Дата создания: 2025-11-06
-- Описание: Создание таблицы users и начальных данных

-- Таблица пользователей
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `user_type` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  `full_name` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  INDEX `idx_login` (`login`),
  INDEX `idx_user_type` (`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставка тестового администратора (логин: admin, пароль: password)
-- ВАЖНО: В продакшене измените пароль!
INSERT IGNORE INTO `users` (`login`, `password`, `user_type`, `full_name`, `email`) VALUES
('admin', '$2y$10$bP/VBUwrrZRPT72OVJfVIOYyMYh0RP79s2rX56W3hf6WOL2t4Yqr.', 'admin', 'Администратор', 'admin@example.com');

-- Вставка тестового пользователя (логин: user, пароль: password)
INSERT IGNORE INTO `users` (`login`, `password`, `user_type`, `full_name`, `email`) VALUES
('user', '$2y$10$bP/VBUwrrZRPT72OVJfVIOYyMYh0RP79s2rX56W3hf6WOL2t4Yqr.', 'user', 'Пользователь', 'user@example.com');
