#!/bin/bash

# Скрипт для детальной диагностики проблемы с подключением Laravel к БД

echo "=== Детальная диагностика подключения Laravel к БД ==="
echo ""

# Загружаем переменные окружения из .env файла (если есть)
if [ -f ".env" ]; then
    export $(grep -v '^#' .env | grep -E "DB_" | xargs)
fi

DB_USERNAME=${DB_USERNAME:-hunter_photo}
DB_DATABASE=${DB_DATABASE:-hunter_photo}
DB_PASSWORD=${DB_PASSWORD:-}

echo "=== 1. Проверка переменных окружения в Docker Compose ==="
echo "DB_HOST: ${DB_HOST:-postgres}"
echo "DB_PORT: ${DB_PORT:-5432}"
echo "DB_DATABASE: ${DB_DATABASE:-hunter_photo}"
echo "DB_USERNAME: ${DB_USERNAME:-hunter_photo}"
echo "DB_PASSWORD: ${DB_PASSWORD:+***установлен***}"
echo ""

echo "=== 2. Проверка переменных окружения в Laravel контейнере ==="
docker exec hunter-photo-laravel env | grep -E "^DB_" | sort
echo ""

echo "=== 3. Проверка настроек в laravel/.env ==="
if [ -f "laravel/.env" ]; then
    echo "Настройки БД в laravel/.env:"
    grep -E "^DB_" laravel/.env | grep -v "PASSWORD" || echo "Нет настроек DB_ (кроме пароля)"
    echo ""
    
    # Проверяем пароль (показываем только длину)
    ENV_PASSWORD=$(grep "^DB_PASSWORD=" laravel/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'")
    if [ -n "$ENV_PASSWORD" ]; then
        echo "DB_PASSWORD в .env: установлен (длина: ${#ENV_PASSWORD} символов)"
        # Проверяем на спецсимволы
        if [[ "$ENV_PASSWORD" =~ [\$\`\\] ]]; then
            echo "⚠️  ВНИМАНИЕ: Пароль содержит спецсимволы, которые могут требовать экранирования!"
        fi
    else
        echo "DB_PASSWORD в .env: НЕ УСТАНОВЛЕН"
    fi
    echo ""
else
    echo "⚠️  Файл laravel/.env не найден"
    echo ""
fi

echo "=== 4. Проверка подключения через PostgreSQL контейнер ==="
docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c "SELECT current_database(), current_user;" 2>&1
echo ""

echo "=== 5. Проверка подключения через PHP PDO (как Laravel) ==="
docker exec hunter-photo-laravel php -r "
\$host = getenv('DB_HOST') ?: 'postgres';
\$port = getenv('DB_PORT') ?: '5432';
\$database = getenv('DB_DATABASE') ?: 'hunter_photo';
\$username = getenv('DB_USERNAME') ?: 'hunter_photo';
\$password = getenv('DB_PASSWORD') ?: '';

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
    
    echo '✅ Подключение успешно!' . PHP_EOL;
    \$stmt = \$pdo->query('SELECT current_database(), current_user, version()');
    \$result = \$stmt->fetch(PDO::FETCH_ASSOC);
    echo 'База данных: ' . \$result['current_database'] . PHP_EOL;
    echo 'Пользователь: ' . \$result['current_user'] . PHP_EOL;
} catch (PDOException \$e) {
    echo '❌ Ошибка подключения: ' . \$e->getMessage() . PHP_EOL;
    echo 'Код ошибки: ' . \$e->getCode() . PHP_EOL;
}
"
echo ""

echo "=== 6. Проверка конфигурации Laravel (config/database.php) ==="
docker exec hunter-photo-laravel php -r "
\$config = require '/var/www/html/config/database.php';
\$pgsql = \$config['connections']['pgsql'] ?? null;
if (\$pgsql) {
    echo 'Конфигурация pgsql:' . PHP_EOL;
    echo '  driver: ' . (\$pgsql['driver'] ?? 'не указан') . PHP_EOL;
    echo '  host: ' . (\$pgsql['host'] ?? 'не указан') . PHP_EOL;
    echo '  port: ' . (\$pgsql['port'] ?? 'не указан') . PHP_EOL;
    echo '  database: ' . (\$pgsql['database'] ?? 'не указан') . PHP_EOL;
    echo '  username: ' . (\$pgsql['username'] ?? 'не указан') . PHP_EOL;
    echo '  password: ' . (isset(\$pgsql['password']) && !empty(\$pgsql['password']) ? 'установлен' : 'НЕ УСТАНОВЛЕН') . PHP_EOL;
} else {
    echo '❌ Конфигурация pgsql не найдена!' . PHP_EOL;
}
"
echo ""

echo "=== 7. Очистка кеша Laravel ==="
docker exec hunter-photo-laravel php artisan config:clear
docker exec hunter-photo-laravel php artisan cache:clear
echo "✅ Кеш очищен"
echo ""

echo "=== 8. Проверка через Laravel Artisan ==="
docker exec hunter-photo-laravel php artisan db:show 2>&1 | head -30
echo ""

echo "=== 9. Проверка пользователя в PostgreSQL ==="
docker exec hunter-photo-postgres psql -U postgres -d postgres -c "\du" 2>&1 | grep -E "hunter_photo|Role name" | head -5
echo ""

echo "=== Рекомендации ==="
echo "1. Убедитесь, что пароль в корневом .env файле совпадает с паролем в PostgreSQL"
echo "2. Если пароль содержит спецсимволы, попробуйте заключить его в одинарные кавычки в .env"
echo "3. Проверьте, что пользователь hunter_photo существует в PostgreSQL:"
echo "   docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"\\du\""
echo ""
echo "4. Если нужно создать/изменить пароль пользователя:"
echo "   docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"ALTER USER hunter_photo WITH PASSWORD 'новый_пароль';\""
echo ""
echo "5. Перезапустите контейнеры после изменения пароля:"
echo "   docker-compose -f docker-compose.production.yml restart postgres laravel"

