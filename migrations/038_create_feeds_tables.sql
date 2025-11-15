CREATE TABLE IF NOT EXISTS `feeds` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `granule` VARCHAR(255) DEFAULT NULL,
    `formula_econom` TEXT DEFAULT NULL,
    `formula_normal` TEXT DEFAULT NULL,
    `formula_growth` TEXT DEFAULT NULL,
    `manufacturer` VARCHAR(255) DEFAULT NULL,
    `created_by` INT(11) UNSIGNED DEFAULT NULL,
    `updated_by` INT(11) UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_feeds_name` (`name`),
    CONSTRAINT `fk_feeds_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_feeds_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feed_norm_images` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `feed_id` INT(11) UNSIGNED NOT NULL,
    `original_name` VARCHAR(255) DEFAULT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `file_size` INT(11) UNSIGNED DEFAULT 0,
    `mime_type` VARCHAR(128) DEFAULT NULL,
    `uploaded_by` INT(11) UNSIGNED DEFAULT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_feed_norm_images_feed_id` (`feed_id`),
    CONSTRAINT `fk_feed_norm_images_feed_id` FOREIGN KEY (`feed_id`) REFERENCES `feeds`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_feed_norm_images_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

