# Hunter-Photo FastAPI Backend

FastAPI приложение для обработки фотографий и ML задач.

## Установка

1. Создайте виртуальное окружение:
```bash
python3.11 -m venv venv
source venv/bin/activate  # Linux/Mac
# или
venv\Scripts\activate  # Windows
```

2. Установите зависимости:
```bash
pip install -r requirements.txt
```

3. Создайте файл `.env` на основе `.env.example`:
```bash
cp .env.example .env
```

4. Настройте переменные окружения в `.env`

## Запуск

### API сервер
```bash
uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload
```

### Celery Worker
```bash
celery -A tasks.celery_app worker --loglevel=info --concurrency=4
```

### Celery Beat (для периодических задач)
```bash
celery -A tasks.celery_app beat --loglevel=info
```

## API Документация

После запуска API доступна по адресу:
- Swagger UI: http://localhost:8000/docs
- ReDoc: http://localhost:8000/redoc

## Структура проекта

```
fastapi/
├── app/
│   ├── __init__.py
│   ├── main.py          # Точка входа FastAPI
│   ├── config.py        # Конфигурация
│   ├── database.py      # Подключение к БД
│   ├── models/          # SQLAlchemy модели
│   ├── schemas/         # Pydantic схемы
│   └── routers/         # API роутеры
├── tasks/
│   ├── __init__.py
│   ├── celery_app.py    # Конфигурация Celery
│   ├── photo_processing.py  # Обработка фотографий
│   ├── face_search.py    # Поиск по лицам
│   └── number_search.py  # Поиск по номерам
├── utils/
│   ├── __init__.py
│   ├── image_processor.py    # Обработка изображений
│   ├── exif_processor.py     # EXIF данные
│   ├── watermark.py          # Водяные знаки
│   ├── face_recognition.py   # InsightFace
│   └── number_recognition.py # EasyOCR
├── requirements.txt
└── README.md
```

## ML Модели

### InsightFace
Модель автоматически загружается при первом использовании. Модель будет сохранена в `./models` (или путь из `INSIGHTFACE_MODEL_PATH`).

### EasyOCR
Модели загружаются автоматически при первом использовании.

## Задачи Celery

- `process_event_photos` - Обработка всех фотографий события
- `search_similar_faces` - Поиск похожих фотографий по лицу
- `search_by_numbers` - Поиск фотографий по номеру


