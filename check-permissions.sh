#!/bin/bash

# Скрипт для проверки прав доступа к папкам проекта

echo "Проверка прав доступа к папкам проекта Hunter-Photo"
echo "=================================================="
echo ""

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Функция проверки прав
check_permissions() {
    local path=$1
    local required_perms=$2
    local description=$3
    
    if [ ! -d "$path" ]; then
        echo -e "${RED}✗${NC} $description: Папка не существует: $path"
        return 1
    fi
    
    local current_perms=$(stat -c "%a" "$path" 2>/dev/null || stat -f "%OLp" "$path" 2>/dev/null)
    
    if [ "$current_perms" -ge "$required_perms" ]; then
        echo -e "${GREEN}✓${NC} $description: $path (права: $current_perms)"
        return 0
    else
        echo -e "${YELLOW}⚠${NC} $description: $path (права: $current_perms, требуется: $required_perms)"
        return 1
    fi
}

# Проверка основных папок Laravel
echo "Проверка папок Laravel:"
echo "----------------------"
check_permissions "laravel/storage" "755" "Storage"
check_permissions "laravel/storage/app" "755" "Storage/app"
check_permissions "laravel/storage/app/public" "755" "Storage/app/public"
check_permissions "laravel/storage/logs" "755" "Storage/logs"
check_permissions "laravel/storage/framework" "755" "Storage/framework"
check_permissions "laravel/storage/framework/cache" "755" "Storage/framework/cache"
check_permissions "laravel/storage/framework/sessions" "755" "Storage/framework/sessions"
check_permissions "laravel/storage/framework/views" "755" "Storage/framework/views"
check_permissions "laravel/bootstrap/cache" "755" "Bootstrap/cache"

echo ""
echo "Проверка папок для событий:"
echo "--------------------------"
if [ -d "laravel/storage/app/public/events" ]; then
    check_permissions "laravel/storage/app/public/events" "755" "Events directory"
    
    # Проверка подпапок событий
    for event_dir in laravel/storage/app/public/events/*/; do
        if [ -d "$event_dir" ]; then
            event_name=$(basename "$event_dir")
            check_permissions "$event_dir" "755" "Event: $event_name"
            
            # Проверка подпапок
            if [ -d "$event_dir/covers" ]; then
                check_permissions "$event_dir/covers" "755" "  └─ Covers"
            fi
            if [ -d "$event_dir/upload" ]; then
                check_permissions "$event_dir/upload" "755" "  └─ Upload"
            fi
            if [ -d "$event_dir/original_photo" ]; then
                check_permissions "$event_dir/original_photo" "755" "  └─ Original"
            fi
            if [ -d "$event_dir/custom_photo" ]; then
                check_permissions "$event_dir/custom_photo" "755" "  └─ Custom"
            fi
        fi
    done
else
    echo -e "${YELLOW}⚠${NC} Папка events не существует (будет создана автоматически)"
fi

echo ""
echo "Проверка папок FastAPI:"
echo "---------------------"
check_permissions "fastapi/uploads" "755" "FastAPI uploads"

echo ""
echo "Рекомендации:"
echo "------------"
echo "Если права доступа неверны, выполните:"
echo "  chmod -R 755 laravel/storage"
echo "  chmod -R 755 laravel/bootstrap/cache"
echo "  chmod -R 755 fastapi/uploads"
echo ""
echo "Для Docker контейнеров убедитесь, что volume правильно настроен в docker-compose.yml"

