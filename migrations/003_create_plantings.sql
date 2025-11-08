-- Миграция 003: Создание таблиц для посадок рыбы
-- Дата создания: 2025-11-06
-- Описание: Таблицы для управления посадками рыбы и их файлами

-- Таблица посадок
CREATE TABLE IF NOT EXISTS `plantings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `fish_breed` VARCHAR(255) NOT NULL COMMENT 'Порода рыбы',
  `hatch_date` DATE DEFAULT NULL COMMENT 'Дата вылупа',
  `planting_date` DATE NOT NULL COMMENT 'Дата посадки',
  `fish_count` INT(11) UNSIGNED NOT NULL COMMENT 'Количество рыб',
  `biomass_weight` DECIMAL(10,2) DEFAULT NULL COMMENT 'Вес биомассы (кг)',
  `supplier` VARCHAR(255) DEFAULT NULL COMMENT 'Поставщик',
  `price` DECIMAL(12,2) DEFAULT NULL COMMENT 'Цена (рубли)',
  `delivery_cost` DECIMAL(12,2) DEFAULT NULL COMMENT 'Стоимость доставки (рубли)',
  `is_archived` TINYINT(1) DEFAULT 0 COMMENT 'Архивировано',
  `created_by` INT(11) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_is_archived` (`is_archived`),
  INDEX `idx_planting_date` (`planting_date`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица файлов посадок
CREATE TABLE IF NOT EXISTS `planting_files` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `planting_id` INT(11) UNSIGNED NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT(11) UNSIGNED NOT NULL COMMENT 'Размер в байтах',
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `uploaded_by` INT(11) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_planting_id` (`planting_id`),
  INDEX `idx_uploaded_by` (`uploaded_by`),
  FOREIGN KEY (`planting_id`) REFERENCES `plantings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
