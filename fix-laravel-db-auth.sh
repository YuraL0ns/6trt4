#!/bin/bash

# Скрипт для исправления проблемы с аутентификацией Laravel к БД
# Когда PostgreSQL принимает подключение, но Laravel не может подключиться

echo "=== Исправление проблемы с аутентификацией Laravel к БД ==="
echo ""

# Загружаем переменные окружения из .env файла
if [ -f ".env" ]; then
    set -a
    source .env
    set +a
fi

DB_USERNAME=${DB_USERNAME:-hunter_photo}
DB_DATABASE=${DB_DATABASE:-hunter_photo}
DB_PASSWORD=${DB_PASSWORD:-}

if [ -z "$DB_PASSWORD" ]; then
    echo "❌ DB_PASSWORD не установлен в корневом .env файле!"
    exit 1
fi

echo "1. Проверяем подключение к PostgreSQL напрямую..."
docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c 'SELECT current_database(), current_user;' 2>&1
if [ $? -ne 0 ]; then
    echo "❌ Ошибка подключения к PostgreSQL!"
    exit 1
fi
echo "✅ PostgreSQL принимает подключение"
echo ""

echo "2. Проверяем пароль в laravel/.env..."
if [ ! -f "laravel/.env" ]; then
    echo "❌ Файл laravel/.env не найден!"
    exit 1
fi

ENV_PASSWORD=$(grep "^DB_PASSWORD=" laravel/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs)
echo "Пароль в laravel/.env: ${ENV_PASSWORD:+установлен (длина: ${#ENV_PASSWORD})}"
echo "Пароль из корневого .env: ${DB_PASSWORD:+установлен (длина: ${#DB_PASSWORD})}"

if [ "$ENV_PASSWORD" != "$DB_PASSWORD" ]; then
    echo "⚠️  Пароли НЕ совпадают! Обновляем..."
    sed -i '/^DB_PASSWORD=/d' laravel/.env
    echo "DB_PASSWORD=$DB_PASSWORD" >> laravel/.env
    echo "✅ Пароль обновлен"
else
    echo "✅ Пароли совпадают"
fi
echo ""

echo "3. Проверяем, запущен ли контейнер Laravel..."
if ! docker ps | grep -q "hunter-photo-laravel"; then
    echo "⚠️  Контейнер Laravel не запущен"
    echo "Запускаем контейнер..."
    docker-compose -f docker-compose.production.yml up -d laravel
    sleep 5
else
    echo "✅ Контейнер Laravel запущен"
fi
echo ""

echo "4. Очищаем ВСЕ кеши Laravel внутри контейнера..."
docker exec hunter-photo-laravel php artisan config:clear 2>&1
docker exec hunter-photo-laravel php artisan cache:clear 2>&1
docker exec hunter-photo-laravel php artisan route:clear 2>&1
docker exec hunter-photo-laravel php artisan view:clear 2>&1
echo "✅ Кеши очищены"
echo ""

echo "5. Удаляем закешированные конфигурационные файлы..."
docker exec hunter-photo-laravel rm -f /var/www/html/bootstrap/cache/config.php 2>/dev/null
docker exec hunter-photo-laravel rm -f /var/www/html/bootstrap/cache/routes-v7.php 2>/dev/null
docker exec hunter-photo-laravel rm -f /var/www/html/bootstrap/cache/services.php 2>/dev/null
echo "✅ Кешированные файлы удалены"
echo ""

echo "6. Проверяем переменные окружения внутри контейнера Laravel..."
echo "DB_PASSWORD в контейнере:"
docker exec hunter-photo-laravel env | grep "^DB_PASSWORD" | head -1
echo ""

echo "7. Проверяем пароль в .env файле внутри контейнера..."
CONTAINER_PASSWORD=$(docker exec hunter-photo-laravel grep "^DB_PASSWORD=" /var/www/html/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs)
echo "Пароль в .env внутри контейнера: ${CONTAINER_PASSWORD:+установлен (длина: ${#CONTAINER_PASSWORD})}"
if [ "$CONTAINER_PASSWORD" != "$DB_PASSWORD" ]; then
    echo "⚠️  Пароль в контейнере НЕ совпадает с паролем из корневого .env!"
    echo "Перезапускаем контейнер для обновления .env..."
    docker-compose -f docker-compose.production.yml restart laravel
    sleep 5
else
    echo "✅ Пароль в контейнере совпадает"
fi
echo ""

echo "8. Тестируем подключение через PHP PDO (как Laravel)..."
docker exec hunter-photo-laravel php -r "
\$host = getenv('DB_HOST') ?: 'postgres';
\$port = getenv('DB_PORT') ?: '5432';
\$database = getenv('DB_DATABASE') ?: 'hunter_photo';
\$username = getenv('DB_USERNAME') ?: 'hunter_photo';
\$password = getenv('DB_PASSWORD') ?: '';

// Также пробуем прочитать из .env файла напрямую
if (file_exists('/var/www/html/.env')) {
    \$envContent = file_get_contents('/var/www/html/.env');
    if (preg_match('/^DB_PASSWORD=(.+)$/m', \$envContent, \$matches)) {
        \$envPassword = trim(\$matches[1], '\"\'');
        if (!empty(\$envPassword)) {
            \$password = \$envPassword;
            echo 'Используем пароль из .env файла' . PHP_EOL;
        }
    }
}

echo 'Параметры подключения:' . PHP_EOL;
echo '  Host: ' . \$host . PHP_EOL;
echo '  Port: ' . \$port . PHP_EOL;
echo '  Database: ' . \$database . PHP_EOL;
echo '  Username: ' . \$username . PHP_EOL;
echo '  Password: ' . (empty(\$password) ? 'НЕ УСТАНОВЛЕН' : 'установлен (длина: ' . strlen(\$password) . ')') . PHP_EOL;
echo PHP_EOL;

try {
    \$dsn = 'pgsql:host=' . \$host . ';port=' . \$port . ';dbname=' . \$database;
    echo 'DSN: ' . \$dsn . PHP_EOL;
    echo 'Попытка подключения...' . PHP_EOL;
    
    \$pdo = new PDO(\$dsn, \$username, \$password);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo '✅ Подключение через PHP PDO успешно!' . PHP_EOL;
    \$stmt = \$pdo->query('SELECT current_database(), current_user');
    \$result = \$stmt->fetch(PDO::FETCH_ASSOC);
    echo 'База данных: ' . \$result['current_database'] . PHP_EOL;
    echo 'Пользователь: ' . \$result['current_user'] . PHP_EOL;
} catch (PDOException \$e) {
    echo '❌ Ошибка подключения через PHP PDO: ' . \$e->getMessage() . PHP_EOL;
    echo 'Код ошибки: ' . \$e->getCode() . PHP_EOL;
    exit(1);
}
"
if [ $? -eq 0 ]; then
    echo ""
    echo "9. Тестируем подключение через Laravel Artisan..."
    docker exec hunter-photo-laravel php artisan db:show 2>&1 | head -20
    if [ $? -eq 0 ]; then
        echo ""
        echo "✅✅✅ ПРОБЛЕМА РЕШЕНА! ✅✅✅"
        echo ""
        echo "Laravel теперь может подключиться к базе данных!"
    else
        echo ""
        echo "⚠️  Laravel Artisan все еще не может подключиться"
        echo ""
        echo "Попробуйте пересобрать контейнер:"
        echo "  docker-compose -f docker-compose.production.yml build laravel"
        echo "  docker-compose -f docker-compose.production.yml up -d laravel"
    fi
else
    echo ""
    echo "❌ Ошибка подключения через PHP PDO"
    echo ""
    echo "Возможные причины:"
    echo "1. Пароль в PostgreSQL не совпадает с паролем в .env"
    echo "2. Проблема с правами пользователя в PostgreSQL"
    echo ""
    echo "Проверьте пароль в PostgreSQL:"
    echo "  docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"\\du\""
    echo ""
    echo "Если нужно изменить пароль пользователя:"
    echo "  docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"ALTER USER hunter_photo WITH PASSWORD '\$DB_PASSWORD';\""
fi
echo ""

