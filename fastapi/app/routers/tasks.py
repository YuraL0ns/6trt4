from fastapi import APIRouter, HTTPException
from celery.result import AsyncResult
from tasks.celery_app import celery_app

router = APIRouter()


@router.get("/tasks/{task_id}")
async def get_task_status(task_id: str):
    """Получить статус задачи Celery"""
    task_result = AsyncResult(task_id, app=celery_app)
    
    if task_result.state == 'PENDING':
        response = {
            'task_id': task_id,
            'state': task_result.state,
            'status': 'Задача ожидает выполнения'
        }
    elif task_result.state == 'PROGRESS':
        response = {
            'task_id': task_id,
            'state': task_result.state,
            'status': 'Задача выполняется',
            'progress': task_result.info.get('progress', 0),
            'current': task_result.info.get('current', 0),
            'total': task_result.info.get('total', 0)
        }
    elif task_result.state == 'SUCCESS':
        response = {
            'task_id': task_id,
            'state': task_result.state,
            'status': 'Задача выполнена успешно',
            'result': task_result.result
        }
    elif task_result.state == 'FAILURE':
        # Для FAILURE состояния получаем полную информацию об ошибке
        error_info = task_result.info
        if isinstance(error_info, Exception):
            error_message = str(error_info)
            error_type = type(error_info).__name__
        elif isinstance(error_info, dict):
            error_message = error_info.get('error', str(error_info))
            error_type = error_info.get('type', 'UnknownError')
        else:
            error_message = str(error_info)
            error_type = 'UnknownError'
        
        # Получаем traceback если доступен
        traceback_info = None
        if hasattr(task_result, 'traceback') and task_result.traceback:
            traceback_info = task_result.traceback
        elif isinstance(error_info, dict) and 'traceback' in error_info:
            traceback_info = error_info['traceback']
        
        response = {
            'task_id': task_id,
            'state': task_result.state,
            'status': 'Ошибка выполнения задачи',
            'error': error_message,
            'error_type': error_type,
            'traceback': traceback_info
        }
    elif task_result.state == 'STARTED':
        # Для STARTED состояния возвращаем информацию о процессе
        response = {
            'task_id': task_id,
            'state': task_result.state,
            'status': 'Задача выполняется',
            'info': task_result.info if task_result.info else {}
        }
    else:
        # Для других состояний (RETRY, REVOKED и т.д.)
        response = {
            'task_id': task_id,
            'state': task_result.state,
            'status': f'Состояние задачи: {task_result.state}',
            'info': str(task_result.info) if task_result.info else None
        }
    
    return response


