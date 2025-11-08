INSERT INTO `settings` (`key`, `value`, `description`)
VALUES ('show_section_descriptions', '1', 'Отображать информационные блоки с описанием разделов')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

