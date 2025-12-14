# Инструкция по локальной установке проекта FisherP

## Требования

- PHP 7.4 или выше
- MySQL 5.7+ или MariaDB 10.3+
- Apache/Nginx (опционально, можно использовать встроенный PHP сервер)

## Установка зависимостей

### Ubuntu/Debian

```bash
# Установка PHP и необходимых расширений
sudo apt update
sudo apt install php php-cli php-mysql php-pdo php-mbstring php-xml

# Установка MySQL
sudo apt install mysql-server

# Или MariaDB
sudo apt install mariadb-server
```

### Проверка установки

```bash
php --version
mysql --version
```

## Настройка базы данных

1. **Запустите MySQL:**
   ```bash
   sudo systemctl start mysql
   # или для MariaDB
   sudo systemctl start mariadb
   ```

2. **Настройте подключение в `config/database.php`:**
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'u3154158_fisherp');
   define('DB_USER', 'root');
   define('DB_PASS', 'ваш_пароль'); // Если установлен пароль для root
   ```

3. **Восстановите базу данных из дампа:**
   ```bash
   chmod +x restore-db-local.sh
   ./restore-db-local.sh
   ```
   
   Или вручную:
   ```bash
   mysql -u root -p u3154158_fisherp < dumps/u3154158_fisherp.sql
   ```

## Настройка прав доступа

```bash
# Установите права на запись для папок uploads и storage
chmod -R 777 uploads storage
```

## Запуск проекта

### Вариант 1: Встроенный PHP сервер (рекомендуется для разработки)

```bash
php -S localhost:8000
```

Затем откройте в браузере: http://localhost:8000

### Вариант 2: Apache

1. Скопируйте проект в директорию Apache:
   ```bash
   sudo cp -r /media/sedacove/ubuntu/projects/fisherp/fishepr /var/www/html/fisherp
   ```

2. Настройте виртуальный хост или используйте `http://localhost/fisherp/`

3. Обновите `BASE_URL` в `config/config.php`:
   ```php
   define('BASE_URL', 'http://localhost/fisherp/');
   ```

4. Убедитесь, что включен mod_rewrite:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

### Вариант 3: XAMPP

1. Скопируйте проект в `C:\xampp\htdocs\fisherp\` (Windows) или `/opt/lampp/htdocs/fisherp/` (Linux)

2. Обновите `BASE_URL` в `config/config.php`:
   ```php
   define('BASE_URL', 'http://localhost/fisherp/');
   ```

3. Запустите Apache и MySQL через панель управления XAMPP

## Параметры подключения к БД

По умолчанию:
- **Хост:** `localhost`
- **База данных:** `u3154158_fisherp`
- **Пользователь:** `root`
- **Пароль:** (пустой, если не установлен)

## Полезные команды

### Проверка подключения к БД
```bash
mysql -u root -p -e "SHOW DATABASES;"
```

### Просмотр логов
```bash
tail -f storage/debug.log
```

### Запуск миграций (если нужно)
```bash
php migrations/migrate.php run
```

## Устранение проблем

### Ошибка подключения к БД
1. Проверьте, что MySQL запущен: `sudo systemctl status mysql`
2. Проверьте параметры в `config/database.php`
3. Проверьте права пользователя MySQL

### Ошибки прав доступа
```bash
chmod -R 755 .
chmod -R 777 uploads storage
```

### PHP расширения не найдены
```bash
# Установите недостающие расширения
sudo apt install php-mysql php-pdo php-mbstring php-xml
```

### Порт 8000 занят
Используйте другой порт:
```bash
php -S localhost:8080
```
И обновите `BASE_URL` в `config/config.php`

## Тестовые учетные записи

После восстановления дампа используйте учетные записи из базы данных.

