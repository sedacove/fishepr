ALTER TABLE `users`
    ADD COLUMN `phone` VARCHAR(20) NULL DEFAULT NULL AFTER `salary`,
    ADD COLUMN `payroll_phone` VARCHAR(20) NULL DEFAULT NULL AFTER `phone`,
    ADD COLUMN `payroll_bank` VARCHAR(255) NULL DEFAULT NULL AFTER `payroll_phone`;

