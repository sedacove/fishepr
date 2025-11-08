-- Миграция 006: Создание таблицы для замеров
-- Дата создания: 2025-11-06
-- Описание: Таблица для хранения замеров температуры и кислорода в бассейнах

-- Таблица замеров
CREATE TABLE IF NOT EXISTS `measurements` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pool_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID бассейна',
  `temperature` DECIMAL(5,2) NOT NULL COMMENT 'Температура (°C)',
  `oxygen` DECIMAL(5,2) NOT NULL COMMENT 'Количество растворенного кислорода (O2)',
  `measured_at` DATETIME NOT NULL COMMENT 'Дата и время замера',
  `created_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто сделал замер',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pool_id` (`pool_id`),
  INDEX `idx_measured_at` (`measured_at`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`pool_id`) REFERENCES `pools`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
