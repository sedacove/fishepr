CREATE TABLE IF NOT EXISTS `extra_works` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `work_date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_paid` TINYINT(1) NOT NULL DEFAULT 0,
  `paid_at` DATETIME NULL,
  `paid_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_extra_works_created_by` (`created_by`),
  INDEX `idx_extra_works_paid_by` (`paid_by`),
  INDEX `idx_extra_works_work_date` (`work_date`),
  INDEX `idx_extra_works_is_paid` (`is_paid`),
  CONSTRAINT `fk_extra_works_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_extra_works_paid_by` FOREIGN KEY (`paid_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

