-- Миграция 011: Создание таблицы для навесок
-- Дата создания: 2025-01-XX
-- Описание: Таблица для хранения навесок (взвешиваний рыб из бассейнов)

-- Таблица навесок
CREATE TABLE IF NOT EXISTS `weighings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pool_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID бассейна',
  `weight` DECIMAL(10,2) NOT NULL COMMENT 'Вес (кг)',
  `fish_count` INT(11) UNSIGNED NOT NULL COMMENT 'Количество рыб (шт)',
  `recorded_at` DATETIME NOT NULL COMMENT 'Дата и время записи',
  `created_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто сделал запись',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pool_id` (`pool_id`),
  INDEX `idx_recorded_at` (`recorded_at`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`pool_id`) REFERENCES `pools`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Добавляем настройку для таймаута редактирования навесок
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('weighing_edit_timeout_minutes', '30', 'Время в минутах, в течение которого пользователь может редактировать свою навеску после создания')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

