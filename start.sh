#!/bin/bash

echo "🚀 Запуск Hunter-Photo проекта через Docker..."

# Проверка Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker не установлен. Установите Docker: https://www.docker.com/get-started"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose не установлен. Установите Docker Compose"
    exit 1
fi

# Копирование .env файлов
if [ ! -f "laravel/.env" ]; then
    echo "📝 Создание laravel/.env из .env.docker..."
    cp laravel/.env.docker laravel/.env
fi

if [ ! -f "fastapi/.env" ]; then
    echo "📝 Создание fastapi/.env из .env.docker..."
    cp fastapi/.env.docker fastapi/.env
fi

# Запуск контейнеров
echo "🐳 Запуск Docker контейнеров..."
docker-compose up -d

# Ожидание готовности PostgreSQL
echo "⏳ Ожидание готовности PostgreSQL..."
sleep 10

# Инициализация Laravel
echo "🔧 Инициализация Laravel..."
docker-compose exec -T laravel sh -c "
    composer install --no-interaction &&
    php artisan key:generate --force &&
    php artisan migrate --force
" || echo "⚠️  Некоторые команды Laravel не выполнились. Проверьте логи."

echo ""
echo "✅ Проект запущен!"
echo ""
echo "📍 Доступные сервисы:"
echo "   - Laravel:  http://localhost:8000"
echo "   - FastAPI:  http://localhost:8001"
echo "   - API Docs: http://localhost:8001/docs"
echo ""
echo "📋 Полезные команды:"
echo "   - Просмотр логов: docker-compose logs -f"
echo "   - Остановка:      docker-compose stop"
echo "   - Создать админа: docker-compose exec laravel php artisan admin:create"
echo ""


