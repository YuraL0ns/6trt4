"""
Модуль для логирования каждого шага анализа в отдельные файлы
"""
import logging
import os
from datetime import datetime
from typing import Optional


class StepLogger:
    """Логгер для каждого шага анализа фотографии"""
    
    def __init__(self, event_id: str, photo_id: str, step_name: str):
        self.event_id = event_id
        self.photo_id = photo_id
        self.step_name = step_name
        
        # Создаем директорию для логов
        log_dir = f"/var/www/html/storage/app/public/events/{event_id}/logs"
        os.makedirs(log_dir, mode=0o755, exist_ok=True)
        
        # Путь к файлу лога
        log_file = os.path.join(log_dir, f"{photo_id}_{step_name}.log")
        
        # Создаем логгер
        self.logger = logging.getLogger(f"step_{event_id}_{photo_id}_{step_name}")
        self.logger.setLevel(logging.DEBUG)
        
        # Удаляем существующие handlers
        self.logger.handlers = []
        
        # Создаем file handler
        file_handler = logging.FileHandler(log_file, encoding='utf-8')
        file_handler.setLevel(logging.DEBUG)
        
        # Формат лога
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )
        file_handler.setFormatter(formatter)
        
        self.logger.addHandler(file_handler)
        
        # Также добавляем console handler для отладки
        console_handler = logging.StreamHandler()
        console_handler.setLevel(logging.INFO)
        console_handler.setFormatter(formatter)
        self.logger.addHandler(console_handler)
        
        self.log_file = log_file
    
    def info(self, message: str):
        """Логировать информационное сообщение"""
        self.logger.info(message)
    
    def debug(self, message: str):
        """Логировать отладочное сообщение"""
        self.logger.debug(message)
    
    def warning(self, message: str):
        """Логировать предупреждение"""
        self.logger.warning(message)
    
    def error(self, message: str, exc_info: bool = False):
        """Логировать ошибку"""
        self.logger.error(message, exc_info=exc_info)
    
    def exception(self, message: str):
        """Логировать исключение"""
        self.logger.exception(message)
    
    def get_log_file(self) -> str:
        """Получить путь к файлу лога"""
        return self.log_file

