#!/bin/bash

# Скрипт для полной очистки и пересоздания контейнеров

echo "=== Полная очистка и пересоздание контейнеров ==="
echo ""
echo "⚠️  ВНИМАНИЕ: Этот скрипт удалит все контейнеры и volumes!"
echo "Все данные в базе данных будут потеряны!"
echo ""
read -p "Вы уверены, что хотите продолжить? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Отменено пользователем"
    exit 0
fi

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

if [ -z "$DB_PASSWORD" ]; then
    echo "❌ DB_PASSWORD не установлен в корневом .env файле!"
    exit 1
fi

echo ""
echo "1. Останавливаем все контейнеры..."
docker-compose -f docker-compose.production.yml down 2>&1
echo "✅ Контейнеры остановлены"
echo ""

echo "2. Удаляем контейнеры..."
docker-compose -f docker-compose.production.yml rm -f 2>&1
echo "✅ Контейнеры удалены"
echo ""

echo "3. Удаляем volumes (база данных будет очищена)..."
read -p "Удалить volumes? Это удалит все данные в БД! (yes/no): " delete_volumes

if [ "$delete_volumes" = "yes" ]; then
    docker volume ls | grep "hunter-photo" | awk '{print $2}' | xargs -r docker volume rm 2>&1
    echo "✅ Volumes удалены"
else
    echo "⚠️  Volumes не удалены (данные в БД сохранятся)"
fi
echo ""

echo "4. Синхронизируем пароли во всех .env файлах..."
if [ -f "generate-env-files.sh" ]; then
    ./generate-env-files.sh 2>&1 | tail -15
else
    echo "⚠️  Скрипт generate-env-files.sh не найден"
fi
echo ""

echo "5. Проверяем laravel/.env файл..."
if [ -f "laravel/.env" ]; then
    echo "✅ Файл laravel/.env существует"
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
    if [ -f "laravel/.env.example" ]; then
        cp laravel/.env.example laravel/.env
        echo "✅ Создан laravel/.env из .env.example"
        # Обновляем настройки БД
        sed -i '/^DB_PASSWORD=/d' laravel/.env
        echo "DB_PASSWORD=$DB_PASSWORD" >> laravel/.env
        sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=pgsql/' laravel/.env
        sed -i 's/^DB_HOST=.*/DB_HOST=postgres/' laravel/.env
        sed -i 's/^DB_PORT=.*/DB_PORT=5432/' laravel/.env
        sed -i "s/^DB_DATABASE=.*/DB_DATABASE=$DB_DATABASE/" laravel/.env
        sed -i "s/^DB_USERNAME=.*/DB_USERNAME=$DB_USERNAME/" laravel/.env
        echo "✅ Настройки БД обновлены в laravel/.env"
    else
        echo "❌ laravel/.env.example не найден!"
        exit 1
    fi
fi
echo ""

echo "6. Очищаем кеши Laravel на хосте (если есть)..."
if [ -d "laravel/bootstrap/cache" ]; then
    rm -f laravel/bootstrap/cache/*.php 2>/dev/null
    echo "✅ Кеши Laravel очищены"
fi
echo ""

echo "7. Собираем образы заново..."
docker-compose -f docker-compose.production.yml build --no-cache 2>&1 | tail -20
if [ ${PIPESTATUS[0]} -eq 0 ]; then
    echo "✅ Образы собраны"
else
    echo "❌ Ошибка при сборке образов!"
    exit 1
fi
echo ""

echo "8. Запускаем контейнеры..."
docker-compose -f docker-compose.production.yml up -d 2>&1
sleep 10
echo ""

echo "9. Проверяем статус контейнеров..."
docker-compose -f docker-compose.production.yml ps
echo ""

echo "10. Ждем готовности PostgreSQL..."
for i in {1..30}; do
    if docker exec hunter-photo-postgres pg_isready -U $DB_USERNAME > /dev/null 2>&1; then
        echo "✅ PostgreSQL готов"
        break
    fi
    echo "Ожидание PostgreSQL... ($i/30)"
    sleep 2
done
echo ""

echo "11. Синхронизируем пароль в PostgreSQL..."
docker exec hunter-photo-postgres psql -U postgres -d postgres -c "ALTER USER $DB_USERNAME WITH PASSWORD '$DB_PASSWORD';" 2>&1
echo "✅ Пароль в PostgreSQL обновлен"
echo ""

echo "12. Проверяем подключение к PostgreSQL..."
docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c "SELECT current_database(), current_user;" 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Подключение к PostgreSQL работает"
else
    echo "❌ Подключение к PostgreSQL не работает!"
fi
echo ""

echo "13. Ждем готовности Laravel контейнера..."
sleep 10

if docker ps | grep -q "hunter-photo-laravel"; then
    echo "✅ Контейнер Laravel запущен"
    echo ""
    
    echo "14. Проверяем переменные окружения в Laravel контейнере..."
    docker exec hunter-photo-laravel env | grep "^DB_" | sort
    echo ""
    
    echo "15. Проверяем .env файл в Laravel контейнере..."
    if docker exec hunter-photo-laravel test -f /var/www/html/.env; then
        echo "✅ Файл .env существует в контейнере"
        echo "Настройки БД:"
        docker exec hunter-photo-laravel grep "^DB_" /var/www/html/.env | grep -v "PASSWORD"
        echo ""
        
        CONTAINER_PASSWORD=$(docker exec hunter-photo-laravel grep "^DB_PASSWORD=" /var/www/html/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs)
        if [ "$CONTAINER_PASSWORD" = "$DB_PASSWORD" ]; then
            echo "✅ Пароль в .env контейнера совпадает с корневым .env"
        else
            echo "⚠️  Пароль в .env контейнера НЕ совпадает!"
            echo "   Длина в контейнере: ${#CONTAINER_PASSWORD}"
            echo "   Длина ожидаемая: ${#DB_PASSWORD}"
        fi
    else
        echo "❌ Файл .env не существует в контейнере!"
    fi
    echo ""
    
    echo "16. Очищаем кеши Laravel..."
    docker exec hunter-photo-laravel php artisan config:clear 2>&1
    docker exec hunter-photo-laravel php artisan cache:clear 2>&1
    docker exec hunter-photo-laravel php artisan route:clear 2>&1
    docker exec hunter-photo-laravel php artisan view:clear 2>&1
    echo "✅ Кеши очищены"
    echo ""
    
    echo "17. Тестируем подключение через PHP PDO..."
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
    " 2>&1
    echo ""
    
    if [ $? -eq 0 ]; then
        echo "18. Тестируем подключение через Laravel Artisan..."
        docker exec hunter-photo-laravel php artisan db:show 2>&1 | head -20
        echo ""
        
        echo "✅✅✅ ПРОБЛЕМА РЕШЕНА! ✅✅✅"
        echo ""
        echo "Теперь можно выполнить миграции:"
        echo "  docker exec hunter-photo-laravel php artisan migrate --force"
    else
        echo "❌ Подключение через PHP PDO не работает"
        echo ""
        echo "Проверьте логи контейнера:"
        echo "  docker logs hunter-photo-laravel"
    fi
else
    echo "❌ Контейнер Laravel не запущен!"
    echo ""
    echo "Проверьте логи:"
    echo "  docker logs hunter-photo-laravel"
fi

echo ""
echo "=== Готово ==="

