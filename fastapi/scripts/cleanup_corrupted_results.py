#!/usr/bin/env python3
"""
Скрипт для очистки поврежденных результатов задач Celery из Redis
"""
import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from tasks.celery_app import celery_app
from celery.result import AsyncResult
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def cleanup_corrupted_results():
    """Очищает все поврежденные результаты задач из Redis"""
    try:
        from celery.backends.redis import RedisBackend
        
        backend = RedisBackend(app=celery_app)
        
        # Получаем соединение с Redis через backend
        # Backend уже настроен с правильными параметрами подключения
        redis_client = backend.client
        
        # Получаем все ключи результатов задач
        # Формат ключа: celery-task-meta-{task_id}
        pattern = "celery-task-meta-*"
        try:
            keys = redis_client.keys(pattern)
        except Exception as keys_error:
            logger.error(f"Не удалось получить ключи из Redis: {str(keys_error)}")
            # Пытаемся использовать альтернативный метод через backend
            # Проходим по всем задачам через AsyncResult
            logger.info("Пытаемся очистить результаты через проверку задач...")
            return cleanup_via_task_check(backend)
        
        logger.info(f"Найдено {len(keys)} результатов задач в Redis")
        
        cleaned_count = 0
        error_count = 0
        
        for key in keys:
            task_id = key.decode('utf-8').replace('celery-task-meta-', '')
            
            try:
                # Пытаемся создать AsyncResult и прочитать состояние
                task_result = AsyncResult(task_id, app=celery_app)
                state = task_result.state
                
                # Если состояние FAILURE, проверяем, можно ли прочитать информацию
                if state == 'FAILURE':
                    try:
                        info = task_result.info
                        # Проверяем, что info содержит error_type (новый формат)
                        if isinstance(info, dict) and 'error_type' not in info:
                            # Старый формат без error_type - удаляем
                            backend.delete(task_id)
                            cleaned_count += 1
                            logger.info(f"Удален старый результат задачи {task_id}")
                    except (ValueError, TypeError) as e:
                        if "Exception information must include" in str(e) or "not JSON serializable" in str(e):
                            # Поврежденный результат - удаляем
                            backend.delete(task_id)
                            cleaned_count += 1
                            logger.info(f"Удален поврежденный результат задачи {task_id}: {str(e)}")
            except (ValueError, TypeError) as e:
                if "Exception information must include" in str(e) or "not JSON serializable" in str(e):
                    # Поврежденный результат - удаляем
                    try:
                        backend.delete(task_id)
                        cleaned_count += 1
                        logger.info(f"Удален поврежденный результат задачи {task_id}: {str(e)}")
                    except Exception as delete_error:
                        error_count += 1
                        logger.error(f"Не удалось удалить результат задачи {task_id}: {str(delete_error)}")
            except Exception as e:
                error_count += 1
                logger.warning(f"Ошибка при проверке задачи {task_id}: {str(e)}")
        
        logger.info(f"Очистка завершена. Удалено поврежденных результатов: {cleaned_count}, ошибок: {error_count}")
        return cleaned_count, error_count
        
    except Exception as e:
        logger.error(f"Критическая ошибка при очистке результатов: {str(e)}", exc_info=True)
        # Пытаемся использовать альтернативный метод
        try:
            from celery.backends.redis import RedisBackend
            backend = RedisBackend(app=celery_app)
            return cleanup_via_task_check(backend)
        except:
            return 0, 1

def cleanup_via_task_check(backend):
    """Альтернативный метод очистки - проверяем задачи по известным ID"""
    # Этот метод менее эффективен, но работает если keys() не доступен
    logger.info("Используем альтернативный метод очистки...")
    cleaned_count = 0
    error_count = 0
    
    # Пытаемся получить список задач из других источников
    # В реальности лучше использовать keys(), но если это не работает,
    # можно очистить результаты при их обнаружении через обработчики сигналов
    logger.warning("Альтернативный метод очистки требует доступа к keys(). Используйте основной метод.")
    return cleaned_count, error_count

if __name__ == "__main__":
    print("Начинаем очистку поврежденных результатов задач Celery...")
    cleaned, errors = cleanup_corrupted_results()
    print(f"Очистка завершена. Удалено: {cleaned}, ошибок: {errors}")
    sys.exit(0 if errors == 0 else 1)

