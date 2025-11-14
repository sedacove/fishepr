-- Создание таблиц для заданий смены

CREATE TABLE IF NOT EXISTS `shift_task_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `frequency` ENUM('daily','weekly','biweekly','monthly') NOT NULL DEFAULT 'daily',
    `start_date` DATE NOT NULL,
    `week_day` TINYINT NULL COMMENT '0=Sunday, 6=Saturday',
    `day_of_month` TINYINT NULL,
    `due_time` TIME NOT NULL DEFAULT '12:00:00',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED NOT NULL,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_shift_task_templates_active` (`is_active`),
    INDEX `idx_shift_task_templates_freq` (`frequency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_task_instances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` INT UNSIGNED NOT NULL,
    `shift_date` DATE NOT NULL,
    `due_at` DATETIME NOT NULL,
    `status` ENUM('pending','completed','missed') NOT NULL DEFAULT 'pending',
    `completed_at` DATETIME DEFAULT NULL,
    `completed_by` INT UNSIGNED DEFAULT NULL,
    `note` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_template_shift_date` (`template_id`, `shift_date`),
    INDEX `idx_shift_task_instances_shift_date` (`shift_date`),
    INDEX `idx_shift_task_instances_status` (`status`),
    CONSTRAINT `fk_shift_task_instances_template`
        FOREIGN KEY (`template_id`) REFERENCES `shift_task_templates` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


