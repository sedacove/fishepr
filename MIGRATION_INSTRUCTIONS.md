# Инструкция по миграции отборов и падежей: добавление session_id

## Важно!

`pool_id` **остается** в таблицах для обратной совместимости. Добавляется новая колонка `session_id`, которая будет использоваться в новой логике приложения.

## Порядок выполнения

### ЭТАП 1: Добавление колонки session_id

Выполните миграцию `043_add_session_id_columns.sql`:

```bash
# Через командную строку MySQL
mysql -u your_user -p your_database < migrations/043_add_session_id_columns.sql

# Или через phpMyAdmin / другой SQL клиент
# Просто выполните содержимое файла migrations/043_add_session_id_columns.sql
```

Эта миграция:
- Добавит колонку `session_id` (NULL разрешен) в таблицы `harvests` и `mortality`
- Добавит индексы для `session_id`
- Добавит внешние ключи на таблицу `sessions`
- **`pool_id` остается нетронутым** для обратной совместимости

### ЭТАП 2: Миграция данных

Запустите скрипт миграции данных:

```bash
php migrate_harvests_mortality_to_sessions.php
```

Скрипт:
- Использует `pool_id` и `recorded_at` для определения соответствующей сессии
- Обновляет `session_id` для всех записей в `harvests` и `mortality`
- Выводит предупреждения для записей, для которых не найдена сессия

**ВАЖНО**: Перед продолжением убедитесь, что все записи получили `session_id`. Если есть записи без `session_id`, их нужно обработать вручную или удалить.

### ЭТАП 3 (опционально): Делаем session_id обязательным

После успешного выполнения скрипта миграции данных можно сделать `session_id` обязательным:

```sql
-- Выполнить только если все записи имеют session_id
ALTER TABLE `harvests` MODIFY COLUMN `session_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID сессии';
ALTER TABLE `mortality` MODIFY COLUMN `session_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID сессии';
```

## Проверка перед завершением

Перед выполнением шага 3 (делать `session_id` NOT NULL) проверьте:

```sql
-- Должно вернуть 0 для обеих таблиц
SELECT COUNT(*) FROM harvests WHERE session_id IS NULL;
SELECT COUNT(*) FROM mortality WHERE session_id IS NULL;
```

Если есть записи без `session_id`, их нужно обработать вручную перед выполнением шага 3.

## Обратная совместимость

- `pool_id` остается в таблицах и может использоваться в старом коде
- Новый код должен использовать `session_id` для связи с сессиями
- `pool_id` можно получить через JOIN: `sessions.pool_id` или оставить в таблице для прямого доступа

