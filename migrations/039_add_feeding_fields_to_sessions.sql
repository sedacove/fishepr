ALTER TABLE `sessions`
    ADD COLUMN `daily_feedings` TINYINT(2) UNSIGNED NOT NULL DEFAULT 3 AFTER `previous_fcr`,
    ADD COLUMN `feed_id` INT(11) UNSIGNED DEFAULT NULL AFTER `daily_feedings`,
    ADD COLUMN `feeding_strategy` ENUM('econom', 'normal', 'growth') NOT NULL DEFAULT 'normal' AFTER `feed_id`,
    ADD CONSTRAINT `fk_sessions_feed_id` FOREIGN KEY (`feed_id`) REFERENCES `feeds`(`id`) ON DELETE SET NULL;

