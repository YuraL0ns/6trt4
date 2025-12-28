"""
Утилита для загрузки фотографий на S3 облако
"""
import os
import logging
from typing import Optional, Dict, Tuple
from app.config import settings

logger = logging.getLogger(__name__)

try:
    import boto3
    from botocore.exceptions import ClientError, BotoCoreError
    from botocore.config import Config
    BOTO3_AVAILABLE = True
except ImportError:
    BOTO3_AVAILABLE = False
    logger.warning("boto3 not installed. S3 upload functionality will be disabled.")


class S3Uploader:
    """Класс для загрузки файлов на S3"""
    
    def __init__(self):
        self.s3_client = None
        self.bucket_name = settings.S3_BUCKET_NAME
        
        if not BOTO3_AVAILABLE:
            logger.warning("boto3 not available, S3 uploader disabled")
            return
        
        # Детальное логирование настроек S3 (без секретных ключей)
        logger.info("S3 Configuration Check:", extra={
            'S3_CLOUD_URL': settings.S3_CLOUD_URL if settings.S3_CLOUD_URL else 'NOT SET',
            'S3_CLOUD_ACCESS_KEY': 'SET' if settings.S3_CLOUD_ACCESS_KEY else 'NOT SET',
            'S3_CLOUD_SECRET_KEY': 'SET' if settings.S3_CLOUD_SECRET_KEY else 'NOT SET',
            'S3_CLOUD_REGION': settings.S3_CLOUD_REGION if settings.S3_CLOUD_REGION else 'NOT SET',
            'S3_BUCKET_NAME': settings.S3_BUCKET_NAME if settings.S3_BUCKET_NAME else 'NOT SET',
        })
        
        if not all([
            settings.S3_CLOUD_ACCESS_KEY,
            settings.S3_CLOUD_SECRET_KEY,
            settings.S3_CLOUD_REGION,
            settings.S3_BUCKET_NAME
        ]):
            missing = []
            if not settings.S3_CLOUD_ACCESS_KEY:
                missing.append('S3_CLOUD_ACCESS_KEY')
            if not settings.S3_CLOUD_SECRET_KEY:
                missing.append('S3_CLOUD_SECRET_KEY')
            if not settings.S3_CLOUD_REGION:
                missing.append('S3_CLOUD_REGION')
            if not settings.S3_BUCKET_NAME:
                missing.append('S3_BUCKET_NAME')
            logger.warning(f"S3 credentials not configured, S3 uploader disabled. Missing: {', '.join(missing)}")
            return
        
        try:
            # Используем Config с addressing_style='path' для совместимости с S3-совместимыми хранилищами
            s3_config = Config(
                s3={'addressing_style': 'path'}
            )
            
            self.s3_client = boto3.client(
                's3',
                endpoint_url=settings.S3_CLOUD_URL,
                region_name=settings.S3_CLOUD_REGION,
                aws_access_key_id=settings.S3_CLOUD_ACCESS_KEY,
                aws_secret_access_key=settings.S3_CLOUD_SECRET_KEY,
                config=s3_config
            )
            logger.info("S3 client initialized successfully", extra={
                'endpoint_url': settings.S3_CLOUD_URL,
                'region': settings.S3_CLOUD_REGION,
                'bucket': settings.S3_BUCKET_NAME,
                'addressing_style': 'path'
            })
        except Exception as e:
            logger.error(f"Failed to initialize S3 client: {e}", exc_info=True)
            self.s3_client = None
    
    def is_available(self) -> bool:
        """Проверить, доступен ли S3 клиент"""
        return self.s3_client is not None and self.bucket_name is not None
    
    def upload_file(self, local_path: str, s3_key: str, content_type: Optional[str] = None) -> Optional[str]:
        """
        Загрузить файл на S3
        
        Args:
            local_path: Локальный путь к файлу
            s3_key: Ключ (путь) в S3 bucket
            content_type: MIME тип файла (опционально)
        
        Returns:
            URL загруженного файла или None в случае ошибки
        """
        if not self.is_available():
            logger.warning("S3 uploader not available, skipping upload")
            return None
        
        if not os.path.exists(local_path):
            logger.error(f"File not found: {local_path}")
            return None
        
        try:
            extra_args = {}
            if content_type:
                extra_args['ContentType'] = content_type
            else:
                # Определяем content_type по расширению
                ext = os.path.splitext(local_path)[1].lower()
                content_types = {
                    '.jpg': 'image/jpeg',
                    '.jpeg': 'image/jpeg',
                    '.png': 'image/png',
                    '.webp': 'image/webp',
                }
                if ext in content_types:
                    extra_args['ContentType'] = content_types[ext]
            
            logger.info(f"Uploading file to S3: {local_path} -> {s3_key}")
            self.s3_client.upload_file(
                local_path,
                self.bucket_name,
                s3_key,
                ExtraArgs=extra_args
            )
            
            # Формируем URL
            if settings.S3_CLOUD_URL:
                # Если указан endpoint_url, используем его
                url = f"{settings.S3_CLOUD_URL.rstrip('/')}/{self.bucket_name}/{s3_key}"
            else:
                # Стандартный AWS S3 URL
                url = f"https://{self.bucket_name}.s3.{settings.S3_CLOUD_REGION}.amazonaws.com/{s3_key}"
            
            logger.info(f"File uploaded successfully: {url}")
            return url
            
        except ClientError as e:
            logger.error(f"Error uploading file to S3: {e}")
            return None
        except Exception as e:
            logger.error(f"Unexpected error uploading file to S3: {e}")
            return None
    
    def upload_event_photos(
        self, 
        event_id: str, 
        photos_data: Dict[str, Dict],
        db_session = None
    ) -> Dict[str, Dict[str, str]]:
        """
        Загрузить все фотографии события на S3
        
        Args:
            event_id: ID события
            photos_data: Словарь с данными фотографий из event_info.json
                {
                    "photo_name.jpg": {
                        "id": "photo_id",
                        "custom_path": "/path/to/custom_photo.webp",
                        "original_path": "/path/to/original_photo.jpg"
                    }
                }
            db_session: SQLAlchemy сессия для обновления базы данных
        
        Returns:
            Словарь с S3 URL для каждой фотографии:
            {
                "photo_id": {
                    "custom_url": "https://s3.../custom_photo.webp",
                    "original_url": "https://s3.../original_photo.jpg"
                }
            }
        """
        if not self.is_available():
            logger.warning("S3 uploader not available, skipping upload")
            return {}
        
        uploaded_urls = {}
        base_path = "/var/www/html/storage/app/public"
        
        for photo_name, photo_info in photos_data.items():
            photo_id = photo_info.get('id')
            if not photo_id:
                logger.warning(f"Photo {photo_name} has no ID, skipping")
                continue
            
            uploaded_urls[photo_id] = {
                'custom_url': None,
                'original_url': None
            }
            
            # Загружаем custom_photo
            # Пробуем получить путь из разных источников
            custom_path = None
            if db_session:
                from app.models import Photo
                photo = db_session.query(Photo).filter(Photo.id == photo_id).first()
                if photo and photo.custom_path:
                    custom_path = photo.custom_path
            
            if not custom_path:
                custom_path = photo_info.get('custom_path') or photo_info.get('docker_path')
            
            if custom_path:
                # Преобразуем относительный путь в абсолютный для Docker контейнера
                if not custom_path.startswith('/'):
                    # Относительный путь
                    full_custom_path = os.path.join(base_path, custom_path)
                elif custom_path.startswith('/var/www/html/storage/app/public/'):
                    # Уже абсолютный путь Docker
                    full_custom_path = custom_path
                else:
                    # Другой формат пути
                    full_custom_path = os.path.join(base_path, custom_path.lstrip('/'))
                
                # Проверяем, что файл существует
                if os.path.exists(full_custom_path):
                    s3_key_custom = f"hunter-photo/events/{event_id}/custom_photo/{os.path.basename(full_custom_path)}"
                    custom_url = self.upload_file(full_custom_path, s3_key_custom)
                    uploaded_urls[photo_id]['custom_url'] = custom_url
                else:
                    logger.warning(f"Custom photo not found: {full_custom_path}")
            
            # Загружаем original_photo
            original_path = None
            if db_session:
                from app.models import Photo
                photo = db_session.query(Photo).filter(Photo.id == photo_id).first()
                if photo and photo.original_path:
                    original_path = photo.original_path
            
            if not original_path:
                original_path = photo_info.get('original_path') or photo_info.get('relative_path')
            
            if original_path:
                # Преобразуем относительный путь в абсолютный для Docker контейнера
                if not original_path.startswith('/'):
                    # Относительный путь
                    full_original_path = os.path.join(base_path, original_path)
                elif original_path.startswith('/var/www/html/storage/app/public/'):
                    # Уже абсолютный путь Docker
                    full_original_path = original_path
                else:
                    # Другой формат пути
                    full_original_path = os.path.join(base_path, original_path.lstrip('/'))
                
                # Проверяем, что файл существует
                if os.path.exists(full_original_path):
                    s3_key_original = f"hunter-photo/events/{event_id}/original_photo/{os.path.basename(full_original_path)}"
                    original_url = self.upload_file(full_original_path, s3_key_original)
                    uploaded_urls[photo_id]['original_url'] = original_url
                else:
                    logger.warning(f"Original photo not found: {full_original_path}")
        
        return uploaded_urls

