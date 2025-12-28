# Управление Celery задачами

## Перезапуск задач через терминал/SSH

### Использование скрипта restart_task.py

Скрипт позволяет перезапустить зависшую или завершившуюся с ошибкой задачу Celery.

#### Базовое использование:

```bash
# Войти в контейнер FastAPI
docker exec -it hunter-photo-fastapi bash

# Перезапустить задачу
python restart_task.py <task_id>
```

#### Примеры:

```bash
# Перезапустить задачу
python restart_task.py abc123-def456-ghi789

# Принудительный перезапуск (даже для успешных задач)
python restart_task.py abc123-def456-ghi789 --force
```

### Получение task_id

Task ID можно получить из:
1. Логов Laravel (`storage/logs/laravel.log`)
2. Админ-панели: `/admin/celery/events/{eventId}`
3. Логов Celery: `/app/logs/tasks/`

### Просмотр логов задач

Логи каждой задачи сохраняются в отдельный файл:
```
/app/logs/tasks/process_event_photos_{task_id}.log
```

### Проверка статуса задачи

```bash
# Через Celery CLI
celery -A tasks.celery_app result <task_id>

# Через Python
python -c "from celery.result import AsyncResult; from tasks.celery_app import celery_app; r = AsyncResult('<task_id>', app=celery_app); print(f'State: {r.state}, Info: {r.info}')"
```

### Отмена зависшей задачи

```bash
# Отменить задачу
celery -A tasks.celery_app control revoke <task_id> --terminate

# Или через Python
python -c "from tasks.celery_app import celery_app; celery_app.control.revoke('<task_id>', terminate=True)"
```

### Мониторинг задач

```bash
# Список активных задач
celery -A tasks.celery_app inspect active

# Статистика workers
celery -A tasks.celery_app inspect stats

# Задачи в ожидании
celery -A tasks.celery_app inspect reserved
```

## Детальное логирование

Все задачи теперь логируют детальную информацию:
- Начало и завершение задачи
- Прогресс выполнения
- Все ошибки с полным traceback
- Метаданные задачи

Логи сохраняются в:
- Консоль (stdout/stderr)
- Файлы в `/app/logs/tasks/`
- Метаданные задачи в Celery

## Решение проблем

### Задача зависла

1. Проверить логи: `/app/logs/tasks/process_event_photos_{task_id}.log`
2. Проверить статус: `celery -A tasks.celery_app result <task_id>`
3. Отменить задачу: `celery -A tasks.celery_app control revoke <task_id> --terminate`
4. Перезапустить: `python restart_task.py <task_id>`

### Задача завершилась с ошибкой

1. Просмотреть логи ошибки в файле задачи
2. Исправить проблему (если возможно)
3. Перезапустить задачу: `python restart_task.py <task_id>`

### Worker не обрабатывает задачи

1. Проверить статус worker: `celery -A tasks.celery_app inspect stats`
2. Перезапустить worker: `docker restart hunter-photo-celery`
3. Проверить подключение к Redis: `redis-cli ping` (из контейнера redis)

