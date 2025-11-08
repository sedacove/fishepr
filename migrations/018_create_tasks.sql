-- Миграция 018: Создание таблиц для задач
-- Дата создания: 2025-01-XX
-- Описание: Таблицы для управления задачами с чеклистами

-- Таблица задач
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL COMMENT 'Название задачи',
  `description` TEXT DEFAULT NULL COMMENT 'Описание задачи',
  `assigned_to` INT(11) UNSIGNED NOT NULL COMMENT 'Ответственный',
  `created_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто создал задачу',
  `due_date` DATE DEFAULT NULL COMMENT 'Срок выполнения',
  `is_completed` TINYINT(1) DEFAULT 0 COMMENT 'Задача выполнена',
  `completed_at` DATETIME DEFAULT NULL COMMENT 'Дата выполнения',
  `completed_by` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Кто выполнил задачу',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_assigned_to` (`assigned_to`),
  INDEX `idx_created_by` (`created_by`),
  INDEX `idx_is_completed` (`is_completed`),
  INDEX `idx_due_date` (`due_date`),
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица элементов чеклиста (подзадач)
CREATE TABLE IF NOT EXISTS `task_items` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID задачи',
  `title` VARCHAR(255) NOT NULL COMMENT 'Название элемента',
  `is_completed` TINYINT(1) DEFAULT 0 COMMENT 'Элемент выполнен',
  `completed_at` DATETIME DEFAULT NULL COMMENT 'Дата выполнения',
  `completed_by` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Кто выполнил элемент',
  `sort_order` INT(11) DEFAULT 0 COMMENT 'Порядок сортировки',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_task_id` (`task_id`),
  INDEX `idx_is_completed` (`is_completed`),
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица файлов задач
CREATE TABLE IF NOT EXISTS `task_files` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID задачи',
  `original_name` VARCHAR(255) NOT NULL COMMENT 'Оригинальное имя файла',
  `file_name` VARCHAR(255) NOT NULL COMMENT 'Имя файла на сервере',
  `file_path` VARCHAR(500) NOT NULL COMMENT 'Путь к файлу',
  `file_size` INT(11) UNSIGNED NOT NULL COMMENT 'Размер в байтах',
  `mime_type` VARCHAR(100) DEFAULT NULL COMMENT 'MIME тип',
  `uploaded_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто загрузил файл',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_task_id` (`task_id`),
  INDEX `idx_uploaded_by` (`uploaded_by`),
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

