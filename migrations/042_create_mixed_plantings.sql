-- Миграция 042: Создание таблиц для микстовых посадок
-- Дата создания: 2025-01-XX
-- Описание: Таблицы для управления микстовыми посадками (смешанными посадками из разных чистых посадок)

-- Таблица микстовых посадок
CREATE TABLE IF NOT EXISTS `mixed_plantings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Название микстовой посадки',
  `fish_breed` VARCHAR(255) DEFAULT NULL COMMENT 'Основная порода рыбы (если все компоненты одной породы)',
  `created_by` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Кто создал микстовую посадку',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Таблица микстовых посадок';

-- Таблица компонентов микстовых посадок
CREATE TABLE IF NOT EXISTS `mixed_planting_components` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mixed_planting_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID микстовой посадки',
  `planting_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID чистой посадки (компонента)',
  `percentage` DECIMAL(5, 2) NOT NULL COMMENT 'Процентное соотношение (0-100)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_mixed_planting` (`mixed_planting_id`),
  INDEX `idx_planting` (`planting_id`),
  FOREIGN KEY (`mixed_planting_id`) REFERENCES `mixed_plantings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`planting_id`) REFERENCES `plantings`(`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uk_mixed_planting_planting` (`mixed_planting_id`, `planting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Компоненты микстовых посадок';

-- Добавляем поле mixed_planting_id в таблицу sessions
ALTER TABLE `sessions`
  ADD COLUMN `mixed_planting_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID микстовой посадки (если используется микстовая посадка)' AFTER `planting_id`,
  ADD INDEX `idx_mixed_planting_id` (`mixed_planting_id`),
  ADD CONSTRAINT `fk_sessions_mixed_planting_id` FOREIGN KEY (`mixed_planting_id`) REFERENCES `mixed_plantings`(`id`) ON DELETE RESTRICT;

