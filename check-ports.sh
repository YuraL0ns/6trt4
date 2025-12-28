#!/bin/bash

echo "🔍 Проверка портов и контейнеров..."
echo ""

# Проверка Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker не установлен"
    exit 1
fi

# Проверка запущенных контейнеров
echo "📦 Запущенные контейнеры:"
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" | grep hunter-photo || echo "Нет запущенных контейнеров hunter-photo"
echo ""

# Проверка портов
echo "🔌 Проверка портов на хосте:"
echo "Порт 8000 (Laravel):"
netstat -tuln 2>/dev/null | grep :8000 || ss -tuln 2>/dev/null | grep :8000 || echo "  Порт 8000 не слушается на хосте"
echo "Порт 8001 (FastAPI):"
netstat -tuln 2>/dev/null | grep :8001 || ss -tuln 2>/dev/null | grep :8001 || echo "  Порт 8001 не слушается на хосте"
echo "Порт 5432 (PostgreSQL):"
netstat -tuln 2>/dev/null | grep :5432 || ss -tuln 2>/dev/null | grep :5432 || echo "  Порт 5432 не слушается на хосте"
echo ""

# Проверка логов Laravel
echo "📋 Последние логи Laravel:"
docker-compose logs --tail=20 laravel 2>/dev/null || echo "Контейнер Laravel не найден"
echo ""

# Проверка доступности
echo "🌐 Проверка доступности:"
echo "Laravel (http://localhost:8000):"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost:8000 2>/dev/null || echo "  Недоступен"
echo "FastAPI (http://localhost:8001):"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost:8001 2>/dev/null || echo "  Недоступен"
echo ""


