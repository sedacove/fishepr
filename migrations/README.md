# Система миграций базы данных

Система миграций позволяет управлять изменениями структуры базы данных версионированным способом.

## Структура

- `migrate.php` - скрипт для запуска миграций
- `001_initial_schema.sql` - первая миграция (начальная схема БД)
- `migrations` - таблица в БД для отслеживания выполненных миграций

## Использование

### Запуск всех новых миграций

```bash
php migrations/migrate.php run
```

или просто:

```bash
php migrations/migrate.php
```

### Просмотр статуса миграций

```bash
php migrations/migrate.php status
```

## Создание новой миграции

1. Создайте новый файл в папке `migrations/` с именем:
   ```
   XXX_description.sql
   ```
   где `XXX` - порядковый номер (001, 002, 003 и т.д.), `description` - краткое описание на английском

2. Пример имени файла:
   ```
   002_add_products_table.sql
   003_add_orders_table.sql
   ```

3. В файле миграции напишите SQL команды:
   ```sql
   -- Миграция 002: Добавление таблицы products
   -- Дата создания: 2025-11-06
   -- Описание: Создание таблицы для хранения товаров

   CREATE TABLE IF NOT EXISTS `products` (
     `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
     `name` VARCHAR(255) NOT NULL,
     `price` DECIMAL(10,2) NOT NULL,
     `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     PRIMARY KEY (`id`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

4. Запустите миграции:
   ```bash
   php migrations/migrate.php run
   ```

## Важные замечания

- Миграции выполняются в порядке номеров (001, 002, 003...)
- Каждая миграция выполняется только один раз
- Используйте `IF NOT EXISTS` для создания таблиц, чтобы избежать ошибок при повторном запуске
- Используйте `INSERT IGNORE` для вставки начальных данных, чтобы избежать дублирования
- Все миграции выполняются в транзакциях - при ошибке все изменения откатываются

## Таблица migrations

Система автоматически создает таблицу `migrations` для отслеживания выполненных миграций:

```sql
CREATE TABLE `migrations` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL UNIQUE,
  `batch` INT(11) NOT NULL,
  `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

- `migration` - имя файла миграции
- `batch` - номер пакета выполнения (все миграции, запущенные вместе, имеют один batch)
- `executed_at` - время выполнения миграции
