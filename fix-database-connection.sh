#!/bin/bash

# Скрипт для диагностики и исправления проблем с подключением к базе данных

echo "=== Диагностика подключения к базе данных ==="
echo ""

# Проверяем, запущен ли контейнер Laravel
if ! docker ps | grep -q "hunter-photo-laravel"; then
    echo "❌ Контейнер Laravel не запущен!"
    echo "Запустите: docker-compose -f docker-compose.production.yml up -d laravel"
    exit 1
fi

echo "✅ Контейнер Laravel запущен"
echo ""

# Проверяем переменные окружения в контейнере
echo "=== Переменные окружения в контейнере ==="
docker exec hunter-photo-laravel env | grep -E "^DB_" | sort
echo ""

# Проверяем, существует ли .env файл
if [ -f "laravel/.env" ]; then
    echo "⚠️  Найден файл laravel/.env"
    echo "Проверяем настройки БД в .env:"
    grep -E "^DB_" laravel/.env | grep -v "PASSWORD" || echo "Нет настроек DB_ в .env"
    echo ""
    
    # Проверяем, не конфликтуют ли настройки
    ENV_DB_HOST=$(grep "^DB_HOST=" laravel/.env | cut -d'=' -f2 | tr -d '"' | tr -d "'" | xargs)
    ENV_DB_DATABASE=$(grep "^DB_DATABASE=" laravel/.env | cut -d'=' -f2 | tr -d '"' | tr -d "'" | xargs)
    
    if [ -n "$ENV_DB_HOST" ] && [ "$ENV_DB_HOST" != "postgres" ]; then
        echo "⚠️  ВНИМАНИЕ: DB_HOST в .env файле ($ENV_DB_HOST) отличается от Docker (postgres)"
        echo "Laravel будет использовать значение из .env файла!"
    fi
    
    if [ -n "$ENV_DB_DATABASE" ] && [ "$ENV_DB_DATABASE" != "hunter_photo" ]; then
        echo "⚠️  ВНИМАНИЕ: DB_DATABASE в .env файле ($ENV_DB_DATABASE) отличается от Docker (hunter_photo)"
        echo "Laravel будет использовать значение из .env файла!"
    fi
    echo ""
fi

# Проверяем подключение к базе данных
echo "=== Проверка подключения к базе данных ==="
docker exec hunter-photo-laravel php artisan db:show 2>&1 | head -20 || echo "Ошибка при проверке подключения"
echo ""

# Очищаем кеш конфигурации
echo "=== Очистка кеша конфигурации ==="
docker exec hunter-photo-laravel php artisan config:clear
docker exec hunter-photo-laravel php artisan cache:clear
echo "✅ Кеш очищен"
echo ""

# Пробуем подключиться к базе данных через PHP
echo "=== Тест подключения через PHP ==="
docker exec hunter-photo-laravel php -r "
try {
    \$pdo = new PDO(
        'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
    echo '✅ Подключение успешно!' . PHP_EOL;
    echo 'База данных: ' . getenv('DB_DATABASE') . PHP_EOL;
    echo 'Хост: ' . getenv('DB_HOST') . PHP_EOL;
} catch (PDOException \$e) {
    echo '❌ Ошибка подключения: ' . \$e->getMessage() . PHP_EOL;
    echo 'DB_HOST: ' . getenv('DB_HOST') . PHP_EOL;
    echo 'DB_PORT: ' . getenv('DB_PORT') . PHP_EOL;
    echo 'DB_DATABASE: ' . getenv('DB_DATABASE') . PHP_EOL;
    echo 'DB_USERNAME: ' . getenv('DB_USERNAME') . PHP_EOL;
}
"
echo ""

echo "=== Рекомендации ==="
echo "1. Убедитесь, что в корневом .env файле указаны:"
echo "   DB_DATABASE=hunter_photo"
echo "   DB_USERNAME=hunter_photo"
echo "   DB_PASSWORD=ваш_пароль"
echo ""
echo "2. Если в laravel/.env есть настройки DB_*, они имеют приоритет над Docker"
echo "   Удалите или закомментируйте их в laravel/.env, чтобы использовать настройки из Docker"
echo ""
echo "3. После изменений перезапустите контейнер:"
echo "   docker-compose -f docker-compose.production.yml restart laravel"

