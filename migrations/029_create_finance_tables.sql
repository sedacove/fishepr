-- Миграция 029: Создание таблиц доходов и расходов
-- Дата создания: 2025-11-08

CREATE TABLE IF NOT EXISTS `finance_expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_date` DATE NOT NULL COMMENT 'Дата расхода',
  `title` VARCHAR(255) NOT NULL COMMENT 'Цель расхода',
  `amount` DECIMAL(12,2) NOT NULL COMMENT 'Сумма расхода',
  `comment` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_finance_expenses_date` (`record_date`),
  INDEX `idx_finance_expenses_created_by` (`created_by`),
  CONSTRAINT `fk_finance_expenses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `finance_incomes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_date` DATE NOT NULL COMMENT 'Дата прихода',
  `title` VARCHAR(255) NOT NULL COMMENT 'Источник дохода',
  `amount` DECIMAL(12,2) NOT NULL COMMENT 'Сумма дохода',
  `comment` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_finance_incomes_date` (`record_date`),
  INDEX `idx_finance_incomes_created_by` (`created_by`),
  CONSTRAINT `fk_finance_incomes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


