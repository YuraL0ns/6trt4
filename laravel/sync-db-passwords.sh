#!/bin/bash

# Скрипт для синхронизации паролей БД во всех .env файлах

echo "=== Синхронизация паролей БД во всех .env файлах ==="
echo ""

# Загружаем переменные окружения из корневого .env файла
if [ -f ".env" ]; then
    set -a
    source .env
    set +a
    echo "✅ Загружен корневой .env файл"
else
    echo "❌ Файл .env не найден в корне проекта!"
    exit 1
fi

DB_PASSWORD=${DB_PASSWORD:-}
DB_USERNAME=${DB_USERNAME:-hunter_photo}
DB_DATABASE=${DB_DATABASE:-hunter_photo}

if [ -z "$DB_PASSWORD" ]; then
    echo "❌ DB_PASSWORD не установлен в корневом .env файле!"
    exit 1
fi

echo "Пароль из корневого .env: ${DB_PASSWORD:+установлен (длина: ${#DB_PASSWORD})}"
echo ""

# 1. Проверяем и обновляем laravel/.env
echo "=== 1. Проверка laravel/.env ==="
if [ -f "laravel/.env" ]; then
    LARAVEL_PASSWORD=$(grep "^DB_PASSWORD=" laravel/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs)
    echo "Текущий пароль в laravel/.env: ${LARAVEL_PASSWORD:+установлен (длина: ${#LARAVEL_PASSWORD})}"
    
    if [ "$LARAVEL_PASSWORD" != "$DB_PASSWORD" ]; then
        echo "⚠️  Пароли НЕ совпадают! Обновляем..."
        # Создаем резервную копию
        cp laravel/.env laravel/.env.backup.$(date +%Y%m%d_%H%M%S)
        # Удаляем старую строку
        sed -i '/^DB_PASSWORD=/d' laravel/.env
        # Добавляем новую
        echo "DB_PASSWORD=$DB_PASSWORD" >> laravel/.env
        echo "✅ Пароль обновлен в laravel/.env"
    else
        echo "✅ Пароли совпадают"
    fi
else
    echo "❌ Файл laravel/.env не найден!"
fi
echo ""

# 2. Проверяем и обновляем fastapi/.env
echo "=== 2. Проверка fastapi/.env ==="
if [ -f "fastapi/.env" ]; then
    FASTAPI_DATABASE_URL=$(grep "^DATABASE_URL=" fastapi/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'")
    echo "Текущий DATABASE_URL в fastapi/.env: ${FASTAPI_DATABASE_URL:+установлен}"
    
    # Извлекаем пароль из DATABASE_URL
    FASTAPI_PASSWORD=$(echo "$FASTAPI_DATABASE_URL" | sed -n 's|.*://[^:]*:\([^@]*\)@.*|\1|p')
    
    if [ "$FASTAPI_PASSWORD" != "$DB_PASSWORD" ]; then
        echo "⚠️  Пароль в DATABASE_URL НЕ совпадает! Обновляем..."
        # Создаем резервную копию
        cp fastapi/.env fastapi/.env.backup.$(date +%Y%m%d_%H%M%S)
        # Обновляем DATABASE_URL
        NEW_DATABASE_URL="postgresql://${DB_USERNAME}:${DB_PASSWORD}@postgres:5432/${DB_DATABASE}"
        sed -i "s|^DATABASE_URL=.*|DATABASE_URL=$NEW_DATABASE_URL|" fastapi/.env
        echo "✅ DATABASE_URL обновлен в fastapi/.env"
        echo "   Новый URL: postgresql://${DB_USERNAME}:***@postgres:5432/${DB_DATABASE}"
    else
        echo "✅ Пароль в DATABASE_URL совпадает"
    fi
else
    echo "❌ Файл fastapi/.env не найден!"
fi
echo ""

# 3. Проверяем пароль в PostgreSQL
echo "=== 3. Проверка пароля в PostgreSQL ==="
if docker ps | grep -q "hunter-photo-postgres"; then
    echo "Проверяем подключение к PostgreSQL с паролем из корневого .env..."
    docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c 'SELECT current_database(), current_user;' 2>&1
    if [ $? -eq 0 ]; then
        echo "✅ PostgreSQL принимает подключение с паролем из корневого .env"
    else
        echo "❌ PostgreSQL НЕ принимает подключение с паролем из корневого .env"
        echo ""
        echo "Возможно, пароль в PostgreSQL отличается. Проверьте:"
        echo "  docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"\\du\""
        echo ""
        echo "Если нужно изменить пароль пользователя в PostgreSQL:"
        echo "  docker exec hunter-photo-postgres psql -U postgres -d postgres -c \"ALTER USER $DB_USERNAME WITH PASSWORD '$DB_PASSWORD';\""
    fi
else
    echo "⚠️  Контейнер PostgreSQL не запущен"
fi
echo ""

# 4. Проверяем переменные окружения в docker-compose
echo "=== 4. Проверка переменных окружения в docker-compose ==="
if [ -f "docker-compose.production.yml" ]; then
    echo "Проверяем, что DB_PASSWORD передается в контейнеры..."
    # Проверяем, что переменная используется в docker-compose
    if grep -q "DB_PASSWORD: \${DB_PASSWORD}" docker-compose.production.yml; then
        echo "✅ docker-compose.production.yml использует DB_PASSWORD из корневого .env"
    else
        echo "⚠️  docker-compose.production.yml может не использовать DB_PASSWORD из корневого .env"
    fi
else
    echo "⚠️  Файл docker-compose.production.yml не найден"
fi
echo ""

# 5. Итоговая проверка
echo "=== 5. Итоговая проверка ==="
echo "Пароли во всех файлах:"
echo "  Корневой .env:        ${DB_PASSWORD:+установлен (длина: ${#DB_PASSWORD})}"
if [ -f "laravel/.env" ]; then
    LARAVEL_PASSWORD=$(grep "^DB_PASSWORD=" laravel/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs)
    echo "  laravel/.env:          ${LARAVEL_PASSWORD:+установлен (длина: ${#LARAVEL_PASSWORD})}"
    if [ "$LARAVEL_PASSWORD" = "$DB_PASSWORD" ]; then
        echo "    ✅ Совпадает с корневым .env"
    else
        echo "    ❌ НЕ совпадает с корневым .env"
    fi
fi
if [ -f "fastapi/.env" ]; then
    FASTAPI_DATABASE_URL=$(grep "^DATABASE_URL=" fastapi/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'")
    FASTAPI_PASSWORD=$(echo "$FASTAPI_DATABASE_URL" | sed -n 's|.*://[^:]*:\([^@]*\)@.*|\1|p')
    echo "  fastapi/.env (DATABASE_URL): ${FASTAPI_PASSWORD:+установлен (длина: ${#FASTAPI_PASSWORD})}"
    if [ "$FASTAPI_PASSWORD" = "$DB_PASSWORD" ]; then
        echo "    ✅ Совпадает с корневым .env"
    else
        echo "    ❌ НЕ совпадает с корневым .env"
    fi
fi
echo ""

echo "=== Готово ==="
echo ""
echo "Если все пароли совпадают, но Laravel все еще не подключается:"
echo "1. Очистите кеши Laravel:"
echo "   docker exec hunter-photo-laravel php artisan config:clear"
echo "   docker exec hunter-photo-laravel php artisan cache:clear"
echo ""
echo "2. Перезапустите контейнер Laravel:"
echo "   docker-compose -f docker-compose.production.yml restart laravel"
echo ""
echo "3. Проверьте логи:"
echo "   docker logs hunter-photo-laravel"
echo ""
echo "4. Если проблема сохраняется, запустите полную диагностику:"
echo "   ./fix-laravel-db-auth.sh"

