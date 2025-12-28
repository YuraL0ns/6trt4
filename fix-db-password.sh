#!/bin/bash

# Скрипт для исправления проблемы с паролем базы данных

echo "=== Исправление проблемы с паролем БД ==="
echo ""

# Загружаем переменные окружения из .env файла (если есть)
if [ -f ".env" ]; then
    export $(grep -v '^#' .env | grep -E "DB_" | xargs)
fi

DB_USERNAME=${DB_USERNAME:-hunter_photo}
DB_DATABASE=${DB_DATABASE:-hunter_photo}
DB_PASSWORD=${DB_PASSWORD:-}

if [ -z "$DB_PASSWORD" ]; then
    echo "❌ DB_PASSWORD не установлен в корневом .env файле!"
    echo "Установите пароль в корневом .env файле:"
    echo "  DB_PASSWORD=ваш_пароль"
    exit 1
fi

echo "1. Проверяем подключение к PostgreSQL с текущим паролем..."
docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c "SELECT current_database(), current_user;" 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Подключение к PostgreSQL работает"
else
    echo "❌ Ошибка подключения к PostgreSQL"
    echo ""
    echo "Проверяем, существует ли пользователь и база данных..."
    docker exec hunter-photo-postgres psql -U postgres -d postgres -c "\du" 2>&1 | grep -E "hunter_photo|Role name"
    echo ""
    echo "Если пользователь не существует, создайте его:"
    echo "  docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"CREATE USER hunter_photo WITH PASSWORD '\$DB_PASSWORD';\""
    echo "  docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"GRANT ALL PRIVILEGES ON DATABASE hunter_photo TO hunter_photo;\""
    exit 1
fi
echo ""

echo "2. Обновляем пароль в laravel/.env файле..."
if [ -f "laravel/.env" ]; then
    # Сохраняем текущий пароль из .env
    CURRENT_PASSWORD=$(grep "^DB_PASSWORD=" laravel/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'")
    
    if [ -n "$CURRENT_PASSWORD" ]; then
        echo "Текущий пароль в laravel/.env: установлен (длина: ${#CURRENT_PASSWORD} символов)"
    else
        echo "Текущий пароль в laravel/.env: НЕ УСТАНОВЛЕН"
    fi
    
    # Обновляем пароль
    if grep -q "^DB_PASSWORD=" laravel/.env; then
        # Экранируем спецсимволы для sed
        ESCAPED_PASSWORD=$(printf '%s\n' "$DB_PASSWORD" | sed 's/[[\.*^$()+?{|]/\\&/g')
        sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$ESCAPED_PASSWORD|" laravel/.env
        echo "✅ Пароль обновлен в laravel/.env"
    else
        echo "DB_PASSWORD=$DB_PASSWORD" >> laravel/.env
        echo "✅ Пароль добавлен в laravel/.env"
    fi
    
    # Проверяем результат
    NEW_PASSWORD=$(grep "^DB_PASSWORD=" laravel/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'")
    if [ "$NEW_PASSWORD" = "$DB_PASSWORD" ]; then
        echo "✅ Пароль в laravel/.env совпадает с паролем из корневого .env"
    else
        echo "⚠️  Пароль в laravel/.env НЕ совпадает с паролем из корневого .env"
        echo "   Длина нового пароля: ${#NEW_PASSWORD}"
        echo "   Длина ожидаемого пароля: ${#DB_PASSWORD}"
    fi
else
    echo "❌ Файл laravel/.env не найден!"
    exit 1
fi
echo ""

echo "3. Проверяем переменные окружения в Laravel контейнере..."
docker exec hunter-photo-laravel env | grep -E "^DB_" | sort
echo ""

echo "4. Очищаем кеш Laravel..."
docker exec hunter-photo-laravel php artisan config:clear
docker exec hunter-photo-laravel php artisan cache:clear
echo "✅ Кеш очищен"
echo ""

echo "5. Тестируем подключение через PHP PDO (как Laravel)..."
docker exec hunter-photo-laravel php -r "
\$host = getenv('DB_HOST') ?: 'postgres';
\$port = getenv('DB_PORT') ?: '5432';
\$database = getenv('DB_DATABASE') ?: 'hunter_photo';
\$username = getenv('DB_USERNAME') ?: 'hunter_photo';
\$password = getenv('DB_PASSWORD') ?: '';

try {
    \$dsn = 'pgsql:host=' . \$host . ';port=' . \$port . ';dbname=' . \$database;
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
    echo "6. Проверяем подключение через Laravel Artisan..."
    docker exec hunter-photo-laravel php artisan db:show 2>&1 | head -20
else
    echo ""
    echo "❌ Ошибка подключения через PHP PDO"
    echo ""
    echo "Возможные причины:"
    echo "1. Пароль содержит спецсимволы, которые неправильно обрабатываются"
    echo "2. Пароль в laravel/.env не совпадает с паролем в PostgreSQL"
    echo "3. Пользователь hunter_photo не существует в PostgreSQL"
    echo ""
    echo "Попробуйте:"
    echo "1. Проверить пароль в PostgreSQL:"
    echo "   docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"\\du\""
    echo ""
    echo "2. Если нужно изменить пароль пользователя:"
    echo "   docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"ALTER USER hunter_photo WITH PASSWORD 'новый_пароль';\""
    echo ""
    echo "3. Перезапустить контейнеры:"
    echo "   docker-compose -f docker-compose.production.yml restart postgres laravel"
    exit 1
fi
echo ""

echo "=== Готово ==="
echo "Если проблема сохраняется, перезапустите контейнер Laravel:"
echo "  docker-compose -f docker-compose.production.yml restart laravel"

