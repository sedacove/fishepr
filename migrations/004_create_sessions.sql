-- Миграция 004: Создание таблицы для сессий
-- Дата создания: 2025-11-06
-- Описание: Таблица для управления сессиями (циклами выращивания рыбы в бассейнах)

-- Таблица сессий
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `pool_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID бассейна',
  `planting_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID посадки',
  `start_date` DATE NOT NULL COMMENT 'Дата начала',
  `end_date` DATE DEFAULT NULL COMMENT 'Дата окончания',
  `start_mass` DECIMAL(10,2) NOT NULL COMMENT 'Масса посадки (кг)',
  `start_fish_count` INT(11) UNSIGNED NOT NULL COMMENT 'Количество рыб (шт)',
  `end_mass` DECIMAL(10,2) DEFAULT NULL COMMENT 'Масса в конце (кг)',
  `feed_amount` DECIMAL(10,2) DEFAULT NULL COMMENT 'Внесено корма (кг)',
  `fcr` DECIMAL(8,4) DEFAULT NULL COMMENT 'FCR (Feed Conversion Ratio)',
  `is_completed` TINYINT(1) DEFAULT 0 COMMENT 'Завершена',
  `created_by` INT(11) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pool_id` (`pool_id`),
  INDEX `idx_planting_id` (`planting_id`),
  INDEX `idx_is_completed` (`is_completed`),
  INDEX `idx_start_date` (`start_date`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`pool_id`) REFERENCES `pools`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`planting_id`) REFERENCES `plantings`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
