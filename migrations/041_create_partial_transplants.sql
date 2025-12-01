-- Миграция 041: Создание таблицы частичных пересадок
-- Дата создания: 2025-01-XX
-- Описание: Таблица для хранения записей о частичных пересадках биомассы между сессиями

CREATE TABLE IF NOT EXISTS `partial_transplants` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transplant_date` DATE NOT NULL COMMENT 'Дата пересадки',
  `source_session_id` INT(11) UNSIGNED NOT NULL COMMENT 'Сессия отбора (источник)',
  `recipient_session_id` INT(11) UNSIGNED NOT NULL COMMENT 'Сессия реципиент (получатель)',
  `weight` DECIMAL(10, 2) NOT NULL COMMENT 'Вес пересаженной биомассы (кг)',
  `fish_count` INT(11) UNSIGNED NOT NULL COMMENT 'Количество пересаженных особей',
  `is_reverted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Флаг отката пересадки (1 = откат выполнен)',
  `reverted_by` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Кто выполнил откат',
  `reverted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Дата и время отката',
  `created_by` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Кто создал запись',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_source_session` (`source_session_id`),
  INDEX `idx_recipient_session` (`recipient_session_id`),
  INDEX `idx_transplant_date` (`transplant_date`),
  INDEX `idx_is_reverted` (`is_reverted`),
  FOREIGN KEY (`source_session_id`) REFERENCES `sessions`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`recipient_session_id`) REFERENCES `sessions`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reverted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Таблица частичных пересадок биомассы между сессиями';

