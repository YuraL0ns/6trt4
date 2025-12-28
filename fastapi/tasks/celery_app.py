from celery import Celery
from app.config import settings

celery_app = Celery(
    "hunter_photo",
    broker=settings.CELERY_BROKER_URL,
    backend=settings.CELERY_RESULT_BACKEND,
    include=[
        "tasks.photo_processing",
        "tasks.face_search",
        "tasks.number_search",
        "tasks.event_archive",
    ]
)

celery_app.conf.update(
    task_serializer="json",
    accept_content=["json"],
    result_serializer="json",
    timezone="UTC",
    enable_utc=True,
    task_track_started=True,
    task_time_limit=7200,  # 2 часа (увеличено для больших событий)
    task_soft_time_limit=6900,  # 1 час 55 минут (увеличено)
    worker_prefetch_multiplier=1,  # Обрабатываем по одной задаче за раз для балансировки
    worker_max_tasks_per_child=200,  # Увеличено до 200 задач (было 50) - для длительных задач обработки событий
    worker_max_memory_per_child=2048000,  # 2GB лимит памяти на worker (в килобайтах)
    
    # Оптимизация для балансировки нагрузки
    task_acks_late=True,  # Подтверждаем задачу только после выполнения (предотвращает потерю задач)
    task_reject_on_worker_lost=True,  # Отклоняем задачу если worker потерян
    
    # Приоритеты задач (чем выше число, тем выше приоритет)
    # ВАЖНО: process_event_photos идет в дефолтную очередь (без указания queue),
    # чтобы worker мог обработать её без дополнительной настройки
    task_routes={
        'tasks.face_search.search_similar_faces': {'queue': 'high_priority', 'priority': 5},
        'tasks.number_search.search_by_numbers': {'queue': 'high_priority', 'priority': 5},
        # process_event_photos идет в дефолтную очередь - не указываем queue
        'tasks.event_archive.archive_event_photos': {'queue': 'low_priority', 'priority': 1},
        'tasks.event_archive.check_events_for_archiving': {'queue': 'low_priority', 'priority': 1},
    },
    
    # Дефолтная очередь для задач без явного указания
    task_default_queue='celery',
    
    # Лимиты для предотвращения перегрузки
    task_default_rate_limit='100/m',  # Максимум 100 задач в минуту по умолчанию
    worker_disable_rate_limits=False,  # Включаем rate limiting
    
    # Оптимизация памяти
    result_expires=3600,  # Результаты хранятся 1 час
    result_backend_transport_options={
        'master_name': 'mymaster',
        'visibility_timeout': 3600,
    },
    
    # Периодические задачи (beat schedule)
    beat_schedule={
        'check-events-for-archiving': {
            'task': 'tasks.event_archive.check_events_for_archiving',
            'schedule': 86400.0,  # Каждые 24 часа
            'options': {'queue': 'low_priority'},  # Низкий приоритет для периодических задач
        },
    },
)


