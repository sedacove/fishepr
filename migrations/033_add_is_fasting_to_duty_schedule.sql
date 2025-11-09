ALTER TABLE duty_schedule
    ADD COLUMN is_fasting TINYINT(1) NOT NULL DEFAULT 0 AFTER user_id;

