from fastapi import APIRouter, HTTPException, UploadFile, File, Form, Depends
from sqlalchemy.orm import Session
from typing import List, Optional
from app.database import get_db
from tasks.face_search import search_similar_faces
from tasks.number_search import search_by_numbers, extract_numbers
import tempfile
import os
import logging

logger = logging.getLogger(__name__)
router = APIRouter()


@router.post("/photos/search/face")
async def search_by_face(
    photo: UploadFile = File(...),
    event_id: Optional[str] = Form(None),
    threshold: float = Form(0.6)
):
    """Поиск похожих фотографий по лицу"""
    if not photo.content_type or not photo.content_type.startswith('image/'):
        raise HTTPException(status_code=400, detail="File must be an image")
    
    logger.info(f"Search by face request: event_id={event_id}, threshold={threshold}")
    
    # Сохраняем временный файл в директорию uploads (не удаляем сразу)
    # Файл будет удален после завершения задачи Celery
    uploads_dir = "/app/uploads"
    os.makedirs(uploads_dir, exist_ok=True)
    
    # Создаем уникальное имя файла
    import uuid
    file_ext = os.path.splitext(photo.filename or 'image.jpg')[1] or '.jpg'
    unique_filename = f"{uuid.uuid4()}{file_ext}"
    tmp_path = os.path.join(uploads_dir, unique_filename)
    
    try:
        # Сохраняем файл
        content = await photo.read()
        with open(tmp_path, 'wb') as f:
            f.write(content)
        
        logger.info(f"Saved query image to: {tmp_path}")
        
        # Запускаем поиск (файл будет удален в задаче Celery после обработки)
        results = search_similar_faces.delay(tmp_path, event_id, threshold)
        
        logger.info(f"Started search task: {results.id}, event_id={event_id}")
        
        return {
            "task_id": results.id,
            "status": "processing"
        }
    except Exception as e:
        logger.error(f"Error in search_by_face: {str(e)}", exc_info=True)
        # Удаляем файл при ошибке
        if os.path.exists(tmp_path):
            try:
            os.unlink(tmp_path)
            except:
                pass
        raise HTTPException(status_code=500, detail=f"Error starting search: {str(e)}")


@router.post("/photos/search/number")
async def search_by_number(
    photo: UploadFile = File(...),
    event_id: Optional[str] = Form(None)
):
    """Поиск фотографий по номеру"""
    if not photo.content_type or not photo.content_type.startswith('image/'):
        raise HTTPException(status_code=400, detail="File must be an image")
    
    logger.info(f"Search by number request: event_id={event_id}")
    
    # Сохраняем временный файл в директорию uploads (не удаляем сразу)
    uploads_dir = "/app/uploads"
    os.makedirs(uploads_dir, exist_ok=True)
    
    # Создаем уникальное имя файла
    import uuid
    file_ext = os.path.splitext(photo.filename or 'image.jpg')[1] or '.jpg'
    unique_filename = f"{uuid.uuid4()}{file_ext}"
    tmp_path = os.path.join(uploads_dir, unique_filename)
    
    try:
        # Сохраняем файл
        content = await photo.read()
        with open(tmp_path, 'wb') as f:
            f.write(content)
        
        logger.info(f"Saved query image to: {tmp_path}")
        
        # Запускаем поиск (файл будет удален в задаче Celery после обработки)
        results = search_by_numbers.delay(tmp_path, event_id)
        
        logger.info(f"Started search task: {results.id}, event_id={event_id}")
        
        return {
            "task_id": results.id,
            "status": "processing"
        }
    except Exception as e:
        logger.error(f"Error in search_by_number: {str(e)}", exc_info=True)
        # Удаляем файл при ошибке
        if os.path.exists(tmp_path):
            try:
            os.unlink(tmp_path)
            except:
                pass
        raise HTTPException(status_code=500, detail=f"Error starting search: {str(e)}")

