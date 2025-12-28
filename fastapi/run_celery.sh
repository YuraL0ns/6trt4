#!/bin/bash
# Скрипт для запуска Celery worker
# Слушаем все очереди: celery (default), high_priority, default, low_priority

celery -A tasks.celery_app worker --loglevel=info --concurrency=4 --queues=celery,high_priority,default,low_priority


