#!/bin/bash

# Скрипт для РУЧНОГО исправления пароля в laravel/.env файле
# Используйте этот скрипт, если контейнер не запускается

echo "=== Ручное исправление пароля в laravel/.env ==="
echo ""

# Загружаем переменные окружения из .env файла (если есть)
if [ -f ".env" ]; then
    # Используем более безопасный способ загрузки переменных
    set -a
    source .env
    set +a
else
    echo "❌ Файл .env не найден в корне проекта!"
    echo "Создайте файл .env с переменной DB_PASSWORD"
    exit 1
fi

DB_USERNAME=${DB_USERNAME:-hunter_photo}
DB_DATABASE=${DB_DATABASE:-hunter_photo}
DB_PASSWORD=${DB_PASSWORD:-}

if [ -z "$DB_PASSWORD" ]; then
    echo "❌ DB_PASSWORD не установлен в корневом .env файле!"
    echo ""
    echo "Добавьте в корневой .env файл:"
    echo "  DB_PASSWORD=ваш_пароль"
    exit 1
fi

echo "Найден пароль из корневого .env (длина: ${#DB_PASSWORD} символов)"
echo ""

# Проверяем, существует ли laravel/.env
if [ ! -f "laravel/.env" ]; then
    echo "❌ Файл laravel/.env не найден!"
    echo "Создайте его из примера:"
    echo "  cp laravel/.env.example laravel/.env"
    exit 1
fi

echo "1. Сохраняем резервную копию laravel/.env..."
cp laravel/.env laravel/.env.backup.$(date +%Y%m%d_%H%M%S)
echo "✅ Резервная копия создана"
echo ""

echo "2. Удаляем старую строку DB_PASSWORD из laravel/.env..."
sed -i '/^DB_PASSWORD=/d' laravel/.env
echo "✅ Старая строка удалена"
echo ""

echo "3. Добавляем новый пароль в laravel/.env..."
# Добавляем пароль в конец файла (или после DB_USERNAME, если он есть)
if grep -q "^DB_USERNAME=" laravel/.env; then
    # Вставляем после DB_USERNAME
    sed -i "/^DB_USERNAME=/a DB_PASSWORD=$DB_PASSWORD" laravel/.env
else
    # Добавляем в конец
    echo "DB_PASSWORD=$DB_PASSWORD" >> laravel/.env
fi
echo "✅ Новый пароль добавлен"
echo ""

echo "4. Проверяем результат..."
NEW_PASSWORD=$(grep "^DB_PASSWORD=" laravel/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'")
if [ "$NEW_PASSWORD" = "$DB_PASSWORD" ]; then
    echo "✅ Пароль в laravel/.env совпадает с паролем из корневого .env"
else
    echo "⚠️  Пароль в laravel/.env НЕ совпадает!"
    echo "   Длина в .env: ${#NEW_PASSWORD}"
    echo "   Длина ожидаемая: ${#DB_PASSWORD}"
    echo ""
    echo "Попробуйте вручную отредактировать laravel/.env:"
    echo "  nano laravel/.env"
    echo "  Найдите строку DB_PASSWORD= и замените на:"
    echo "  DB_PASSWORD=$DB_PASSWORD"
    exit 1
fi
echo ""

echo "5. Проверяем другие настройки БД в laravel/.env..."
echo "DB_CONNECTION: $(grep "^DB_CONNECTION=" laravel/.env | cut -d'=' -f2 || echo 'не установлен')"
echo "DB_HOST: $(grep "^DB_HOST=" laravel/.env | cut -d'=' -f2 || echo 'не установлен')"
echo "DB_PORT: $(grep "^DB_PORT=" laravel/.env | cut -d'=' -f2 || echo 'не установлен')"
echo "DB_DATABASE: $(grep "^DB_DATABASE=" laravel/.env | cut -d'=' -f2 || echo 'не установлен')"
echo "DB_USERNAME: $(grep "^DB_USERNAME=" laravel/.env | cut -d'=' -f2 || echo 'не установлен')"
echo ""

echo "6. Обновляем другие настройки БД, если они не совпадают..."
if ! grep -q "^DB_CONNECTION=pgsql" laravel/.env; then
    sed -i '/^DB_CONNECTION=/d' laravel/.env
    echo "DB_CONNECTION=pgsql" >> laravel/.env
    echo "✅ DB_CONNECTION обновлен"
fi

if ! grep -q "^DB_HOST=postgres" laravel/.env; then
    sed -i '/^DB_HOST=/d' laravel/.env
    echo "DB_HOST=postgres" >> laravel/.env
    echo "✅ DB_HOST обновлен"
fi

if ! grep -q "^DB_PORT=5432" laravel/.env; then
    sed -i '/^DB_PORT=/d' laravel/.env
    echo "DB_PORT=5432" >> laravel/.env
    echo "✅ DB_PORT обновлен"
fi

if ! grep -q "^DB_DATABASE=$DB_DATABASE" laravel/.env; then
    sed -i '/^DB_DATABASE=/d' laravel/.env
    echo "DB_DATABASE=$DB_DATABASE" >> laravel/.env
    echo "✅ DB_DATABASE обновлен"
fi

if ! grep -q "^DB_USERNAME=$DB_USERNAME" laravel/.env; then
    sed -i '/^DB_USERNAME=/d' laravel/.env
    echo "DB_USERNAME=$DB_USERNAME" >> laravel/.env
    echo "✅ DB_USERNAME обновлен"
fi
echo ""

echo "=== Готово ==="
echo ""
echo "Теперь попробуйте запустить контейнер:"
echo "  docker-compose -f docker-compose.production.yml up -d laravel"
echo ""
echo "Или перезапустите, если он уже запущен:"
echo "  docker-compose -f docker-compose.production.yml restart laravel"
echo ""
echo "Проверьте логи:"
echo "  docker logs hunter-photo-laravel"
echo ""
echo "Если проблема сохраняется, проверьте подключение к PostgreSQL:"
echo "  docker exec hunter-photo-postgres psql -U $DB_USERNAME -d $DB_DATABASE -c 'SELECT 1;'"

