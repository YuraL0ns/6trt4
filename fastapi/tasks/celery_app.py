from celery import Celery
from celery.signals import task_failure, task_prerun, task_postrun
from celery.backends.redis import RedisBackend
from app.config import settings
import logging

logger = logging.getLogger(__name__)

# Кастомный Redis backend с обработкой ошибок десериализации
class SafeRedisBackend(RedisBackend):
    """Redis backend с безопасной обработкой ошибок десериализации"""
    
    def exception_to_python(self, exc):
        """Переопределяем метод для безопасной обработки исключений"""
        try:
            return super().exception_to_python(exc)
        except ValueError as e:
            if "Exception information must include" in str(e):
                # Поврежденный результат - удаляем его и возвращаем безопасное исключение
                logger.warning(f"Обнаружен поврежденный результат при десериализации, удаляем")
                try:
                    # Пытаемся получить task_id из контекста
                    if hasattr(self, '_task_id'):
                        self.delete(self._task_id)
                        logger.info(f"Поврежденный результат задачи {self._task_id} удален")
                except:
                    pass
                # Возвращаем безопасное исключение вместо ошибки десериализации
                from celery.exceptions import Retry
                return Retry("Поврежденный результат задачи был удален")
            raise
    
    def _get_task_meta_for(self, task_id):
        """Переопределяем метод для безопасного чтения метаданных"""
        try:
            # Сохраняем task_id для возможного удаления
            self._task_id = task_id
            return super()._get_task_meta_for(task_id)
        except ValueError as e:
            if "Exception information must include" in str(e):
                # Поврежденный результат - удаляем его
                logger.warning(f"Обнаружен поврежденный результат для задачи {task_id}, удаляем")
                try:
                    self.delete(task_id)
                    logger.info(f"Поврежденный результат задачи {task_id} удален")
                except:
                    pass
                # Возвращаем пустые метаданные вместо ошибки
                return {'status': 'PENDING', 'result': None, 'traceback': None, 'children': None}
            raise

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

# Применяем monkey patching к существующему backend для безопасной обработки ошибок
def patch_backend_for_safety():
    """Применяет патчи к backend для безопасной обработки ошибок десериализации"""
    if isinstance(celery_app.backend, RedisBackend):
        original_exception_to_python = celery_app.backend.exception_to_python
        original_get_task_meta_for = celery_app.backend._get_task_meta_for
        
        def safe_exception_to_python(self, exc):
            """Безопасная обработка исключений"""
            try:
                return original_exception_to_python(exc)
            except ValueError as e:
                if "Exception information must include" in str(e):
                    logger.warning(f"Обнаружен поврежденный результат при десериализации, удаляем")
                    try:
                        if hasattr(self, '_task_id'):
                            self.delete(self._task_id)
                            logger.info(f"Поврежденный результат задачи {self._task_id} удален")
                    except:
                        pass
                    from celery.exceptions import Retry
                    return Retry("Поврежденный результат задачи был удален")
                raise
        
        def safe_get_task_meta_for(self, task_id):
            """Безопасное чтение метаданных"""
            try:
                self._task_id = task_id
                return original_get_task_meta_for(task_id)
            except ValueError as e:
                if "Exception information must include" in str(e):
                    logger.warning(f"Обнаружен поврежденный результат для задачи {task_id}, удаляем")
                    try:
                        self.delete(task_id)
                        logger.info(f"Поврежденный результат задачи {task_id} удален")
                    except:
                        pass
                    return {'status': 'PENDING', 'result': None, 'traceback': None, 'children': None}
                raise
        
        # Применяем патчи
        import types
        celery_app.backend.exception_to_python = types.MethodType(safe_exception_to_python, celery_app.backend)
        celery_app.backend._get_task_meta_for = types.MethodType(safe_get_task_meta_for, celery_app.backend)

# Применяем патчи после создания app
patch_backend_for_safety()

def cleanup_corrupted_result(task_id):
    """Удаляет поврежденный результат задачи из Redis"""
    try:
        from celery.backends.redis import RedisBackend
        backend = RedisBackend(app=celery_app)
        backend.delete(task_id)
        logger.info(f"Поврежденный результат задачи {task_id} удален из Redis")
        return True
    except Exception as e:
        logger.error(f"Не удалось удалить поврежденный результат задачи {task_id}: {str(e)}")
        return False

# Обработчик перед выполнением задачи - проверяем и очищаем поврежденные результаты
@task_prerun.connect
def handle_task_prerun(sender=None, task_id=None, task=None, **kwargs):
    """Обработчик перед выполнением задачи - проверяем наличие поврежденных результатов"""
    if not task_id:
        return
    
    try:
        from celery.result import AsyncResult
        task_result = AsyncResult(task_id, app=celery_app)
        
        # Пытаемся прочитать состояние задачи
        # Если результат поврежден, это вызовет ошибку десериализации
        try:
            state = task_result.state
            # Если состояние FAILURE, проверяем, можно ли прочитать информацию об ошибке
            if state == 'FAILURE':
                try:
                    info = task_result.info
                    # Проверяем, что info содержит error_type (новый формат)
                    if isinstance(info, dict) and 'error_type' not in info:
                        # Старый формат без error_type - удаляем поврежденный результат
                        logger.warning(f"Обнаружен старый формат результата для задачи {task_id}, удаляем")
                        cleanup_corrupted_result(task_id)
                except (ValueError, TypeError) as e:
                    if "Exception information must include" in str(e) or "not JSON serializable" in str(e):
                        logger.warning(f"Обнаружена ошибка десериализации для задачи {task_id} при проверке, удаляем")
                        cleanup_corrupted_result(task_id)
        except (ValueError, TypeError) as e:
            if "Exception information must include" in str(e) or "not JSON serializable" in str(e):
                logger.warning(f"Обнаружена ошибка десериализации для задачи {task_id} при чтении состояния, удаляем")
                cleanup_corrupted_result(task_id)
    except Exception as e:
        # Игнорируем ошибки при проверке - это не критично
        logger.debug(f"Ошибка при проверке результата задачи {task_id}: {str(e)}")

# Обработчик ошибок выполнения задач
@task_failure.connect
def handle_task_failure(sender=None, task_id=None, exception=None, traceback=None, einfo=None, **kwargs):
    """Обработчик ошибок выполнения задач"""
    if isinstance(exception, ValueError) and "Exception information must include" in str(exception):
        # Это ошибка десериализации - пытаемся удалить поврежденный результат
        logger.warning(f"Обнаружена ошибка десериализации для задачи {task_id}, удаляем поврежденный результат")
        cleanup_corrupted_result(task_id)

celery_app.conf.update(
    task_serializer="json",
    accept_content=["json"],
    result_serializer="json",
    timezone="UTC",
    enable_utc=True,
    task_track_started=True,
    task_time_limit=7200,  # 2 часа (увеличено для больших событий)
    task_soft_time_limit=6900,  # 1 час 55 минут (увеличено)
    
    # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Используем solo pool вместо prefork
    # InsightFace, OpenCV и ONNX Runtime не работают с prefork
    # solo = один процесс, один поток (безопасно для ML библиотек)
    worker_pool='solo',  # ВАЖНО: solo вместо prefork для ML библиотек
    
    worker_prefetch_multiplier=1,  # Обрабатываем по одной задаче за раз для балансировки
    # worker_max_tasks_per_child не используется с solo pool
    # worker_max_memory_per_child не используется с solo pool
    
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
    result_expires=1800,  # Результаты хранятся 30 минут (уменьшено для быстрой очистки поврежденных результатов)
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


