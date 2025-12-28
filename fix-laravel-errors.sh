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

if [ -z "$APP_KEY" ] || [ "$APP_KEY" == "" ] || [[ ! "$APP_KEY" =~ ^base64: ]]; then
    echo "⚠️  APP_KEY отсутствует или неверный. Генерирую новый..."
    docker exec $CONTAINER_NAME php artisan key:generate --force
    echo "✅ APP_KEY сгенерирован"
else
    echo "✅ APP_KEY уже установлен: ${APP_KEY:0:20}..."
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

# Шаг 3: Очистка всех кэшей
echo "📝 Шаг 3: Очистка кэшей..."
docker exec $CONTAINER_NAME php artisan config:clear
docker exec $CONTAINER_NAME php artisan route:clear
docker exec $CONTAINER_NAME php artisan view:clear
docker exec $CONTAINER_NAME php artisan cache:clear
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
FINAL_APP_KEY=$(docker exec $CONTAINER_NAME grep "^APP_KEY=" /var/www/html/.env 2>/dev/null | cut -d'=' -f2 || echo "")
if [ -z "$FINAL_APP_KEY" ] || [[ ! "$FINAL_APP_KEY" =~ ^base64: ]]; then
    echo "❌ ОШИБКА: APP_KEY все еще не установлен!"
    echo "Попробуйте выполнить вручную:"
    echo "  docker exec $CONTAINER_NAME php artisan key:generate --force"
    exit 1
else
    echo "✅ APP_KEY установлен: ${FINAL_APP_KEY:0:30}..."
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

