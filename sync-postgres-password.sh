#!/bin/bash

# Скрипт для синхронизации пароля в PostgreSQL с паролем из .env файла

echo "=== Синхронизация пароля в PostgreSQL ==="
echo ""

# Загружаем переменные из корневого .env
if [ -f ".env" ]; then
    set -a
    source .env
    set +a
else
    echo "❌ Корневой .env не найден!"
    exit 1
fi

DB_USERNAME=${DB_USERNAME:-hunter_photo}
DB_DATABASE=${DB_DATABASE:-hunter_photo}
DB_PASSWORD=${DB_PASSWORD:-}

if [ -z "$DB_PASSWORD" ]; then
    echo "❌ DB_PASSWORD не установлен в корневом .env файле!"
    exit 1
fi

echo "Пароль из корневого .env: ${DB_PASSWORD:+установлен (длина: ${#DB_PASSWORD})}"
echo ""

# Проверяем, запущен ли PostgreSQL
if ! docker ps | grep -q "hunter-photo-postgres"; then
    echo "❌ Контейнер PostgreSQL не запущен!"
    exit 1
fi

echo "✅ Контейнер PostgreSQL запущен"
echo ""

# 1. Проверяем текущее подключение с паролем из .env
echo "=== 1. Проверка текущего подключения ==="
echo "Пробуем подключиться с паролем из .env..."
docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c "SELECT current_database(), current_user;" 2>&1
CURRENT_CONNECTION=$?
echo ""

if [ $CURRENT_CONNECTION -eq 0 ]; then
    echo "✅ Подключение работает! Пароль в PostgreSQL совпадает с паролем из .env"
    echo ""
    echo "Проблема может быть в другом месте. Проверьте:"
    echo "1. Переменные окружения в Laravel контейнере"
    echo "2. .env файл внутри Laravel контейнера"
    echo "3. Кеши Laravel"
    exit 0
fi

echo "❌ Подключение не работает с текущим паролем"
echo ""

# 2. Пробуем подключиться как postgres для изменения пароля
echo "=== 2. Изменение пароля пользователя в PostgreSQL ==="
echo "Подключаемся как postgres для изменения пароля..."
docker exec hunter-photo-postgres psql -U postgres -d postgres -c "ALTER USER $DB_USERNAME WITH PASSWORD '$DB_PASSWORD';" 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Пароль пользователя $DB_USERNAME обновлен в PostgreSQL"
else
    echo "❌ Не удалось обновить пароль в PostgreSQL"
    echo ""
    echo "Проверяем, существует ли пользователь..."
    docker exec hunter-photo-postgres psql -U postgres -d postgres -c "\du" 2>&1 | grep -E "hunter_photo|Role name" | head -5
    echo ""
    echo "Если пользователь не существует, создайте его:"
    echo "  docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"CREATE USER $DB_USERNAME WITH PASSWORD '$DB_PASSWORD';\""
    echo "  docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"GRANT ALL PRIVILEGES ON DATABASE $DB_DATABASE TO $DB_USERNAME;\""
    exit 1
fi
echo ""

# 3. Проверяем подключение с новым паролем
echo "=== 3. Проверка подключения с новым паролем ==="
sleep 2  # Даем время PostgreSQL обновить пароль
docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c "SELECT current_database(), current_user, version();" 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Подключение работает с новым паролем!"
else
    echo "❌ Подключение все еще не работает"
    echo ""
    echo "Возможные причины:"
    echo "1. PostgreSQL не успел обновить пароль (попробуйте перезапустить контейнер)"
    echo "2. Проблема с правами пользователя"
    echo "3. Проблема с конфигурацией PostgreSQL"
    exit 1
fi
echo ""

# 4. Проверяем подключение из Laravel контейнера (если он запущен)
echo "=== 4. Проверка подключения из Laravel контейнера ==="
if docker ps | grep -q "hunter-photo-laravel"; then
    echo "Контейнер Laravel запущен, проверяем подключение..."
    echo ""
    
    # Останавливаем контейнер, чтобы он не перезапускался
    docker-compose -f docker-compose.production.yml stop laravel 2>&1
    sleep 2
    
    # Запускаем контейнер с упрощенной командой
    echo "Запускаем контейнер с упрощенной командой (только php-fpm)..."
    docker-compose -f docker-compose.production.yml run --rm -d --name hunter-photo-laravel-test \
        -e DB_CONNECTION=pgsql \
        -e DB_HOST=postgres \
        -e DB_PORT=5432 \
        -e DB_DATABASE=$DB_DATABASE \
        -e DB_USERNAME=$DB_USERNAME \
        -e DB_PASSWORD=$DB_PASSWORD \
        laravel php-fpm 2>&1
    
    sleep 5
    
    if docker ps | grep -q "hunter-photo-laravel-test"; then
        echo "✅ Тестовый контейнер запущен"
        echo ""
        
        echo "Проверяем подключение через PHP PDO..."
        docker exec hunter-photo-laravel-test php -r "
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
            echo '❌ Ошибка подключения: ' . \$e->getMessage() . PHP_EOL;
            exit(1);
        }
        " 2>&1
        
        echo ""
        echo "Останавливаем тестовый контейнер..."
        docker stop hunter-photo-laravel-test 2>&1
        docker rm hunter-photo-laravel-test 2>&1
    else
        echo "⚠️  Не удалось запустить тестовый контейнер"
    fi
else
    echo "⚠️  Контейнер Laravel не запущен"
fi
echo ""

echo "=== Готово ==="
echo ""
echo "Если пароль был обновлен, перезапустите контейнер Laravel:"
echo "  docker-compose -f docker-compose.production.yml restart laravel"
echo ""
echo "Или перезапустите PostgreSQL для применения изменений:"
echo "  docker-compose -f docker-compose.production.yml restart postgres"
echo "  docker-compose -f docker-compose.production.yml restart laravel"

