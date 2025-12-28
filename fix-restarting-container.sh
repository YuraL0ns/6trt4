#!/bin/bash

# Скрипт для исправления проблемы с постоянно перезапускающимся контейнером Laravel

echo "=== Исправление проблемы с перезапускающимся контейнером Laravel ==="
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

echo "1. Останавливаем контейнер Laravel..."
docker-compose -f docker-compose.production.yml stop laravel 2>&1
echo ""

echo "2. Просматриваем последние логи контейнера Laravel..."
echo "=== ПОСЛЕДНИЕ 50 СТРОК ЛОГОВ ==="
docker logs --tail 50 hunter-photo-laravel 2>&1 || echo "Не удалось получить логи (контейнер не существует или не запускался)"
echo ""

echo "3. Проверяем, что PostgreSQL работает..."
if docker ps | grep -q "hunter-photo-postgres"; then
    echo "✅ PostgreSQL запущен"
    docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c "SELECT 1;" 2>&1 | head -3
else
    echo "❌ PostgreSQL не запущен!"
    exit 1
fi
echo ""

echo "4. Синхронизируем пароли во всех .env файлах..."
if [ -f "generate-env-files.sh" ]; then
    ./generate-env-files.sh 2>&1 | tail -10
else
    echo "⚠️  Скрипт generate-env-files.sh не найден, пропускаем синхронизацию"
fi
echo ""

echo "5. Проверяем laravel/.env файл на хосте..."
if [ -f "laravel/.env" ]; then
    echo "✅ Файл laravel/.env существует"
    echo "Настройки БД в laravel/.env:"
    grep "^DB_" laravel/.env | grep -v "PASSWORD"
    echo ""
    
    LARAVEL_PASSWORD=$(grep "^DB_PASSWORD=" laravel/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs)
    if [ "$LARAVEL_PASSWORD" = "$DB_PASSWORD" ]; then
        echo "✅ Пароль в laravel/.env совпадает с корневым .env"
    else
        echo "⚠️  Пароль в laravel/.env НЕ совпадает! Обновляем..."
        sed -i '/^DB_PASSWORD=/d' laravel/.env
        echo "DB_PASSWORD=$DB_PASSWORD" >> laravel/.env
        echo "✅ Пароль обновлен"
    fi
else
    echo "❌ Файл laravel/.env не найден!"
    echo "Создаем базовый .env файл..."
    if [ -f "laravel/.env.example" ]; then
        cp laravel/.env.example laravel/.env
        echo "✅ Создан laravel/.env из .env.example"
    else
        echo "❌ laravel/.env.example не найден!"
        exit 1
    fi
fi
echo ""

echo "6. Временно изменяем команду docker-compose для запуска без миграций..."
echo "Создаем временный docker-compose файл..."
cat > docker-compose.production.temp.yml << 'EOF'
version: '3.8'

services:
  laravel:
    build:
      context: ./laravel
      dockerfile: Dockerfile.production
    container_name: hunter-photo-laravel
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./laravel:/var/www/html
      - laravel_storage:/var/www/html/storage
      - laravel_vendor:/var/www/html/vendor
      - ./laravel/storage/app/public/events:/var/www/html/storage/app/public/events:rw
    environment:
      CLEAR_EVENTS_ON_START: ${CLEAR_EVENTS_ON_START:-false}
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: ${DB_DATABASE:-hunter_photo}
      DB_USERNAME: ${DB_USERNAME:-hunter_photo}
      DB_PASSWORD: ${DB_PASSWORD}
      REDIS_HOST: redis
      REDIS_PORT: 6379
      APP_ENV: production
      APP_DEBUG: ${APP_DEBUG:-false}
      APP_URL: ${APP_URL:-https://hunter-photo.ru}
      FASTAPI_URL: http://fastapi:8000
      FASTAPI_TIMEOUT: 300
      YOO_KASSA_SHOP_ID: ${YOO_KASSA_SHOP_ID}
      YOO_KASSA_SECRET_KEY: ${YOO_KASSA_SECRET_KEY}
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - hunter-photo-network
    # Запускаем только php-fpm без миграций и кеширования
    command: php-fpm
EOF

echo "✅ Создан временный docker-compose файл"
echo ""

echo "7. Запускаем контейнер Laravel с упрощенной командой..."
docker-compose -f docker-compose.production.temp.yml up -d laravel 2>&1
sleep 5
echo ""

echo "8. Проверяем статус контейнера..."
if docker ps | grep -q "hunter-photo-laravel"; then
    echo "✅ Контейнер Laravel запущен и работает!"
    echo ""
    echo "9. Проверяем переменные окружения в контейнере..."
    docker exec hunter-photo-laravel env | grep "^DB_" | sort
    echo ""
    
    echo "10. Проверяем .env файл в контейнере..."
    if docker exec hunter-photo-laravel test -f /var/www/html/.env; then
        echo "✅ Файл .env существует в контейнере"
        echo "Настройки БД:"
        docker exec hunter-photo-laravel grep "^DB_" /var/www/html/.env | grep -v "PASSWORD"
    else
        echo "❌ Файл .env не существует в контейнере!"
    fi
    echo ""
    
    echo "11. Тестируем подключение через PHP PDO..."
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
        echo '❌ Ошибка подключения: ' . \$e->getMessage() . PHP_EOL;
        exit(1);
    }
    "
    echo ""
    
    echo "12. Если подключение работает, очищаем кеши Laravel..."
    docker exec hunter-photo-laravel php artisan config:clear 2>&1
    docker exec hunter-photo-laravel php artisan cache:clear 2>&1
    echo "✅ Кеши очищены"
    echo ""
    
    echo "13. Тестируем подключение через Laravel..."
    docker exec hunter-photo-laravel php artisan db:show 2>&1 | head -20
    echo ""
    
    echo "=== РЕШЕНИЕ ==="
    echo "Если подключение работает, проблема была в команде docker-compose, которая пыталась"
    echo "выполнить миграции до того, как контейнер был готов."
    echo ""
    echo "Теперь нужно обновить docker-compose.production.yml, чтобы:"
    echo "1. Не выполнять миграции при запуске контейнера"
    echo "2. Или добавить проверку подключения перед миграциями"
    echo ""
    echo "Остановите временный контейнер:"
    echo "  docker-compose -f docker-compose.production.temp.yml down"
    echo ""
    echo "И используйте обычный docker-compose.production.yml"
    
else
    echo "❌ Контейнер Laravel все еще не запускается!"
    echo ""
    echo "Просматриваем логи..."
    docker logs --tail 30 hunter-photo-laravel 2>&1
    echo ""
    echo "Возможные причины:"
    echo "1. Ошибка в entrypoint скрипте"
    echo "2. Проблема с правами доступа к файлам"
    echo "3. Ошибка в PHP конфигурации"
    echo ""
    echo "Проверьте логи выше для деталей"
fi

echo ""
echo "Удаляем временный docker-compose файл..."
rm -f docker-compose.production.temp.yml
echo "✅ Временный файл удален"

