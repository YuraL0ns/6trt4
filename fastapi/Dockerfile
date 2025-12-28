FROM python:3.11-slim

# Установка системных зависимостей
# Для OpenCV, InsightFace, EasyOCR и других ML библиотек
RUN apt-get update && apt-get install -y \
    gcc \
    g++ \
    libpq-dev \
    # Для OpenCV и работы с изображениями
    libgl1 \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender1 \
    libgomp1 \
    # Для работы с изображениями (Pillow, OpenCV)
    libjpeg-dev \
    libpng-dev \
    libtiff-dev \
    libavcodec-dev \
    libavformat-dev \
    libswscale-dev \
    # Для работы с шрифтами (EasyOCR)
    libfontconfig1 \
    libx11-6 \
    && rm -rf /var/lib/apt/lists/*

# Рабочая директория
WORKDIR /app

# Копирование requirements
COPY requirements.txt .

# Установка Python зависимостей
RUN pip install --no-cache-dir -r requirements.txt

# Копирование приложения
COPY . .

# Создание директорий с правильными правами
RUN mkdir -p /app/uploads /shared /app/models /app/logs/tasks /var/www/html/storage/app/public/events && \
    chmod -R 755 /app/uploads /shared /app/models /app/logs && \
    chmod -R 755 /var/www/html/storage 2>/dev/null || true

# Expose порт
EXPOSE 8000

# Создаем entrypoint скрипт для установки прав при запуске
RUN cat > /entrypoint.sh << 'EOF'
#!/bin/bash
set -e

# Увеличиваем лимит открытых файлов
ulimit -n 65536 2>/dev/null || true

# Устанавливаем права на директории при запуске контейнера
if [ -d "/var/www/html/storage" ]; then
    chmod -R 755 /var/www/html/storage || true
    find /var/www/html/storage -type d -exec chmod 755 {} \; 2>/dev/null || true
    find /var/www/html/storage -type f -exec chmod 644 {} \; 2>/dev/null || true
fi

if [ -d "/app/uploads" ]; then
    chmod -R 755 /app/uploads || true
fi

# Запускаем основную команду
exec "$@"
EOF

RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
