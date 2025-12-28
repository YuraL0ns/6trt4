#!/bin/bash

# Скрипт для пересборки и перезапуска FastAPI контейнера

echo "Останавливаем FastAPI контейнер..."
docker stop hunter-photo-fastapi 2>/dev/null || true
docker rm hunter-photo-fastapi 2>/dev/null || true

echo "Пересобираем FastAPI образ..."
docker build -t hunter-photo-fastapi ./fastapi

echo "Запускаем FastAPI контейнер..."
cd "$(dirname "$0")"
docker compose up -d fastapi

echo "Ждем запуска FastAPI..."
sleep 5

echo "Проверяем статус..."
docker ps | grep fastapi

echo "Проверяем доступность..."
curl -s http://localhost:8001/api/v1/health && echo " - FastAPI доступен!" || echo " - FastAPI недоступен, проверьте логи: docker logs hunter-photo-fastapi"

