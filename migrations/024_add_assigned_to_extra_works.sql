ALTER TABLE `extra_works`
    ADD COLUMN `assigned_to` INT UNSIGNED NOT NULL AFTER `amount`,
    ADD CONSTRAINT `fk_extra_works_assigned_to`
        FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE RESTRICT;

CREATE INDEX `idx_extra_works_assigned_to` ON `extra_works` (`assigned_to`);

