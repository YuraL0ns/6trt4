FROM php:8.3-cli

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd opcache intl zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Установка Node.js и npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Рабочая директория
WORKDIR /var/www/html

# Копирование файлов
COPY . .

# Копирование PHP конфигурации
COPY php.ini /usr/local/etc/php/conf.d/uploads.ini

# Права доступа
RUN chown -R www-data:www-data /var/www/html \
    && mkdir -p /var/www/html/storage/framework/{cache,sessions,views,testing} \
    && mkdir -p /var/www/html/storage/app/public/{events,orders} \
    && mkdir -p /var/www/html/storage/logs \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Создаем entrypoint скрипт для установки прав при запуске
RUN cat > /entrypoint.sh << 'EOF'
#!/bin/bash
set -e

# Устанавливаем права на директории при запуске контейнера
if [ -d "/var/www/html/storage" ]; then
    chown -R www-data:www-data /var/www/html/storage
    
    # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Устанавливаем правильные права для разных типов директорий
    # Директории должны быть 775 (rwxrwxr-x) для возможности записи
    find /var/www/html/storage -type d -exec chmod 775 {} \;
    
    # Файлы должны быть 664 (rw-rw-r--) для возможности записи
    find /var/www/html/storage -type f -exec chmod 664 {} \;
    
    # Создаем все необходимые поддиректории Laravel
    mkdir -p /var/www/html/storage/app/public/events
    mkdir -p /var/www/html/storage/app/public/orders
    mkdir -p /var/www/html/storage/framework/cache
    mkdir -p /var/www/html/storage/framework/sessions
    mkdir -p /var/www/html/storage/framework/views
    mkdir -p /var/www/html/storage/framework/testing
    mkdir -p /var/www/html/storage/logs
    
    # Устанавливаем права на созданные директории
    chown -R www-data:www-data /var/www/html/storage
    find /var/www/html/storage -type d -exec chmod 775 {} \;
    find /var/www/html/storage -type f -exec chmod 664 {} \;
fi

if [ -d "/var/www/html/bootstrap/cache" ]; then
    chown -R www-data:www-data /var/www/html/bootstrap/cache
    find /var/www/html/bootstrap/cache -type d -exec chmod 775 {} \;
    find /var/www/html/bootstrap/cache -type f -exec chmod 664 {} \;
fi

# Запускаем основную команду
exec "$@"
EOF

RUN chmod +x /entrypoint.sh

# Expose порт
EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]

# Команда будет переопределена в docker-compose.yml
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

