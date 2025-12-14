#!/bin/bash
# Скрипт для инициализации базы данных в Docker контейнере

echo "Ожидание запуска MySQL..."
sleep 10

# Импорт дампа базы данных
if [ -f "/docker-entrypoint-initdb.d/u3154158_fisherp.sql" ]; then
    echo "Импорт дампа базы данных..."
    mysql -u root -prootpassword u3154158_fisherp < /docker-entrypoint-initdb.d/u3154158_fisherp.sql
    echo "Дамп успешно импортирован!"
else
    echo "Файл дампа не найден: /docker-entrypoint-initdb.d/u3154158_fisherp.sql"
fi

