-- Миграция 025: Создание таблиц для контрагентов
-- Дата создания: 2025-11-08
-- Описание: Справочник контрагентов и их документы

CREATE TABLE IF NOT EXISTS `counterparties` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Название контрагента',
  `description` TEXT NULL COMMENT 'Описание',
  `inn` VARCHAR(12) NULL COMMENT 'ИНН',
  `phone` VARCHAR(20) NULL COMMENT 'Телефон в формате +7XXXXXXXXXX',
  `email` VARCHAR(255) NULL COMMENT 'Email',
  `color` VARCHAR(20) NULL COMMENT 'Цвет из предустановленной палитры',
  `created_by` INT UNSIGNED NOT NULL COMMENT 'Создал пользователь',
  `updated_by` INT UNSIGNED NULL COMMENT 'Последний изменивший пользователь',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_counterparties_name` (`name`),
  INDEX `idx_counterparties_inn` (`inn`),
  INDEX `idx_counterparties_created_by` (`created_by`),
  INDEX `idx_counterparties_updated_by` (`updated_by`),
  CONSTRAINT `fk_counterparties_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_counterparties_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `counterparty_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `counterparty_id` INT UNSIGNED NOT NULL COMMENT 'ID контрагента',
  `original_name` VARCHAR(255) NOT NULL COMMENT 'Имя файла при загрузке',
  `file_name` VARCHAR(255) NOT NULL COMMENT 'Имя файла на сервере',
  `file_path` VARCHAR(500) NOT NULL COMMENT 'Относительный путь к файлу',
  `file_size` INT UNSIGNED NOT NULL COMMENT 'Размер файла в байтах',
  `mime_type` VARCHAR(150) NULL COMMENT 'MIME-тип файла',
  `uploaded_by` INT UNSIGNED NOT NULL COMMENT 'Кто загрузил файл',
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_counterparty_documents_counterparty_id` (`counterparty_id`),
  INDEX `idx_counterparty_documents_uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_counterparty_documents_counterparty` FOREIGN KEY (`counterparty_id`) REFERENCES `counterparties`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_counterparty_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


