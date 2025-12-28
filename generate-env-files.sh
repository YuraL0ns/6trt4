#!/bin/bash

# Скрипт для генерации всех .env файлов из единого .env.unified файла

echo "=== Генерация .env файлов из единого источника ==="
echo ""

# Проверяем наличие единого .env файла
UNIFIED_ENV=".env.unified"
if [ ! -f "$UNIFIED_ENV" ]; then
    echo "❌ Файл $UNIFIED_ENV не найден!"
    echo ""
    echo "Создайте файл $UNIFIED_ENV на основе .env.unified.example:"
    echo "  cp .env.unified.example .env.unified"
    echo "  nano .env.unified  # Заполните все переменные"
    exit 1
fi

echo "✅ Найден файл $UNIFIED_ENV"
echo ""

# Загружаем переменные из единого файла
set -a
source "$UNIFIED_ENV"
set +a

# Проверяем обязательные переменные
REQUIRED_VARS=("DB_PASSWORD" "DB_USERNAME" "DB_DATABASE" "S3_CLOUD_ACCESS_KEY" "S3_CLOUD_SECRET_KEY" "S3_BUCKET_NAME")
MISSING_VARS=()

for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ]; then
        MISSING_VARS+=("$var")
    fi
done

if [ ${#MISSING_VARS[@]} -ne 0 ]; then
    echo "❌ Отсутствуют обязательные переменные:"
    for var in "${MISSING_VARS[@]}"; do
        echo "   - $var"
    done
    echo ""
    echo "Заполните их в файле $UNIFIED_ENV"
    exit 1
fi

echo "✅ Все обязательные переменные найдены"
echo ""

# 1. Генерируем корневой .env для docker-compose
echo "=== 1. Генерация корневого .env (для docker-compose) ==="
cat > .env << EOF
# Автоматически сгенерировано из $UNIFIED_ENV
# Не редактируйте вручную! Используйте $UNIFIED_ENV и запустите generate-env-files.sh

# Database
DB_HOST=${DB_HOST:-postgres}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

# YooKassa
YOO_KASSA_SHOP_ID=${YOO_KASSA_SHOP_ID}
YOO_KASSA_SECRET_KEY=${YOO_KASSA_SECRET_KEY}

# S3 Storage
S3_CLOUD_URL=${S3_CLOUD_URL}
S3_CLOUD_ACCESS_KEY=${S3_CLOUD_ACCESS_KEY}
S3_CLOUD_SECRET_KEY=${S3_CLOUD_SECRET_KEY}
S3_CLOUD_REGION=${S3_CLOUD_REGION}
S3_BUCKET_NAME=${S3_BUCKET_NAME}

# Docker Compose
CLEAR_EVENTS_ON_START=${CLEAR_EVENTS_ON_START:-false}
EOF
echo "✅ Создан .env (корневой)"
echo ""

# 2. Генерируем laravel/.env
echo "=== 2. Генерация laravel/.env ==="
if [ ! -f "laravel/.env.example" ]; then
    echo "⚠️  Файл laravel/.env.example не найден, создаем базовый .env"
fi

cat > laravel/.env << EOF
APP_NAME=${APP_NAME:-Laravel}
APP_ENV=${APP_ENV:-production}
APP_KEY=
APP_DEBUG=${APP_DEBUG:-false}
APP_TIMEZONE=${APP_TIMEZONE:-UTC}
APP_URL=${APP_URL:-https://hunter-photo.ru}

APP_LOCALE=${APP_LOCALE:-ru}
APP_FALLBACK_LOCALE=${APP_FALLBACK_LOCALE:-ru}
APP_FAKER_LOCALE=ru_RU

APP_MAINTENANCE_DRIVER=file

PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=${LOG_LEVEL:-debug}

# PostgreSQL Database
DB_CONNECTION=pgsql
DB_HOST=${DB_HOST:-postgres}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

# Redis
REDIS_CLIENT=phpredis
REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PASSWORD=null
REDIS_PORT=${REDIS_PORT:-6379}

# Session
SESSION_DRIVER=${SESSION_DRIVER:-database}
SESSION_LIFETIME=${SESSION_LIFETIME:-120}
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

# Broadcast
BROADCAST_CONNECTION=log

# Filesystem
FILESYSTEM_DISK=${FILESYSTEM_DISK:-local}

# Queue
QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}

# Cache
CACHE_STORE=${CACHE_STORE:-database}
CACHE_PREFIX=

# Mail
MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

# AWS (не используется, но может быть нужно для S3)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Vite
VITE_APP_NAME="\${APP_NAME}"

# FastAPI
FASTAPI_URL=${FASTAPI_URL:-http://fastapi:8000}
FASTAPI_TIMEOUT=${FASTAPI_TIMEOUT:-300}

# YooKassa
YOOKASSA_SHOP_ID=${YOO_KASSA_SHOP_ID}
YOOKASSA_SECRET_KEY=${YOO_KASSA_SECRET_KEY}
YOOKASSA_TEST_MODE=${YOOKASSA_TEST_MODE:-false}
EOF
echo "✅ Создан laravel/.env"
echo ""

# 3. Генерируем fastapi/.env
echo "=== 3. Генерация fastapi/.env ==="
cat > fastapi/.env << EOF
# Автоматически сгенерировано из $UNIFIED_ENV
# Не редактируйте вручную! Используйте $UNIFIED_ENV и запустите generate-env-files.sh

# Database
DATABASE_URL=postgresql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST:-postgres}:${DB_PORT:-5432}/${DB_DATABASE}

# Redis
REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PORT=${REDIS_PORT:-6379}
REDIS_DB=${REDIS_DB:-0}

# Celery
CELERY_BROKER_URL=${CELERY_BROKER_URL:-redis://redis:6379/0}
CELERY_RESULT_BACKEND=${CELERY_RESULT_BACKEND:-redis://redis:6379/0}

# File Storage
STORAGE_PATH=${STORAGE_PATH:-/var/www/html/storage}
UPLOAD_MAX_SIZE=${UPLOAD_MAX_SIZE:-20971520}
UPLOAD_MAX_FILES=${UPLOAD_MAX_FILES:-15000}

# S3 Storage
S3_CLOUD_URL=${S3_CLOUD_URL}
S3_CLOUD_ACCESS_KEY=${S3_CLOUD_ACCESS_KEY}
S3_CLOUD_SECRET_KEY=${S3_CLOUD_SECRET_KEY}
S3_CLOUD_REGION=${S3_CLOUD_REGION}
S3_BUCKET_NAME=${S3_BUCKET_NAME}

# ML Models
INSIGHTFACE_MODEL_PATH=${INSIGHTFACE_MODEL_PATH}
EASYOCR_LANGUAGES=${EASYOCR_LANGUAGES:-en,ru}

# API
API_HOST=${API_HOST:-0.0.0.0}
API_PORT=${API_PORT:-8000}
API_PREFIX=${API_PREFIX:-/api/v1}

# Environment
ENVIRONMENT=${ENVIRONMENT:-production}

# Security
ALLOWED_ORIGINS=${ALLOWED_ORIGINS:-https://hunter-photo.ru,http://localhost:8000}
EOF
echo "✅ Создан fastapi/.env"
echo ""

echo "=== Готово ==="
echo ""
echo "Все .env файлы успешно сгенерированы из $UNIFIED_ENV"
echo ""
echo "Сгенерированные файлы:"
echo "  ✅ .env (корневой, для docker-compose)"
echo "  ✅ laravel/.env"
echo "  ✅ fastapi/.env"
echo ""
echo "Теперь вы можете:"
echo "  1. Запустить сборку: docker-compose -f docker-compose.production.yml build"
echo "  2. Запустить контейнеры: docker-compose -f docker-compose.production.yml up -d"
echo ""
echo "Для обновления .env файлов после изменения $UNIFIED_ENV:"
echo "  ./generate-env-files.sh"

