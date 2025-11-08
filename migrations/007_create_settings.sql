-- Миграция 007: Создание таблицы настроек
-- Дата создания: 2025-11-06
-- Описание: Таблица для хранения системных настроек

-- Таблица настроек
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL COMMENT 'Ключ настройки',
  `value` TEXT NOT NULL COMMENT 'Значение настройки',
  `description` VARCHAR(255) DEFAULT NULL COMMENT 'Описание настройки',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Кто обновил настройку',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`),
  INDEX `idx_updated_by` (`updated_by`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставляем начальные настройки
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('measurement_edit_timeout_minutes', '30', 'Время в минутах, в течение которого пользователь может редактировать свой замер после создания')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
