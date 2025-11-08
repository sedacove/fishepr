-- Миграция 009: Создание таблицы для отборов
-- Дата создания: 2025-11-06
-- Описание: Таблица для хранения данных об отборе рыбы из бассейнов

-- Таблица отборов
CREATE TABLE IF NOT EXISTS `harvests` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pool_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID бассейна',
  `weight` DECIMAL(8,2) NOT NULL COMMENT 'Вес (кг)',
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
