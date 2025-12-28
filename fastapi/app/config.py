from pydantic_settings import BaseSettings
from typing import Optional, List, Union
from pydantic import field_validator, Field
import json
import os


class Settings(BaseSettings):
    # Database
    DATABASE_URL: str = "postgresql://user:password@localhost:5432/hunter_photo"
    
    # Redis
    REDIS_HOST: str = "localhost"
    REDIS_PORT: int = 6379
    REDIS_DB: int = 0
    
    # Celery
    CELERY_BROKER_URL: str = "redis://localhost:6379/0"
    CELERY_RESULT_BACKEND: str = "redis://localhost:6379/0"
    
    # File Storage
    STORAGE_PATH: str = "/var/www/hunter-photo/storage"
    UPLOAD_MAX_SIZE: int = 20 * 1024 * 1024  # 20MB
    UPLOAD_MAX_FILES: int = 15000
    
    # S3 Storage
    S3_CLOUD_URL: Optional[str] = None
    S3_CLOUD_ACCESS_KEY: Optional[str] = None
    S3_CLOUD_SECRET_KEY: Optional[str] = None
    S3_CLOUD_REGION: Optional[str] = None
    S3_BUCKET_NAME: Optional[str] = None
    
    # ML Models
    INSIGHTFACE_MODEL_PATH: Optional[str] = None  # Auto-download if None
    
    # EASYOCR_LANGUAGES - используем Union для поддержки разных типов
    # и обрабатываем через валидатор до парсинга pydantic
    EASYOCR_LANGUAGES: Union[str, List[str]] = Field(default="en,ru")
    
    @field_validator('EASYOCR_LANGUAGES', mode='before')
    @classmethod
    def parse_easyocr_languages(cls, v):
        """Парсинг EASYOCR_LANGUAGES из строки или списка"""
        # Если значение None или пустая строка, возвращаем значение по умолчанию
        if v is None:
            return ["en", "ru"]
        
        # Если это уже список, возвращаем как есть
        if isinstance(v, list):
            return v
        
        # Если это строка
        if isinstance(v, str):
            # Если пустая строка, возвращаем значение по умолчанию
            v = v.strip()
            if not v:
                return ["en", "ru"]
            
            # Пытаемся распарсить как JSON
            try:
                parsed = json.loads(v)
                if isinstance(parsed, list):
                    return parsed
            except (json.JSONDecodeError, ValueError):
                pass
            
            # Если не JSON, пытаемся разделить по запятой
            languages = [lang.strip() for lang in v.split(',') if lang.strip()]
            if languages:
                return languages
        
        # Значение по умолчанию
        return ["en", "ru"]
    
    @property
    def easyocr_languages_list(self) -> List[str]:
        """Получить EASYOCR_LANGUAGES как список"""
        if isinstance(self.EASYOCR_LANGUAGES, list):
            return self.EASYOCR_LANGUAGES
        return self.parse_easyocr_languages(self.EASYOCR_LANGUAGES)
    
    # API
    API_HOST: str = "0.0.0.0"
    API_PORT: int = 8000
    API_PREFIX: str = "/api/v1"
    
    # Environment
    ENVIRONMENT: str = "development"  # development, staging, production
    
    # Security
    ALLOWED_ORIGINS: Union[str, List[str]] = Field(default="https://hunter-photo.ru,http://localhost:8000")
    
    @field_validator('ALLOWED_ORIGINS', mode='before')
    @classmethod
    def parse_allowed_origins(cls, v):
        """Парсинг ALLOWED_ORIGINS из строки или списка"""
        # Если значение None или пустая строка, возвращаем значение по умолчанию
        if v is None:
            return ["https://hunter-photo.ru", "http://localhost:8000"]
        
        # Если это уже список, возвращаем как есть
        if isinstance(v, list):
            return v
        
        # Если это строка
        if isinstance(v, str):
            v = v.strip()
            if not v:
                return ["https://hunter-photo.ru", "http://localhost:8000"]
            
            # Пытаемся распарсить как JSON
            try:
                parsed = json.loads(v)
                if isinstance(parsed, list):
                    return parsed
            except (json.JSONDecodeError, ValueError):
                pass
            
            # Если не JSON, пытаемся разделить по запятой
            origins = [origin.strip() for origin in v.split(',') if origin.strip()]
            if origins:
                return origins
        
        # Значение по умолчанию
        return ["https://hunter-photo.ru", "http://localhost:8000"]
    
    @property
    def allowed_origins_list(self) -> List[str]:
        """Получить ALLOWED_ORIGINS как список"""
        if isinstance(self.ALLOWED_ORIGINS, list):
            return self.ALLOWED_ORIGINS
        return self.parse_allowed_origins(self.ALLOWED_ORIGINS)
    
    class Config:
        env_file = ".env"
        case_sensitive = True
        # Разрешаем дополнительные поля из окружения (на случай если что-то забыли добавить)
        extra = "ignore"


settings = Settings()


