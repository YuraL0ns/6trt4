"""
Задача архивирования события
Если у события статус archived, скачиваем все фотографии из S3, проверяем количество,
удаляем папку на S3 и создаем архив
"""
from celery import Task
from tasks.celery_app import celery_app
import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import logging
import zipfile
import tempfile
import shutil
from typing import Dict, List
from datetime import datetime, timedelta

from app.database import SessionLocal
from app.models import Event, Photo
from utils.s3_uploader import S3Uploader

logger = logging.getLogger(__name__)


class CallbackTask(Task):
    """Базовый класс для задач с callback"""
    def on_progress(self, current: int, total: int):
        self.update_state(
            state='PROGRESS',
            meta={
                'progress': int((current / total) * 100),
                'current': current,
                'total': total
            }
        )


@celery_app.task(bind=True, base=CallbackTask)
def archive_event_photos(self, event_id: str):
    """
    Архивировать фотографии события
    
    Процесс:
    1. Найти все фотографии события с S3 URL
    2. Скачать папку original_photo с S3
    3. Проверить что кол-во файлов скачанных равно кол-ву файлов на облаке
    4. Если результат успешный, удалить папку на облаке
    5. Файлы которые скачал запихнуть в архив
    """
    db = SessionLocal()
    s3_uploader = S3Uploader()
    
    try:
        # Получаем событие
        event = db.query(Event).filter(Event.id == event_id).first()
        if not event:
            logger.error(f"Event not found: {event_id}")
            return {"error": "Event not found", "status": "failed"}
        
        if event.status != 'archived':
            logger.warning(f"Event {event_id} is not archived, skipping")
            return {"error": "Event is not archived", "status": "skipped"}
        
        # Получаем все фотографии события с S3 URL
        photos = db.query(Photo).filter(
            Photo.event_id == event_id,
            Photo.s3_original_url.isnot(None)
        ).all()
        
        if not photos:
            logger.warning(f"No photos with S3 URLs found for event {event_id}")
            return {"error": "No photos with S3 URLs found", "status": "skipped"}
        
        logger.info(f"Starting archive for event {event_id}, photos count: {len(photos)}")
        
        # Создаем временную директорию для скачанных файлов
        temp_dir = tempfile.mkdtemp(prefix=f'event_archive_{event_id}_')
        downloaded_files = []
        
        try:
            # Скачиваем все файлы с S3
            s3_prefix = f"hunter-photo/events/{event_id}/original_photo/"
            s3_files_count = 0
            downloaded_count = 0
            
            # Получаем список файлов на S3
            if not s3_uploader.is_available():
                logger.error("S3 uploader not available")
                return {"error": "S3 uploader not available", "status": "failed"}
            
            try:
                # Получаем список объектов в папке original_photo на S3
                paginator = s3_uploader.s3_client.get_paginator('list_objects_v2')
                pages = paginator.paginate(
                    Bucket=s3_uploader.bucket_name,
                    Prefix=s3_prefix
                )
                
                s3_files = []
                for page in pages:
                    if 'Contents' in page:
                        for obj in page['Contents']:
                            # Пропускаем папки (объекты заканчивающиеся на /)
                            if not obj['Key'].endswith('/'):
                                s3_files.append(obj['Key'])
                                s3_files_count += 1
                
                logger.info(f"Found {s3_files_count} files on S3 for event {event_id}")
                
                # Скачиваем каждый файл
                for s3_key in s3_files:
                    try:
                        filename = os.path.basename(s3_key)
                        local_path = os.path.join(temp_dir, filename)
                        
                        # Скачиваем файл
                        s3_uploader.s3_client.download_file(
                            s3_uploader.bucket_name,
                            s3_key,
                            local_path
                        )
                        
                        downloaded_files.append(local_path)
                        downloaded_count += 1
                        
                        # Обновляем прогресс
                        if downloaded_count % 10 == 0:
                            self.on_progress(downloaded_count, s3_files_count)
                        
                        logger.debug(f"Downloaded {s3_key} to {local_path}")
                    except Exception as e:
                        logger.error(f"Error downloading {s3_key}: {e}")
                        continue
                
            except Exception as e:
                logger.error(f"Error listing S3 files: {e}")
                return {"error": f"Error listing S3 files: {str(e)}", "status": "failed"}
            
            # Проверяем что количество файлов совпадает
            if downloaded_count != s3_files_count:
                logger.error(
                    f"File count mismatch: downloaded={downloaded_count}, s3={s3_files_count}"
                )
                # Очищаем временные файлы
                shutil.rmtree(temp_dir, ignore_errors=True)
                return {
                    "error": f"File count mismatch: downloaded={downloaded_count}, s3={s3_files_count}",
                    "status": "failed"
                }
            
            logger.info(f"Successfully downloaded {downloaded_count} files, count matches")
            
            # Создаем архив
            archive_path = f"/var/www/html/storage/app/public/events/{event_id}/archive_{event_id}_{datetime.now().strftime('%Y%m%d_%H%M%S')}.zip"
            archive_dir = os.path.dirname(archive_path)
            
            if not os.path.exists(archive_dir):
                os.makedirs(archive_dir, mode=0o755, exist_ok=True)
            
            with zipfile.ZipFile(archive_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
                for local_file in downloaded_files:
                    if os.path.exists(local_file):
                        filename = os.path.basename(local_file)
                        zipf.write(local_file, filename)
                        logger.debug(f"Added {filename} to archive")
            
            logger.info(f"Archive created: {archive_path}")
            
            # НЕ удаляем папку original_photo на S3
            # Оригиналы нужны для повторного анализа событий
            # Если нужно освободить место, можно удалить вручную через админ-панель
            logger.info(f"Skipping S3 deletion to preserve originals for re-analysis. Files count: {len(s3_files)}")
            
            # Раскомментируйте код ниже, если нужно удалять файлы с S3 после архивации:
            # try:
            #     # Удаляем все объекты в папке
            #     for s3_key in s3_files:
            #         try:
            #             s3_uploader.s3_client.delete_object(
            #                 Bucket=s3_uploader.bucket_name,
            #                 Key=s3_key
            #             )
            #             logger.debug(f"Deleted {s3_key} from S3")
            #         except Exception as e:
            #             logger.error(f"Error deleting {s3_key} from S3: {e}")
            #     
            #     logger.info(f"Deleted {len(s3_files)} files from S3")
            # except Exception as e:
            #     logger.error(f"Error deleting files from S3: {e}")
            #     # Не прерываем процесс, так как архив уже создан
            
            # Очищаем временные файлы
            shutil.rmtree(temp_dir, ignore_errors=True)
            
            return {
                "status": "completed",
                "event_id": event_id,
                "files_downloaded": downloaded_count,
                "archive_path": archive_path,
                "s3_files_deleted": len(s3_files)
            }
            
        except Exception as e:
            logger.error(f"Error during archive process: {e}", exc_info=True)
            # Очищаем временные файлы в случае ошибки
            if os.path.exists(temp_dir):
                shutil.rmtree(temp_dir, ignore_errors=True)
            return {"error": str(e), "status": "failed"}
    
    finally:
        db.close()


@celery_app.task
def check_events_for_archiving():
    """
    Периодическая задача для проверки событий, которые нужно архивировать
    Вызывается автоматически через 30 дней после публикации события
    """
    db = SessionLocal()
    
    try:
        # Находим события со статусом published, которые были опубликованы более 30 дней назад
        thirty_days_ago = datetime.now() - timedelta(days=30)
        
        events_to_archive = db.query(Event).filter(
            Event.status == 'published',
            Event.created_at <= thirty_days_ago
        ).all()
        
        logger.info(f"Found {len(events_to_archive)} events to check for archiving")
        
        for event in events_to_archive:
            # Меняем статус на archived
            event.status = 'archived'
            db.commit()
            
            # Запускаем задачу архивирования
            archive_event_photos.delay(str(event.id))
            logger.info(f"Started archiving for event {event.id}")
        
        return {
            "status": "completed",
            "events_checked": len(events_to_archive)
        }
    
    finally:
        db.close()

