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
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd intl \
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
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Создаем entrypoint скрипт для установки прав при запуске
RUN echo '#!/bin/bash\n\
# Устанавливаем права на директории при запуске контейнера\n\
if [ -d "/var/www/html/storage" ]; then\n\
    chown -R www-data:www-data /var/www/html/storage\n\
    find /var/www/html/storage -type d -exec chmod 755 {} \\;\n\
    find /var/www/html/storage -type f -exec chmod 644 {} \\;\n\
    # Создаем директории для событий если их нет\n\
    mkdir -p /var/www/html/storage/app/public/events\n\
    chmod -R 755 /var/www/html/storage/app/public/events\n\
fi\n\
if [ -d "/var/www/html/bootstrap/cache" ]; then\n\
    chown -R www-data:www-data /var/www/html/bootstrap/cache\n\
    chmod -R 755 /var/www/html/bootstrap/cache\n\
fi\n\
# Запускаем основную команду\n\
exec "$@"' > /entrypoint.sh && chmod +x /entrypoint.sh

# Expose порт
EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]

# Команда будет переопределена в docker-compose.yml
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

