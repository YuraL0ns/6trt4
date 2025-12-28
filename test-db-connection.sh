#!/bin/bash

# Скрипт для проверки подключения к базе данных через PostgreSQL контейнер

echo "=== Проверка подключения к базе данных через PostgreSQL ==="
echo ""

# Загружаем переменные окружения из .env файла (если есть)
if [ -f ".env" ]; then
    export $(grep -v '^#' .env | grep -E "DB_" | xargs)
fi

# Используем значения по умолчанию, если не указаны
DB_USERNAME=${DB_USERNAME:-hunter_photo}
DB_DATABASE=${DB_DATABASE:-hunter_photo}
DB_PASSWORD=${DB_PASSWORD:-}

# Проверяем, запущен ли контейнер PostgreSQL
if ! docker ps | grep -q "hunter-photo-postgres"; then
    echo "❌ Контейнер PostgreSQL не запущен!"
    echo "Запустите: docker-compose -f docker-compose.production.yml up -d postgres"
    exit 1
fi

echo "✅ Контейнер PostgreSQL запущен"
echo ""

# Проверяем готовность PostgreSQL
echo "=== Проверка готовности PostgreSQL ==="
docker exec hunter-photo-postgres pg_isready -U $DB_USERNAME
if [ $? -eq 0 ]; then
    echo "✅ PostgreSQL готов к подключениям"
else
    echo "❌ PostgreSQL не готов"
    exit 1
fi
echo ""

# Проверяем версию PostgreSQL
echo "=== Версия PostgreSQL ==="
docker exec hunter-photo-postgres psql -U $DB_USERNAME -d postgres -c "SELECT version();" 2>&1 | head -3
echo ""

# Проверяем список баз данных
echo "=== Список баз данных ==="
docker exec hunter-photo-postgres psql -U $DB_USERNAME -d postgres -c "\l" 2>&1 | grep -E "Name|hunter_photo" | head -10
echo ""

# Проверяем подключение к базе данных hunter_photo
echo "=== Проверка подключения к базе данных '$DB_DATABASE' ==="
docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c "SELECT current_database(), current_user, version();" 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Подключение к базе данных '$DB_DATABASE' успешно!"
else
    echo "❌ Ошибка подключения к базе данных '$DB_DATABASE'"
    echo ""
    echo "Возможные причины:"
    echo "1. База данных '$DB_DATABASE' не существует"
    echo "2. Пользователь '$DB_USERNAME' не имеет прав доступа"
    echo "3. Неверный пароль"
    echo ""
    echo "Проверьте настройки в корневом .env файле:"
    echo "  DB_DATABASE=$DB_DATABASE"
    echo "  DB_USERNAME=$DB_USERNAME"
    echo "  DB_PASSWORD=***"
    exit 1
fi
echo ""

# Проверяем список таблиц (если база данных существует)
echo "=== Список таблиц в базе данных ==="
docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c "\dt" 2>&1 | head -20
echo ""

# Проверяем подключение из Laravel контейнера
echo "=== Проверка подключения из Laravel контейнера ==="
if docker ps | grep -q "hunter-photo-laravel"; then
    echo "Проверяем переменные окружения в Laravel контейнере:"
    docker exec hunter-photo-laravel env | grep -E "^DB_" | sort
    echo ""
    
    echo "Тестируем подключение через PHP PDO:"
    docker exec hunter-photo-laravel php -r "
    try {
        \$pdo = new PDO(
            'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        echo '✅ Подключение из Laravel успешно!' . PHP_EOL;
        echo 'База данных: ' . getenv('DB_DATABASE') . PHP_EOL;
        echo 'Хост: ' . getenv('DB_HOST') . PHP_EOL;
        echo 'Порт: ' . getenv('DB_PORT') . PHP_EOL;
        echo 'Пользователь: ' . getenv('DB_USERNAME') . PHP_EOL;
    } catch (PDOException \$e) {
        echo '❌ Ошибка подключения из Laravel: ' . \$e->getMessage() . PHP_EOL;
        echo 'DB_HOST: ' . getenv('DB_HOST') . PHP_EOL;
        echo 'DB_PORT: ' . getenv('DB_PORT') . PHP_EOL;
        echo 'DB_DATABASE: ' . getenv('DB_DATABASE') . PHP_EOL;
        echo 'DB_USERNAME: ' . getenv('DB_USERNAME') . PHP_EOL;
    }
    "
else
    echo "⚠️  Контейнер Laravel не запущен"
fi
echo ""

echo "=== Рекомендации ==="
echo "Для интерактивного доступа к PostgreSQL используйте:"
echo "  docker-compose -f docker-compose.production.yml exec postgres bash"
echo ""
echo "Или напрямую через psql:"
echo "  docker exec -it hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE"
echo ""

