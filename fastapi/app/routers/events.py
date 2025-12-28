from fastapi import APIRouter, HTTPException, Depends, UploadFile, File, Form
from sqlalchemy.orm import Session
from typing import List, Optional
from app.database import get_db
from app.schemas.event import EventCreate, EventResponse
from app.models.event import Event
from utils.cover_processor import CoverProcessor
from tasks.photo_processing import process_event_photos
import os
import logging

logger = logging.getLogger(__name__)
router = APIRouter()


@router.get("/events/{event_id}", response_model=EventResponse)
async def get_event(event_id: str, db: Session = Depends(get_db)):
    """Получить информацию о событии"""
    event = db.query(Event).filter(Event.id == event_id).first()
    if not event:
        raise HTTPException(status_code=404, detail="Event not found")
    return event


@router.post("/events/{event_id}/start-analysis")
async def start_analysis(
    event_id: str,
    analyses: dict,
    db: Session = Depends(get_db)
):
    """Запустить анализ фотографий события"""
    event = db.query(Event).filter(Event.id == event_id).first()
    if not event:
        raise HTTPException(status_code=404, detail="Event not found")
    
    try:
        # Валидируем analyses
        if not isinstance(analyses, dict):
            raise HTTPException(status_code=400, detail="analyses must be a dictionary")
        
        # Проверяем, что есть хотя бы один включенный анализ
        enabled_analyses = [k for k, v in analyses.items() if v is True]
        if not enabled_analyses:
            logger.warning(f"No enabled analyses for event {event_id}")
            # Не блокируем, но логируем предупреждение
        
        logger.info(f"Starting analysis for event {event_id}, enabled analyses: {enabled_analyses}")
        
        # Проверяем, что задача доступна
        if not process_event_photos:
            raise HTTPException(
                status_code=500,
                detail="Celery task 'process_event_photos' is not available. Check task registration."
            )
    
        # Запускаем Celery задачу
        try:
            logger.info(f"Calling process_event_photos.delay for event {event_id}")
            task = process_event_photos.delay(event_id, analyses)
            logger.info(f"Celery task started for event {event_id}, task_id: {task.id}")
        except Exception as e:
            logger.error(f"Failed to start Celery task for event {event_id}: {e}", exc_info=True)
            raise HTTPException(
                status_code=500, 
                detail=f"Failed to start Celery task: {str(e)}. Check Celery worker status and Redis connection."
            )
        
        return {
            "task_id": task.id,
            "status": "started",
            "event_id": event_id,
            "enabled_analyses": enabled_analyses
        }
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error in start_analysis for event {event_id}: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")


@router.get("/events/{event_id}/event-info")
async def get_event_info(event_id: str):
    """
    Получить event_info.json для события
    Используется Laravel для polling статусов анализа
    """
    import json
    import fcntl
    
    event_info_path = f"/var/www/html/storage/app/public/events/{event_id}/event_info.json"
    
    if not os.path.exists(event_info_path):
        raise HTTPException(status_code=404, detail="event_info.json not found")
    
    max_retries = 3
    retry_count = 0
    
    while retry_count < max_retries:
        try:
            with open(event_info_path, 'r', encoding='utf-8') as f:
                # Пытаемся заблокировать файл для чтения
                try:
                    fcntl.flock(f, fcntl.LOCK_SH)  # Shared lock для чтения
                except (AttributeError, OSError):
                    pass
                
                try:
                    event_info = json.load(f)
                    
                    try:
                        fcntl.flock(f, fcntl.LOCK_UN)
                    except (AttributeError, OSError):
                        pass
                    
                    logger.info(f"Event info read for event {event_id}")
                    return event_info
                    
                except json.JSONDecodeError as e:
                    try:
                        fcntl.flock(f, fcntl.LOCK_UN)
                    except (AttributeError, OSError):
                        pass
                    
                    logger.warning(f"Error parsing event_info.json for event {event_id} (attempt {retry_count + 1}): {e}")
                    
                    if retry_count < max_retries - 1:
                        retry_count += 1
                        import asyncio
                        await asyncio.sleep(0.1)  # Небольшая задержка перед повтором
                        continue
                    else:
                        # Попытка восстановить частичные данные
                        logger.error(f"Failed to parse event_info.json after {max_retries} attempts, trying recovery")
                        try:
                            # Пытаемся прочитать файл построчно и найти последнюю валидную запись
                            f.seek(0)
                            content = f.read()
                            # Пытаемся найти последнюю закрывающую скобку
                            last_brace = content.rfind('}')
                            if last_brace > 0:
                                # Пытаемся парсить до последней закрывающей скобки
                                partial_content = content[:last_brace + 1]
                                # Проверяем, что это валидный JSON
                                try:
                                    partial_json = json.loads(partial_content)
                                    logger.warning(f"Returning partial event_info.json for event {event_id}")
                                    return partial_json
                                except:
                                    pass
                        except:
                            pass
                        
                        raise HTTPException(
                            status_code=500, 
                            detail=f"Error parsing event_info.json: {str(e)}. File may be corrupted. Please check the file manually."
                        )
        
        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"Error reading event_info.json for event {event_id} (attempt {retry_count + 1}): {e}")
            if retry_count < max_retries - 1:
                retry_count += 1
                import asyncio
                await asyncio.sleep(0.1)
                continue
            else:
                raise HTTPException(status_code=500, detail=f"Error reading event_info.json: {str(e)}")
    
    raise HTTPException(status_code=500, detail="Failed to read event_info.json after multiple attempts")


@router.post("/events/{event_id}/process-cover")
async def process_cover(
    event_id: str,
    cover_file: UploadFile = File(...),
    title: str = Form(...),
    city: str = Form(...),
    date: str = Form(...),
    storage_path: str = Form(...),
    db: Session = Depends(get_db)
):
    """
    Обработать обложку события:
    - Затемнить изображение
    - Добавить текст (название, город, дата)
    - Добавить логотип (если есть)
    - Сохранить в указанную директорию Laravel
    """
    logger.info(f"Processing cover for event {event_id}")
    
    event = db.query(Event).filter(Event.id == event_id).first()
    if not event:
        raise HTTPException(status_code=404, detail="Event not found")
    
    try:
        # Создаем директорию для обложки если её нет
        cover_dir = os.path.dirname(storage_path)
        if cover_dir and not os.path.exists(cover_dir):
            os.makedirs(cover_dir, mode=0o755, exist_ok=True)
            logger.info(f"Created directory: {cover_dir}")
        
        # Альтернативные пути к storage Laravel
        laravel_storage_paths = [
            storage_path,  # Прямой путь
            storage_path.replace("/var/www/html/storage", "/app/laravel/storage"),  # Docker путь
            storage_path.replace("/var/www/html/storage", "/shared/laravel/storage"),  # Shared volume
        ]
        
        # Находим существующий путь
        actual_storage_path = None
        for path in laravel_storage_paths:
            if os.path.exists(os.path.dirname(path)) or os.path.exists(os.path.dirname(os.path.dirname(path))):
                actual_storage_path = path
                break
        
        if actual_storage_path is None:
            actual_storage_path = storage_path
            logger.warning(f"Using default storage path: {actual_storage_path}")
        
        # Обновляем путь для сохранения
        storage_path = actual_storage_path
        cover_dir = os.path.dirname(storage_path)
        if cover_dir and not os.path.exists(cover_dir):
            os.makedirs(cover_dir, mode=0o755, exist_ok=True)
            logger.info(f"Created directory: {cover_dir}")
        
        # Сохраняем загруженный файл временно
        temp_path = f"/tmp/cover_{event_id}_{cover_file.filename}"
        with open(temp_path, "wb") as f:
            content = await cover_file.read()
            f.write(content)
        
        logger.debug(f"Cover saved to temp: {temp_path}, size: {len(content)} bytes")
        
        # Обрабатываем обложку
        processor = CoverProcessor()
        
        # Путь к логотипу (если есть)
        logo_path = None
        possible_logo_paths = [
            "/var/www/html/public/images/logo.png",
            "/app/public/images/logo.png",
            os.path.join(os.path.dirname(storage_path), "../../../public/images/logo.png"),
        ]
        
        for path in possible_logo_paths:
            if os.path.exists(path):
                logo_path = path
                break
        
        processed_path = processor.process_cover(
            image_path=temp_path,
            title=title,
            city=city,
            date=date,
            logo_path=logo_path,
            output_path=storage_path
        )
        
        # Удаляем временный файл
        if os.path.exists(temp_path):
            os.remove(temp_path)
        
        # Обновляем путь к обложке в БД
        relative_path = storage_path.replace("/var/www/html/storage/app/public/", "")
        event.cover_path = relative_path
        db.commit()
        
        logger.info(f"Cover processed successfully: {processed_path}")
        
        return {
            "success": True,
            "cover_path": relative_path,
            "full_path": processed_path,
            "message": "Обложка успешно обработана"
        }
        
    except Exception as e:
        logger.error(f"Error processing cover: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=f"Ошибка обработки обложки: {str(e)}")


@router.post("/events/{event_id}/archive")
async def archive_event(event_id: str, db: Session = Depends(get_db)):
    """
    Запустить архивирование события
    Вызывается когда событие переводится в статус archived
    """
    from tasks.event_archive import archive_event_photos
    
    event = db.query(Event).filter(Event.id == event_id).first()
    if not event:
        raise HTTPException(status_code=404, detail="Event not found")
    
    if event.status != 'archived':
        raise HTTPException(
            status_code=400, 
            detail=f"Event status is '{event.status}', expected 'archived'"
        )
    
    # Запускаем Celery задачу
    task = archive_event_photos.delay(event_id)
    
    logger.info(f"Archive task started for event {event_id}, task_id: {task.id}")
    
    return {
        "task_id": task.id,
        "status": "started",
        "event_id": event_id,
        "message": "Архивирование события запущено"
    }

