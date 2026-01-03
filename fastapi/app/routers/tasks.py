from fastapi import APIRouter, HTTPException
from celery.result import AsyncResult
from tasks.celery_app import celery_app

router = APIRouter()


@router.get("/tasks/{task_id}")
async def get_task_status(task_id: str):
    """Получить статус задачи Celery"""
    try:
        task_result = AsyncResult(task_id, app=celery_app)
    except Exception as e:
        # Если не удалось создать AsyncResult, возможно проблема с сериализацией
        # Пытаемся удалить поврежденный результат из Redis
        try:
            from celery.backends.redis import RedisBackend
            backend = RedisBackend(app=celery_app)
            backend.delete(task_id)
        except:
            pass
        raise HTTPException(
            status_code=500,
            detail=f"Не удалось получить информацию о задаче. Возможно, результат поврежден. Ошибка: {str(e)}"
        )
    
    try:
        # Пытаемся получить состояние задачи
        state = task_result.state
    except Exception as e:
        # Если не удалось прочитать состояние, возможно результат поврежден
        # Пытаемся удалить поврежденный результат
        try:
            from celery.backends.redis import RedisBackend
            backend = RedisBackend(app=celery_app)
            backend.delete(task_id)
        except:
            pass
        raise HTTPException(
            status_code=500,
            detail=f"Не удалось прочитать состояние задачи. Результат может быть поврежден. Ошибка: {str(e)}"
        )
    
    if state == 'PENDING':
        response = {
            'task_id': task_id,
            'state': state,
            'status': 'Задача ожидает выполнения'
        }
    elif state == 'PROGRESS':
        try:
            info = task_result.info or {}
            response = {
                'task_id': task_id,
                'state': state,
                'status': 'Задача выполняется',
                'progress': info.get('progress', 0) if isinstance(info, dict) else 0,
                'current': info.get('current', 0) if isinstance(info, dict) else 0,
                'total': info.get('total', 0) if isinstance(info, dict) else 0
            }
        except Exception as e:
            # Если не удалось прочитать info, возвращаем базовую информацию
            response = {
                'task_id': task_id,
                'state': state,
                'status': 'Задача выполняется',
                'error': f'Не удалось прочитать детали: {str(e)}'
            }
    elif state == 'SUCCESS':
        try:
            result = task_result.result
            response = {
                'task_id': task_id,
                'state': state,
                'status': 'Задача выполнена успешно',
                'result': result
            }
        except Exception as e:
            # Если не удалось прочитать результат, возвращаем базовую информацию
            response = {
                'task_id': task_id,
                'state': state,
                'status': 'Задача выполнена успешно',
                'error': f'Не удалось прочитать результат: {str(e)}'
            }
    elif state == 'FAILURE':
        # Для FAILURE состояния получаем полную информацию об ошибке
        try:
            error_info = task_result.info
            if isinstance(error_info, Exception):
                error_message = str(error_info)
                error_type = type(error_info).__name__
                traceback_info = None
            elif isinstance(error_info, dict):
                error_message = error_info.get('error', error_info.get('error_message', str(error_info)))
                error_type = error_info.get('error_type', error_info.get('type', 'UnknownError'))
                traceback_info = error_info.get('traceback')
            else:
                error_message = str(error_info) if error_info else 'Неизвестная ошибка'
                error_type = 'UnknownError'
                traceback_info = None
            
            # Получаем traceback если доступен
            if not traceback_info:
                try:
                    if hasattr(task_result, 'traceback') and task_result.traceback:
                        traceback_info = task_result.traceback
                except:
                    pass
            
            response = {
                'task_id': task_id,
                'state': state,
                'status': 'Ошибка выполнения задачи',
                'error': error_message,
                'error_type': error_type,
                'traceback': traceback_info
            }
        except Exception as e:
            # Если не удалось прочитать информацию об ошибке, возможно результат поврежден
            # Пытаемся удалить поврежденный результат
            try:
                from celery.backends.redis import RedisBackend
                backend = RedisBackend(app=celery_app)
                backend.delete(task_id)
            except:
                pass
            response = {
                'task_id': task_id,
                'state': state,
                'status': 'Ошибка выполнения задачи',
                'error': f'Не удалось прочитать информацию об ошибке. Результат может быть поврежден. Ошибка: {str(e)}',
                'error_type': 'DeserializationError'
            }
    elif state == 'STARTED':
        # Для STARTED состояния возвращаем информацию о процессе
        try:
            info = task_result.info if task_result.info else {}
            response = {
                'task_id': task_id,
                'state': state,
                'status': 'Задача выполняется',
                'info': info
            }
        except Exception as e:
            response = {
                'task_id': task_id,
                'state': state,
                'status': 'Задача выполняется',
                'error': f'Не удалось прочитать детали: {str(e)}'
            }
    else:
        # Для других состояний (RETRY, REVOKED и т.д.)
        try:
            info = str(task_result.info) if task_result.info else None
            response = {
                'task_id': task_id,
                'state': state,
                'status': f'Состояние задачи: {state}',
                'info': info
            }
        except Exception as e:
            response = {
                'task_id': task_id,
                'state': state,
                'status': f'Состояние задачи: {state}',
                'error': f'Не удалось прочитать детали: {str(e)}'
            }
    
    return response


