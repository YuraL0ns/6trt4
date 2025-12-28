#!/usr/bin/env python3
"""
Скрипт для перезапуска Celery задач через терминал/SSH

Использование:
    python restart_task.py <task_id> [--force]
    
Примеры:
    python restart_task.py abc123-def456-ghi789
    python restart_task.py abc123-def456-ghi789 --force
"""
import sys
import os
import argparse
from celery import Celery
from celery.result import AsyncResult

# Добавляем путь к приложению
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from tasks.celery_app import celery_app


def restart_task(task_id: str, force: bool = False):
    """Перезапустить задачу Celery"""
    print(f"Получение информации о задаче {task_id}...")
    
    result = AsyncResult(task_id, app=celery_app)
    
    # Получаем информацию о задаче
    try:
        task_info = result.info if hasattr(result, 'info') and result.info is not None else {}
    except Exception as e:
        print(f"Не удалось получить info из результата: {e}")
        task_info = {}
    
    # Если task_info все еще None, создаем пустой словарь
    if task_info is None:
        task_info = {}
    
    task_state = result.state
    
    print(f"Текущий статус задачи: {task_state}")
    
    # Обрабатываем разные статусы задачи
    if task_state == 'PENDING':
        if force:
            print("Задача в статусе PENDING. Попытка перезапуска с --force...")
        else:
            print("Задача еще не запущена или была отменена. Используйте --force для принудительного перезапуска.")
            return False
    
    # Обрабатываем отмененные задачи (REVOKED, REJECTED)
    if task_state in ['REVOKED', 'REJECTED']:
        print(f"Задача была отменена (статус: {task_state}). Перезапуск...")
        # Продолжаем к перезапуску
    
    if task_state == 'SUCCESS':
        print("Задача уже успешно завершена. Используйте --force для принудительного перезапуска.")
        if not force:
            return False
    
    if task_state == 'FAILURE':
        print("Задача завершилась с ошибкой. Перезапуск...")
    elif task_state in ['STARTED', 'PROGRESS', 'RETRY']:
        print(f"Задача выполняется (статус: {task_state}). Отмена текущей задачи...")
        try:
            celery_app.control.revoke(task_id, terminate=True, signal='SIGKILL')
            print("Задача отменена.")
            # Даем время на завершение
            import time
            time.sleep(1)
        except Exception as e:
            print(f"Ошибка при отмене задачи: {e}")
            if not force:
                return False
    
    # Получаем аргументы задачи из метаданных
    # ВАЖНО: task_info может быть None или не быть словарем
    if not isinstance(task_info, dict):
        task_info = {}
    
    task_args = task_info.get('args', []) if isinstance(task_info, dict) else []
    task_kwargs = task_info.get('kwargs', {}) if isinstance(task_info, dict) else {}
    
    # Если аргументы не найдены, пытаемся получить из разных источников
    if not task_args and not task_kwargs:
        print("Не удалось получить аргументы задачи из метаданных. Поиск в других источниках...")
        
        # 1. Пытаемся получить из результата
        try:
            if hasattr(result, 'result') and isinstance(result.result, dict):
                if 'event_id' in result.result:
                    task_kwargs['event_id'] = result.result['event_id']
                if 'analyses' in result.result:
                    task_kwargs['analyses'] = result.result['analyses']
        except Exception as e:
            print(f"Не удалось получить из result: {e}")
        
        # 2. Пытаемся получить из task_id (если это event_id)
        # Иногда task_id может быть event_id
        if not task_kwargs.get('event_id'):
            # Проверяем, является ли task_id UUID события
            import re
            uuid_pattern = r'^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'
            if re.match(uuid_pattern, task_id, re.IGNORECASE):
                print(f"Используем task_id как event_id: {task_id}")
                task_kwargs['event_id'] = task_id
                # Устанавливаем дефолтные анализы
                task_kwargs['analyses'] = {
                    'timeline': False,
                    'remove_exif': True,
                    'watermark': True,
                    'face_search': False,
                    'number_search': False
                }
        
        # 3. Пытаемся получить из БД Laravel (если доступна)
        if not task_kwargs.get('event_id'):
            print("Попытка получить event_id из БД...")
            try:
                from app.database import SessionLocal
                from app.models import Event
                db = SessionLocal()
                # Ищем событие по task_id (может быть совпадение)
                event = db.query(Event).filter(Event.id == task_id).first()
                if event:
                    print(f"Найдено событие в БД: {event.id}")
                    task_kwargs['event_id'] = str(event.id)
                    task_kwargs['analyses'] = {
                        'timeline': False,
                        'remove_exif': True,
                        'watermark': True,
                        'face_search': False,
                        'number_search': False
                    }
                db.close()
            except Exception as e:
                print(f"Не удалось получить из БД: {e}")
    
    if not task_kwargs.get('event_id'):
        print("ОШИБКА: Не удалось получить event_id для перезапуска.")
        print("Попробуйте запустить задачу вручную с нужными параметрами.")
        print("Или используйте task_id, который является UUID события.")
        return False
    
    print(f"Аргументы задачи: args={task_args}, kwargs={task_kwargs}")
    
    # Определяем имя задачи
    task_name = task_info.get('task', None)
    if not task_name:
        # Пытаемся определить по task_id или другим признакам
        if 'event_id' in task_kwargs:
            task_name = 'tasks.photo_processing.process_event_photos'
        else:
            print("ОШИБКА: Не удалось определить имя задачи.")
            return False
    
    print(f"Имя задачи: {task_name}")
    
    # Запускаем задачу заново
    try:
        print("Запуск новой задачи...")
        if task_args:
            new_task = celery_app.send_task(task_name, args=task_args, kwargs=task_kwargs)
        else:
            new_task = celery_app.send_task(task_name, kwargs=task_kwargs)
        
        print(f"Задача перезапущена. Новый task_id: {new_task.id}")
        print(f"Проверить статус: celery -A tasks.celery_app result {new_task.id}")
        return True
    except Exception as e:
        print(f"ОШИБКА при перезапуске задачи: {e}")
        import traceback
        traceback.print_exc()
        return False


def main():
    parser = argparse.ArgumentParser(description='Перезапуск Celery задач')
    parser.add_argument('task_id', help='ID задачи Celery для перезапуска')
    parser.add_argument('--force', action='store_true', help='Принудительный перезапуск даже для успешных задач')
    
    args = parser.parse_args()
    
    success = restart_task(args.task_id, force=args.force)
    sys.exit(0 if success else 1)


if __name__ == '__main__':
    main()

