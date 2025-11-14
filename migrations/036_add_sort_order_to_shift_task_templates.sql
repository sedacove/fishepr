ALTER TABLE `shift_task_templates`
    ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `description`;

UPDATE `shift_task_templates`
SET sort_order = id
WHERE sort_order = 0;

