-- Миграция 012: Создание таблицы для календаря дежурств
-- Дата создания: 2025-01-XX
-- Описание: Таблица для хранения расписания дежурств

-- Таблица дежурств
CREATE TABLE IF NOT EXISTS `duty_schedule` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL COMMENT 'Дата дежурства',
  `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID дежурного',
  `created_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто назначил',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_date` (`date`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_date` (`date`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

