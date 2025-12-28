#!/bin/bash

# Глубокая диагностика проблемы подключения Laravel к PostgreSQL

echo "=========================================="
echo "ГЛУБОКАЯ ДИАГНОСТИКА ПОДКЛЮЧЕНИЯ LARAVEL К POSTGRESQL"
echo "=========================================="
echo ""

# Загружаем переменные из корневого .env
if [ -f ".env" ]; then
    set -a
    source .env
    set +a
    echo "✅ Загружен корневой .env"
else
    echo "❌ Корневой .env не найден!"
    exit 1
fi

DB_USERNAME=${DB_USERNAME:-hunter_photo}
DB_DATABASE=${DB_DATABASE:-hunter_photo}
DB_PASSWORD=${DB_PASSWORD:-}
DB_HOST=${DB_HOST:-postgres}
DB_PORT=${DB_PORT:-5432}

echo "Параметры подключения:"
echo "  DB_HOST: $DB_HOST"
echo "  DB_PORT: $DB_PORT"
echo "  DB_DATABASE: $DB_DATABASE"
echo "  DB_USERNAME: $DB_USERNAME"
echo "  DB_PASSWORD: ${DB_PASSWORD:+установлен (длина: ${#DB_PASSWORD})}"
echo ""

# 1. Проверка контейнеров
echo "=== 1. ПРОВЕРКА КОНТЕЙНЕРОВ ==="
if docker ps | grep -q "hunter-photo-postgres"; then
    echo "✅ Контейнер PostgreSQL запущен"
    POSTGRES_IP=$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' hunter-photo-postgres)
    echo "   IP адрес: $POSTGRES_IP"
else
    echo "❌ Контейнер PostgreSQL НЕ запущен!"
    exit 1
fi

if docker ps | grep -q "hunter-photo-laravel"; then
    echo "✅ Контейнер Laravel запущен"
    LARAVEL_IP=$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' hunter-photo-laravel)
    echo "   IP адрес: $LARAVEL_IP"
else
    echo "❌ Контейнер Laravel НЕ запущен!"
    exit 1
fi

# Проверяем сеть
echo ""
echo "Проверяем сеть Docker..."
if docker network ls | grep -q "hunter-photo-network"; then
    echo "✅ Сеть hunter-photo-network существует"
    echo "Контейнеры в сети:"
    docker network inspect hunter-photo-network --format '{{range .Containers}}{{.Name}} {{end}}' 2>/dev/null || echo "Не удалось получить список"
else
    echo "❌ Сеть hunter-photo-network НЕ найдена!"
fi
echo ""

# 2. Проверка PostgreSQL
echo "=== 2. ПРОВЕРКА POSTGRESQL ==="
echo "Проверяем готовность PostgreSQL..."
docker exec hunter-photo-postgres pg_isready -U $DB_USERNAME 2>&1
if [ $? -eq 0 ]; then
    echo "✅ PostgreSQL готов к подключениям"
else
    echo "❌ PostgreSQL не готов!"
fi
echo ""

echo "Проверяем подключение из контейнера PostgreSQL..."
docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c "SELECT current_database(), current_user, version();" 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Подключение из контейнера PostgreSQL работает"
else
    echo "❌ Подключение из контейнера PostgreSQL НЕ работает!"
fi
echo ""

echo "Проверяем пользователей в PostgreSQL..."
docker exec hunter-photo-postgres psql -U postgres -d postgres -c "\du" 2>&1 | grep -E "hunter_photo|Role name" | head -5
echo ""

echo "Проверяем базы данных..."
docker exec hunter-photo-postgres psql -U postgres -d postgres -c "\l" 2>&1 | grep -E "hunter_photo|Name" | head -5
echo ""

# 3. Проверка переменных окружения в Laravel контейнере
echo "=== 3. ПЕРЕМЕННЫЕ ОКРУЖЕНИЯ В LARAVEL КОНТЕЙНЕРЕ ==="
echo "Переменные DB_* в контейнере:"
docker exec hunter-photo-laravel env | grep "^DB_" | sort
echo ""

# 4. Проверка .env файла внутри контейнера
echo "=== 4. .ENV ФАЙЛ ВНУТРИ LARAVEL КОНТЕЙНЕРА ==="
if docker exec hunter-photo-laravel test -f /var/www/html/.env; then
    echo "✅ Файл .env существует в контейнере"
    echo ""
    echo "Настройки БД в .env:"
    docker exec hunter-photo-laravel grep "^DB_" /var/www/html/.env | grep -v "PASSWORD"
    echo ""
    echo "DB_PASSWORD в .env (длина):"
    ENV_PASSWORD_LEN=$(docker exec hunter-photo-laravel grep "^DB_PASSWORD=" /var/www/html/.env | cut -d'=' -f2- | wc -c)
    echo "  Длина пароля: $ENV_PASSWORD_LEN символов"
    
    # Сравниваем пароли
    ENV_PASSWORD=$(docker exec hunter-photo-laravel grep "^DB_PASSWORD=" /var/www/html/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs)
    if [ "$ENV_PASSWORD" = "$DB_PASSWORD" ]; then
        echo "  ✅ Пароль в .env совпадает с паролем из корневого .env"
    else
        echo "  ❌ Пароль в .env НЕ совпадает с паролем из корневого .env!"
        echo "     Длина в .env: ${#ENV_PASSWORD}"
        echo "     Длина ожидаемая: ${#DB_PASSWORD}"
    fi
else
    echo "❌ Файл .env НЕ существует в контейнере!"
fi
echo ""

# 5. Проверка подключения через PHP PDO
echo "=== 5. ПРОВЕРКА ПОДКЛЮЧЕНИЯ ЧЕРЕЗ PHP PDO ==="
docker exec hunter-photo-laravel php -r "
\$host = getenv('DB_HOST') ?: 'postgres';
\$port = getenv('DB_PORT') ?: '5432';
\$database = getenv('DB_DATABASE') ?: 'hunter_photo';
\$username = getenv('DB_USERNAME') ?: 'hunter_photo';
\$password = getenv('DB_PASSWORD') ?: '';

// Пробуем прочитать из .env файла
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

// Проверяем доступность хоста
echo 'Проверка доступности хоста...' . PHP_EOL;
\$socket = @fsockopen(\$host, \$port, \$errno, \$errstr, 5);
if (\$socket) {
    echo '✅ Хост доступен' . PHP_EOL;
    fclose(\$socket);
} else {
    echo '❌ Хост НЕ доступен: ' . \$errstr . ' (' . \$errno . ')' . PHP_EOL;
}

echo PHP_EOL;
echo 'Попытка подключения через PDO...' . PHP_EOL;

try {
    \$dsn = 'pgsql:host=' . \$host . ';port=' . \$port . ';dbname=' . \$database;
    echo 'DSN: ' . \$dsn . PHP_EOL;
    
    \$pdo = new PDO(\$dsn, \$username, \$password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    echo '✅ Подключение через PHP PDO успешно!' . PHP_EOL;
    \$stmt = \$pdo->query('SELECT current_database(), current_user, version()');
    \$result = \$stmt->fetch(PDO::FETCH_ASSOC);
    echo 'База данных: ' . \$result['current_database'] . PHP_EOL;
    echo 'Пользователь: ' . \$result['current_user'] . PHP_EOL;
    echo 'Версия PostgreSQL: ' . substr(\$result['version'], 0, 50) . '...' . PHP_EOL;
} catch (PDOException \$e) {
    echo '❌ Ошибка подключения через PHP PDO:' . PHP_EOL;
    echo '   Сообщение: ' . \$e->getMessage() . PHP_EOL;
    echo '   Код: ' . \$e->getCode() . PHP_EOL;
    exit(1);
}
"
PDO_RESULT=$?
echo ""

# 6. Проверка подключения через Laravel
if [ $PDO_RESULT -eq 0 ]; then
    echo "=== 6. ПРОВЕРКА ПОДКЛЮЧЕНИЯ ЧЕРЕЗ LARAVEL ==="
    echo "Очищаем кеши Laravel..."
    docker exec hunter-photo-laravel php artisan config:clear 2>&1
    docker exec hunter-photo-laravel php artisan cache:clear 2>&1
    echo ""
    
    echo "Проверяем подключение через Laravel Artisan..."
    docker exec hunter-photo-laravel php artisan db:show 2>&1 | head -30
    echo ""
    
    echo "Проверяем подключение через Laravel Tinker..."
    docker exec hunter-photo-laravel php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connection OK';" 2>&1 | head -20
    echo ""
fi

# 7. Проверка сетевого подключения
echo "=== 7. ПРОВЕРКА СЕТЕВОГО ПОДКЛЮЧЕНИЯ ==="
echo "Проверяем доступность PostgreSQL из Laravel контейнера..."
docker exec hunter-photo-laravel ping -c 2 $DB_HOST 2>&1 | head -5
echo ""

echo "Проверяем доступность порта PostgreSQL..."
docker exec hunter-photo-laravel timeout 3 bash -c "echo > /dev/tcp/$DB_HOST/$DB_PORT" 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Порт $DB_PORT доступен на хосте $DB_HOST"
else
    echo "❌ Порт $DB_PORT НЕ доступен на хосте $DB_HOST!"
fi
echo ""

# 8. Проверка pg_hba.conf
echo "=== 8. ПРОВЕРКА КОНФИГУРАЦИИ POSTGRESQL ==="
echo "Проверяем pg_hba.conf..."
docker exec hunter-photo-postgres cat /var/lib/postgresql/data/pgdata/pg_hba.conf 2>/dev/null | grep -v "^#" | grep -v "^$" | head -10
echo ""

# 9. Итоговая сводка
echo "=========================================="
echo "ИТОГОВАЯ СВОДКА"
echo "=========================================="
echo ""

if [ $PDO_RESULT -eq 0 ]; then
    echo "✅ PHP PDO может подключиться к PostgreSQL"
    echo ""
    echo "Если Laravel все еще не может подключиться, возможные причины:"
    echo "1. Laravel использует закешированную конфигурацию"
    echo "   Решение: docker exec hunter-photo-laravel php artisan config:clear"
    echo ""
    echo "2. Проблема с правами доступа к .env файлу"
    echo "   Решение: docker exec hunter-photo-laravel chmod 644 /var/www/html/.env"
    echo ""
    echo "3. Проблема с расширением pgsql в PHP"
    echo "   Решение: docker exec hunter-photo-laravel php -m | grep pgsql"
else
    echo "❌ PHP PDO НЕ может подключиться к PostgreSQL"
    echo ""
    echo "Возможные причины:"
    echo "1. Неправильный пароль"
    echo "   Решение: Проверьте пароль в PostgreSQL и в .env файлах"
    echo ""
    echo "2. Пользователь не существует или не имеет прав"
    echo "   Решение: docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"\\du\""
    echo ""
    echo "3. База данных не существует"
    echo "   Решение: docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"\\l\""
    echo ""
    echo "4. Проблема с сетью Docker"
    echo "   Решение: Проверьте, что оба контейнера в одной сети"
    echo ""
    echo "5. PostgreSQL не принимает подключения"
    echo "   Решение: Проверьте pg_hba.conf и listen_addresses в postgresql.conf"
fi
echo ""

