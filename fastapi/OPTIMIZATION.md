# Оптимизация задач Celery

## Рекомендации по балансировке нагрузки

### 1. Использование очередей с приоритетами

Задачи разделены на очереди с разными приоритетами:
- **high_priority**: Поиск по лицам и номерам (быстрые запросы пользователей)
- **default**: Обработка фотографий событий
- **low_priority**: Архивирование и периодические задачи

### 2. Запуск workers с разными очередями

Для оптимальной балансировки рекомендуется запускать отдельные workers для разных очередей:

```bash
# Worker для высокоприоритетных задач (поиск)
celery -A tasks.celery_app worker --loglevel=info --concurrency=2 --queues=high_priority -n worker_high@%h

# Worker для обработки фотографий
celery -A tasks.celery_app worker --loglevel=info --concurrency=4 --queues=default -n worker_default@%h

# Worker для низкоприоритетных задач
celery -A tasks.celery_app worker --loglevel=info --concurrency=2 --queues=low_priority -n worker_low@%h
```

### 3. Настройка concurrency

Текущая настройка `--concurrency=4` подходит для большинства случаев. Рекомендации:
- **Для CPU-интенсивных задач** (обработка изображений, распознавание лиц): concurrency = количество CPU ядер
- **Для I/O-интенсивных задач** (загрузка на S3): concurrency = количество CPU ядер * 2-4

### 4. Мониторинг нагрузки

Рекомендуется мониторить:
- Количество задач в очереди: `celery -A tasks.celery_app inspect active`
- Статус workers: `celery -A tasks.celery_app inspect stats`
- Задачи в ожидании: `celery -A tasks.celery_app inspect reserved`

### 5. Автоматическое масштабирование

Для автоматического масштабирования можно использовать:
- **Celery Flower** для мониторинга
- **Supervisor** или **systemd** для управления workers
- **Docker Swarm** или **Kubernetes** для горизонтального масштабирования

### 6. Оптимизация памяти

Текущие настройки:
- `worker_max_tasks_per_child=50` - перезапуск worker после 50 задач
- `result_expires=3600` - результаты хранятся 1 час

Для задач с большим потреблением памяти можно уменьшить `worker_max_tasks_per_child`.

### 7. Rate Limiting

Установлен лимит `100/m` (100 задач в минуту) по умолчанию для предотвращения перегрузки системы.

Для конкретных задач можно установить индивидуальные лимиты в декораторе:
```python
@celery_app.task(rate_limit='10/m')  # 10 задач в минуту
def my_task():
    pass
```

## Пример docker-compose для раздельных workers

```yaml
  celery_high:
    # ... конфигурация ...
    command: celery -A tasks.celery_app worker --loglevel=info --concurrency=2 --queues=high_priority -n worker_high@%h

  celery_default:
    # ... конфигурация ...
    command: celery -A tasks.celery_app worker --loglevel=info --concurrency=4 --queues=default -n worker_default@%h

  celery_low:
    # ... конфигурация ...
    command: celery -A tasks.celery_app worker --loglevel=info --concurrency=2 --queues=low_priority -n worker_low@%h
```

