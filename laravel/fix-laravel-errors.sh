#!/bin/bash

# Скрипт для исправления ошибок Laravel (APP_KEY и 500 ошибки)

set -e

echo "=========================================="
echo "Исправление ошибок Laravel"
echo "=========================================="
echo ""

CONTAINER_NAME="hunter-photo-laravel"

# Проверка что контейнер запущен
if ! docker ps | grep -q "$CONTAINER_NAME"; then
    echo "❌ Контейнер $CONTAINER_NAME не запущен!"
    echo "Запустите контейнер: docker-compose up -d"
    exit 1
fi

echo "✅ Контейнер $CONTAINER_NAME запущен"
echo ""

# Шаг 1: Проверка и генерация APP_KEY
echo "📝 Шаг 1: Проверка APP_KEY..."
APP_KEY=$(docker exec $CONTAINER_NAME grep "^APP_KEY=" /var/www/html/.env 2>/dev/null | cut -d'=' -f2 || echo "")

# Удаляем пробелы и кавычки
APP_KEY=$(echo "$APP_KEY" | tr -d ' ' | tr -d '"' | tr -d "'")

if [ -z "$APP_KEY" ] || [ "$APP_KEY" == "" ] || [[ ! "$APP_KEY" =~ ^base64: ]]; then
    echo "⚠️  APP_KEY отсутствует или неверный. Генерирую новый..."
    docker exec $CONTAINER_NAME php artisan key:generate --force
    echo "✅ APP_KEY сгенерирован"
    
    # Проверяем что ключ был записан
    sleep 2
    APP_KEY=$(docker exec $CONTAINER_NAME grep "^APP_KEY=" /var/www/html/.env 2>/dev/null | cut -d'=' -f2 | tr -d ' ' | tr -d '"' | tr -d "'" || echo "")
    if [ -z "$APP_KEY" ] || [[ ! "$APP_KEY" =~ ^base64: ]]; then
        echo "❌ ОШИБКА: Не удалось сгенерировать APP_KEY!"
        echo "Попробуйте вручную: docker exec $CONTAINER_NAME php artisan key:generate --force"
        exit 1
    fi
    echo "✅ APP_KEY успешно сгенерирован: ${APP_KEY:0:30}..."
else
    echo "✅ APP_KEY уже установлен: ${APP_KEY:0:30}..."
fi
echo ""

# Шаг 2: Проверка .env файла
echo "📝 Шаг 2: Проверка настроек .env..."
docker exec $CONTAINER_NAME sh -c 'cd /var/www/html && \
    if grep -q "APP_ENV=local" .env; then \
        sed -i "s/APP_ENV=local/APP_ENV=production/" .env && \
        echo "✅ APP_ENV изменен на production"; \
    else \
        echo "✅ APP_ENV уже production"; \
    fi'

docker exec $CONTAINER_NAME sh -c 'cd /var/www/html && \
    if grep -q "APP_DEBUG=true" .env; then \
        sed -i "s/APP_DEBUG=true/APP_DEBUG=false/" .env && \
        echo "✅ APP_DEBUG изменен на false"; \
    else \
        echo "✅ APP_DEBUG уже false"; \
    fi'
echo ""

# Шаг 3: Очистка всех кэшей (ВАЖНО: перед пересозданием кэша)
echo "📝 Шаг 3: Очистка кэшей..."
docker exec $CONTAINER_NAME php artisan optimize:clear
# Дополнительная очистка на случай если optimize:clear не удалил все
docker exec $CONTAINER_NAME rm -f /var/www/html/bootstrap/cache/config.php
docker exec $CONTAINER_NAME rm -f /var/www/html/bootstrap/cache/routes-v7.php
docker exec $CONTAINER_NAME rm -f /var/www/html/bootstrap/cache/services.php
echo "✅ Кэши очищены"
echo ""

# Шаг 4: Перегенерация autoload
echo "📝 Шаг 4: Перегенерация Composer autoload..."
docker exec $CONTAINER_NAME composer dump-autoload --optimize
echo "✅ Autoload перегенерирован"
echo ""

# Шаг 5: Проверка прав доступа
echo "📝 Шаг 5: Проверка прав доступа..."
docker exec $CONTAINER_NAME chown -R www-data:www-data /var/www/html/storage
docker exec $CONTAINER_NAME chown -R www-data:www-data /var/www/html/bootstrap/cache
docker exec $CONTAINER_NAME chmod -R 755 /var/www/html/storage
docker exec $CONTAINER_NAME chmod -R 755 /var/www/html/bootstrap/cache
echo "✅ Права доступа установлены"
echo ""

# Шаг 6: Пересоздание кэша
echo "📝 Шаг 6: Пересоздание кэша конфигурации..."
docker exec $CONTAINER_NAME php artisan config:cache
docker exec $CONTAINER_NAME php artisan route:cache
docker exec $CONTAINER_NAME php artisan view:cache
echo "✅ Кэш пересоздан"
echo ""

# Шаг 7: Проверка APP_KEY после всех операций
echo "📝 Шаг 7: Финальная проверка APP_KEY..."
FINAL_APP_KEY=$(docker exec $CONTAINER_NAME grep "^APP_KEY=" /var/www/html/.env 2>/dev/null | cut -d'=' -f2 | tr -d ' ' | tr -d '"' | tr -d "'" || echo "")
if [ -z "$FINAL_APP_KEY" ] || [[ ! "$FINAL_APP_KEY" =~ ^base64: ]]; then
    echo "❌ ОШИБКА: APP_KEY все еще не установлен!"
    echo "Попробуйте выполнить вручную:"
    echo "  docker exec $CONTAINER_NAME php artisan key:generate --force"
    exit 1
else
    echo "✅ APP_KEY установлен в .env: ${FINAL_APP_KEY:0:30}..."
fi

# Проверяем что Laravel может прочитать APP_KEY
echo "📝 Проверка чтения APP_KEY Laravel..."
LARAVEL_APP_KEY=$(docker exec $CONTAINER_NAME php -r "
require '/var/www/html/vendor/autoload.php';
\$dotenv = Dotenv\Dotenv::createImmutable('/var/www/html');
\$dotenv->load();
echo \$_ENV['APP_KEY'] ?? 'NOT SET';
" 2>/dev/null || echo "ERROR")

if [ "$LARAVEL_APP_KEY" == "NOT SET" ] || [ "$LARAVEL_APP_KEY" == "ERROR" ] || [ -z "$LARAVEL_APP_KEY" ]; then
    echo "⚠️  ВНИМАНИЕ: Laravel не может прочитать APP_KEY из .env файла!"
    echo "Возможные причины:"
    echo "  1. Проблемы с правами доступа к .env файлу"
    echo "  2. Неправильная кодировка .env файла"
    echo "  3. Проблемы с переменными окружения в docker-compose"
    echo ""
    echo "Попробуйте:"
    echo "  1. Проверить права: docker exec $CONTAINER_NAME ls -la /var/www/html/.env"
    echo "  2. Проверить содержимое: docker exec $CONTAINER_NAME cat /var/www/html/.env | grep APP_KEY"
    echo "  3. Убедиться что в docker-compose.production.yml нет APP_KEY в environment"
else
    echo "✅ Laravel может прочитать APP_KEY: ${LARAVEL_APP_KEY:0:30}..."
fi
echo ""

# Шаг 8: Проверка что класс загружается
echo "📝 Шаг 8: Проверка загрузки классов..."
if docker exec $CONTAINER_NAME php -r "require '/var/www/html/vendor/autoload.php'; echo class_exists('App\Services\Payment\YooKassaService') ? 'OK' : 'FAIL';" | grep -q "OK"; then
    echo "✅ Класс YooKassaService загружается правильно"
else
    echo "⚠️  Проблема с загрузкой класса YooKassaService"
fi
echo ""

echo "=========================================="
echo "✅ Исправление завершено!"
echo "=========================================="
echo ""
echo "Перезапустите контейнер для применения изменений:"
echo "  docker-compose restart laravel"
echo ""
echo "Проверьте логи на наличие ошибок:"
echo "  docker-compose logs laravel --tail=50"
echo ""

