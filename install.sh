#!/bin/bash

# Скрипт установки Hunter-Photo на VPS сервер
# Автор: Hunter-Photo Team
# Версия: 1.0

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Функция для вывода сообщений
info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Проверка прав root
if [ "$EUID" -ne 0 ]; then 
    error "Пожалуйста, запустите скрипт с правами root (sudo ./install.sh)"
    exit 1
fi

info "Начинаем установку Hunter-Photo на VPS сервер..."

# Обновление системы
info "Обновление системы..."
apt-get update
apt-get upgrade -y

# Установка необходимых пакетов
info "Установка необходимых пакетов..."
apt-get install -y \
    curl \
    wget \
    git \
    unzip \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    lsb-release \
    ufw \
    certbot \
    python3-certbot-nginx

# Установка Docker
info "Проверка установки Docker..."
if ! command -v docker &> /dev/null; then
    info "Установка Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
    systemctl enable docker
    systemctl start docker
    info "Docker установлен"
else
    info "Docker уже установлен"
fi

# Установка Docker Compose
info "Проверка установки Docker Compose..."
if ! command -v docker-compose &> /dev/null; then
    info "Установка Docker Compose..."
    DOCKER_COMPOSE_VERSION=$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep 'tag_name' | cut -d\" -f4)
    curl -L "https://github.com/docker/compose/releases/download/${DOCKER_COMPOSE_VERSION}/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
    info "Docker Compose установлен"
else
    info "Docker Compose уже установлен"
fi

# Настройка firewall
info "Настройка firewall..."
ufw --force enable
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
info "Firewall настроен"

# Создание директорий
info "Создание необходимых директорий..."
mkdir -p /var/www/certbot

# Определяем путь к проекту
PROJECT_DIR=$(pwd)
if [ ! -d "$PROJECT_DIR/nginx" ]; then
    error "Директория nginx не найдена в текущей директории!"
    error "Убедитесь, что вы запускаете скрипт из корня проекта."
    exit 1
fi

# Копирование конфигурации nginx (только если не в /opt/hunter-photo)
if [ "$PROJECT_DIR" != "/opt/hunter-photo" ]; then
    info "Копирование конфигурации nginx..."
    mkdir -p /opt/hunter-photo/nginx/ssl
    mkdir -p /opt/hunter-photo/nginx/conf.d
    mkdir -p /opt/hunter-photo/nginx/logs
    cp -r ./nginx/* /opt/hunter-photo/nginx/ 2>/dev/null || warn "Не удалось скопировать nginx конфигурацию"
    info "Конфигурация nginx скопирована"
else
    info "Проект уже в /opt/hunter-photo, пропускаем копирование nginx конфигурации"
    mkdir -p ./nginx/ssl
    mkdir -p ./nginx/conf.d
    mkdir -p ./nginx/logs
fi

# Создание .env файлов
info "Настройка переменных окружения..."

# Laravel .env
if [ ! -f "./laravel/.env" ]; then
    if [ -f "./laravel/.env.production.example" ]; then
        cp ./laravel/.env.production.example ./laravel/.env
        info "Создан файл laravel/.env из примера"
        
        # Копируем пароль из корневого .env если он существует
        if [ -f "./.env" ]; then
            DB_PASSWORD=$(grep "^DB_PASSWORD=" ./.env | cut -d'=' -f2)
            if [ -n "$DB_PASSWORD" ]; then
                if [[ "$OSTYPE" == "darwin"* ]]; then
                    sed -i '' "s/DB_PASSWORD=CHANGE_ME_SECURE_PASSWORD_HERE/DB_PASSWORD=$DB_PASSWORD/" ./laravel/.env
                else
                    sed -i "s/DB_PASSWORD=CHANGE_ME_SECURE_PASSWORD_HERE/DB_PASSWORD=$DB_PASSWORD/" ./laravel/.env
                fi
                info "Пароль БД скопирован из корневого .env в laravel/.env"
            fi
        fi
        
        warn "ВАЖНО: Отредактируйте laravel/.env и укажите все необходимые параметры!"
    else
        error "Файл laravel/.env.production.example не найден!"
        exit 1
    fi
else
    warn "Файл laravel/.env уже существует, пропускаем..."
    info "Убедитесь, что пароль DB_PASSWORD совпадает с корневым .env!"
fi

# FastAPI .env
if [ ! -f "./fastapi/.env" ]; then
    if [ -f "./fastapi/.env.production.example" ]; then
        cp ./fastapi/.env.production.example ./fastapi/.env
        info "Создан файл fastapi/.env из примера"
        
        # Копируем пароль из корневого .env если он существует
        if [ -f "./.env" ]; then
            DB_PASSWORD=$(grep "^DB_PASSWORD=" ./.env | cut -d'=' -f2)
            if [ -n "$DB_PASSWORD" ]; then
                # Обновляем DATABASE_URL в fastapi/.env
                if [[ "$OSTYPE" == "darwin"* ]]; then
                    sed -i '' "s|postgresql://hunter_photo:CHANGE_ME_SECURE_PASSWORD_HERE@postgres:5432/hunter_photo|postgresql://hunter_photo:$DB_PASSWORD@postgres:5432/hunter_photo|" ./fastapi/.env
                else
                    sed -i "s|postgresql://hunter_photo:CHANGE_ME_SECURE_PASSWORD_HERE@postgres:5432/hunter_photo|postgresql://hunter_photo:$DB_PASSWORD@postgres:5432/hunter_photo|" ./fastapi/.env
                fi
                info "Пароль БД скопирован из корневого .env в fastapi/.env (DATABASE_URL)"
            fi
        fi
        
        warn "ВАЖНО: Отредактируйте fastapi/.env и укажите все необходимые параметры!"
    else
        error "Файл fastapi/.env.production.example не найден!"
        exit 1
    fi
else
    warn "Файл fastapi/.env уже существует, пропускаем..."
    info "Убедитесь, что пароль в DATABASE_URL совпадает с корневым .env!"
fi

# Генерация APP_KEY для Laravel (будет сгенерирован после запуска контейнера)
info "APP_KEY будет сгенерирован автоматически при первом запуске контейнера"

# Создание docker-compose.production.yml если его нет
if [ ! -f "./docker-compose.production.yml" ]; then
    error "Файл docker-compose.production.yml не найден!"
    exit 1
fi

# Копирование docker-compose.production.yml в корень проекта
if [ ! -f "./docker-compose.yml" ] || [ -f "./docker-compose.production.yml" ]; then
    cp ./docker-compose.production.yml ./docker-compose.yml
    info "Используется docker-compose.production.yml"
fi

# Создание .env для docker-compose
if [ ! -f "./.env" ]; then
    info "Создание .env для docker-compose..."
    if [ -f "./.env.production.example" ]; then
        cp ./.env.production.example ./.env
        # Генерируем пароль для базы данных
        DB_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
        # Заменяем пароль в .env файле
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            sed -i '' "s/DB_PASSWORD=CHANGE_ME_SECURE_PASSWORD_HERE/DB_PASSWORD=$DB_PASSWORD/" ./.env
        else
            # Linux
            sed -i "s/DB_PASSWORD=CHANGE_ME_SECURE_PASSWORD_HERE/DB_PASSWORD=$DB_PASSWORD/" ./.env
        fi
        info "Создан файл .env для docker-compose с автоматически сгенерированным паролем"
        info "Сгенерированный пароль БД: $DB_PASSWORD"
        warn "ВАЖНО: Скопируйте этот пароль в laravel/.env и fastapi/.env!"
        warn "ВАЖНО: Отредактируйте .env и укажите остальные необходимые параметры (YooKassa, S3)!"
    else
        error "Файл .env.production.example не найден!"
        error "Создайте файл .env вручную с необходимыми переменными"
        exit 1
    fi
else
    warn "Файл .env уже существует, пропускаем..."
    info "Убедитесь, что пароль DB_PASSWORD совпадает в laravel/.env и fastapi/.env!"
fi

# Установка SSL сертификата (Let's Encrypt)
info "Настройка SSL сертификата..."
read -p "Хотите установить SSL сертификат Let's Encrypt сейчас? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    read -p "Введите доменное имя (например, hunter-photo.ru): " DOMAIN
    if [ -n "$DOMAIN" ]; then
        info "Проверка занятости порта 80..."
        
        # Проверяем, занят ли порт 80
        if lsof -Pi :80 -sTCP:LISTEN -t >/dev/null 2>&1 || netstat -tuln | grep -q ':80 '; then
            warn "Порт 80 занят. Останавливаем возможные веб-серверы..."
            
            # Останавливаем nginx если запущен
            systemctl stop nginx 2>/dev/null || true
            docker-compose stop nginx 2>/dev/null || true
            
            # Останавливаем apache если запущен
            systemctl stop apache2 2>/dev/null || true
            
            # Ждем освобождения порта
            sleep 3
            
            # Проверяем еще раз
            if lsof -Pi :80 -sTCP:LISTEN -t >/dev/null 2>&1; then
                error "Порт 80 все еще занят. Остановите веб-сервер вручную и попробуйте снова."
                error "Используйте команду: lsof -i :80 или netstat -tuln | grep :80"
                warn "Пропускаем установку SSL сертификата. Установите его позже:"
                warn "1. Остановите веб-сервер: systemctl stop nginx"
                warn "2. Получите сертификат: certbot certonly --standalone -d $DOMAIN -d www.$DOMAIN"
                warn "3. Скопируйте сертификаты в nginx/ssl/$DOMAIN/"
            else
                info "Порт 80 освобожден. Получение SSL сертификата для $DOMAIN..."
                certbot certonly --standalone -d $DOMAIN -d www.$DOMAIN --agree-tos --register-unsafely-without-email --non-interactive || warn "Не удалось получить сертификат автоматически"
                
                # Копирование сертификатов
                if [ -d "/etc/letsencrypt/live/$DOMAIN" ]; then
                    SSL_DIR="$PROJECT_DIR/nginx/ssl/$DOMAIN"
                    if [ "$PROJECT_DIR" != "/opt/hunter-photo" ]; then
                        SSL_DIR="/opt/hunter-photo/nginx/ssl/$DOMAIN"
                    fi
                    mkdir -p "$SSL_DIR"
                    cp /etc/letsencrypt/live/$DOMAIN/fullchain.pem "$SSL_DIR/"
                    cp /etc/letsencrypt/live/$DOMAIN/privkey.pem "$SSL_DIR/"
                    info "SSL сертификаты скопированы в $SSL_DIR"
                else
                    warn "Сертификаты не найдены, создайте их вручную или используйте самоподписанные"
                fi
            fi
        else
            info "Порт 80 свободен. Получение SSL сертификата для $DOMAIN..."
            certbot certonly --standalone -d $DOMAIN -d www.$DOMAIN --agree-tos --register-unsafely-without-email --non-interactive || warn "Не удалось получить сертификат автоматически"
            
            # Копирование сертификатов
            if [ -d "/etc/letsencrypt/live/$DOMAIN" ]; then
                SSL_DIR="$PROJECT_DIR/nginx/ssl/$DOMAIN"
                if [ "$PROJECT_DIR" != "/opt/hunter-photo" ]; then
                    SSL_DIR="/opt/hunter-photo/nginx/ssl/$DOMAIN"
                fi
                mkdir -p "$SSL_DIR"
                cp /etc/letsencrypt/live/$DOMAIN/fullchain.pem "$SSL_DIR/"
                cp /etc/letsencrypt/live/$DOMAIN/privkey.pem "$SSL_DIR/"
                info "SSL сертификаты скопированы в $SSL_DIR"
            else
                warn "Сертификаты не найдены, создайте их вручную или используйте самоподписанные"
            fi
        fi
    fi
else
    warn "Пропускаем установку SSL сертификата. Вы можете установить его позже:"
    warn "1. Остановите веб-сервер: systemctl stop nginx (или docker-compose stop nginx)"
    warn "2. Получите сертификат: certbot certonly --standalone -d hunter-photo.ru -d www.hunter-photo.ru"
    warn "3. Скопируйте сертификаты: cp /etc/letsencrypt/live/hunter-photo.ru/*.pem nginx/ssl/hunter-photo.ru/"
fi

# Сборка и запуск контейнеров
info "Сборка Docker образов..."

# Проверка Docker rate limit
info "Проверка доступности Docker Hub..."
if ! curl -s https://registry-1.docker.io/v2/ > /dev/null; then
    warn "Проблемы с доступом к Docker Hub. Возможен rate limit."
    warn "Рекомендуется:"
    warn "1. Войти в Docker Hub: docker login"
    warn "2. Или подождать несколько минут и повторить попытку"
    warn "3. Или использовать альтернативный registry"
    read -p "Продолжить сборку? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        error "Сборка отменена"
        exit 1
    fi
fi

# Попытка сборки с обработкой ошибок rate limit
info "Начинаем сборку образов..."
if ! docker-compose build --no-cache 2>&1 | tee /tmp/docker-build.log; then
    if grep -q "429 Too Many Requests\|toomanyrequests" /tmp/docker-build.log; then
        error "Достигнут лимит запросов к Docker Hub (rate limit)"
        error "Решения:"
        error "1. Войдите в Docker Hub: docker login"
        error "2. Подождите 5-10 минут и повторите: docker-compose build"
        error "3. Используйте Docker Hub Pro для увеличения лимита"
        error ""
        warn "Попробуем собрать без --no-cache (может использовать кэш)..."
        docker-compose build || {
            error "Сборка не удалась. Попробуйте позже или войдите в Docker Hub."
            exit 1
        }
    else
        error "Ошибка при сборке Docker образов. Проверьте логи выше."
        exit 1
    fi
fi
rm -f /tmp/docker-build.log

info "Запуск контейнеров..."
docker-compose up -d

# Ожидание запуска контейнеров
info "Ожидание запуска контейнеров..."
sleep 10

# Ожидание полного запуска контейнеров
info "Ожидание полного запуска контейнеров..."
sleep 15

# Генерация APP_KEY для Laravel
info "Генерация APP_KEY для Laravel..."
docker exec hunter-photo-laravel php artisan key:generate --force || warn "Не удалось сгенерировать APP_KEY"

# Выполнение миграций
info "Выполнение миграций базы данных..."
docker exec hunter-photo-laravel php artisan migrate --force || warn "Не удалось выполнить миграции"

# Создание символической ссылки storage
info "Создание символической ссылки storage..."
docker exec hunter-photo-laravel php artisan storage:link || warn "Не удалось создать символическую ссылку"

# Очистка кэша
info "Очистка кэша..."
docker exec hunter-photo-laravel php artisan config:cache || warn "Не удалось очистить кэш конфигурации"
docker exec hunter-photo-laravel php artisan route:cache || warn "Не удалось очистить кэш маршрутов"
docker exec hunter-photo-laravel php artisan view:cache || warn "Не удалось очистить кэш представлений"

# Настройка прав доступа
info "Настройка прав доступа..."
chown -R www-data:www-data ./laravel/storage
chown -R www-data:www-data ./laravel/bootstrap/cache
chmod -R 775 ./laravel/storage
chmod -R 775 ./laravel/bootstrap/cache

info "Установка завершена!"
echo ""
info "Следующие шаги:"
warn "1. Отредактируйте файлы .env и укажите все необходимые параметры:"
warn "   - ./laravel/.env"
warn "   - ./fastapi/.env"
warn "   - ./.env"
warn ""
warn "2. Если SSL сертификат не был установлен, установите его вручную:"
warn "   certbot certonly --standalone -d hunter-photo.ru -d www.hunter-photo.ru"
warn ""
warn "3. Перезапустите контейнеры после изменения .env файлов:"
warn "   docker-compose down && docker-compose up -d"
warn ""
warn "4. Проверьте логи контейнеров:"
warn "   docker-compose logs -f"
warn ""
info "Проект доступен по адресу: https://hunter-photo.ru"

