CREATE TABLE IF NOT EXISTS meters (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_created_by (created_by),
    CONSTRAINT fk_meters_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meter_readings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    meter_id INT UNSIGNED NOT NULL,
    reading_value DECIMAL(14,4) NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recorded_by INT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_meter_id (meter_id),
    INDEX idx_recorded_by (recorded_by),
    INDEX idx_recorded_at (recorded_at),
    CONSTRAINT fk_meter_readings_meter FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    CONSTRAINT fk_meter_readings_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (`key`, `value`, `description`)
SELECT 'meter_reading_edit_timeout_minutes', '30', 'Тайм-аут (в минутах) на редактирование показаний приборов учета пользователем'
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE `key` = 'meter_reading_edit_timeout_minutes'
);

