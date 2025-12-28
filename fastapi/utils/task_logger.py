"""
Утилита для детального логирования Celery задач
"""
import logging
import os
import traceback
from datetime import datetime
from typing import Optional, Dict, Any
import json


class TaskLogger:
    """Логгер для Celery задач с записью в файл"""
    
    # Кэш для логгеров, чтобы не создавать дубликаты
    _loggers_cache: Dict[str, logging.Logger] = {}
    _handlers_cache: Dict[str, logging.Handler] = {}
    _file_handlers: Dict[str, logging.Handler] = {}  # Отдельный кэш для файловых хендлеров
    
    def __init__(self, task_name: str, task_id: Optional[str] = None):
        self.task_name = task_name
        self.task_id = task_id
        self.log_dir = "/app/logs/tasks"
        self._ensure_log_dir()
        
        # Создаем уникальный ключ для кэша
        logger_key = f"{task_name}_{task_id}" if task_id else task_name
        
        # Используем кэш, чтобы не создавать дубликаты хендлеров
        if logger_key not in self._loggers_cache:
            # Создаем логгер
            self.logger = logging.getLogger(f"task.{logger_key}")
            self.logger.setLevel(logging.DEBUG)
            # Предотвращаем распространение на родительские логгеры
            self.logger.propagate = False
            
            # Формат логов
            formatter = logging.Formatter(
                '%(asctime)s - %(name)s - %(levelname)s - %(message)s',
                datefmt='%Y-%m-%d %H:%M:%S'
            )
            
            # Консольный handler (только один для всех задач)
            if 'console' not in self._handlers_cache:
                console_handler = logging.StreamHandler()
                console_handler.setLevel(logging.INFO)
                console_handler.setFormatter(formatter)
                self._handlers_cache['console'] = console_handler
            
            # Файловый handler для задачи
            # ВАЖНО: Создаем файловый хендлер только если его еще нет в кэше
            if task_id and logger_key not in self._file_handlers:
                log_file = os.path.join(self.log_dir, f"{task_name}_{task_id}.log")
                try:
                    # Используем RotatingFileHandler для предотвращения переполнения
                    from logging.handlers import RotatingFileHandler
                    file_handler = RotatingFileHandler(
                        log_file, 
                        encoding='utf-8',
                        maxBytes=10*1024*1024,  # 10MB
                        backupCount=3
                    )
                    file_handler.setLevel(logging.DEBUG)
                    file_handler.setFormatter(formatter)
                    self.logger.addHandler(file_handler)
                    self._file_handlers[logger_key] = file_handler
                    self.log_file = log_file
                except OSError as e:
                    # Если не удалось создать файл (например, слишком много открытых файлов),
                    # используем только консольный логгер
                    _logger = logging.getLogger(__name__)
                    _logger.warning(f"Failed to create file handler for {log_file}: {e}. Using console only.")
                    self.log_file = None
            elif task_id:
                # Используем существующий файловый хендлер
                self.log_file = os.path.join(self.log_dir, f"{task_name}_{task_id}.log")
            else:
                self.log_file = None
            
            # Добавляем консольный handler только если его еще нет
            console_handler_names = [h.name for h in self.logger.handlers if hasattr(h, 'name')]
            if 'console' not in console_handler_names:
                self.logger.addHandler(self._handlers_cache['console'])
            
            self._loggers_cache[logger_key] = self.logger
        else:
            # Используем существующий логгер из кэша
            self.logger = self._loggers_cache[logger_key]
            if task_id:
                self.log_file = os.path.join(self.log_dir, f"{task_name}_{task_id}.log")
            else:
                self.log_file = None
        
        # Сохраняем ключ для закрытия
        self._logger_key = logger_key
    
    def close(self):
        """Закрыть файловый хендлер логгера (консольный оставляем)"""
        if hasattr(self, '_logger_key') and self._logger_key in self._file_handlers:
            file_handler = self._file_handlers[self._logger_key]
            try:
                # Закрываем только файловый хендлер
                if file_handler in self.logger.handlers:
                    self.logger.removeHandler(file_handler)
                file_handler.close()
                # Удаляем из кэша
                del self._file_handlers[self._logger_key]
            except Exception as e:
                _logger = logging.getLogger(__name__)
                _logger.warning(f"Error closing file handler: {e}")
    
    def _ensure_log_dir(self):
        """Создать директорию для логов если её нет"""
        if not os.path.exists(self.log_dir):
            os.makedirs(self.log_dir, mode=0o755, exist_ok=True)
    
    def info(self, message: str, **kwargs):
        """Логировать информационное сообщение"""
        if kwargs:
            message = f"{message} | {json.dumps(kwargs, default=str, ensure_ascii=False)}"
        self.logger.info(message)
    
    def debug(self, message: str, **kwargs):
        """Логировать отладочное сообщение"""
        if kwargs:
            message = f"{message} | {json.dumps(kwargs, default=str, ensure_ascii=False)}"
        self.logger.debug(message)
    
    def warning(self, message: str, **kwargs):
        """Логировать предупреждение"""
        if kwargs:
            message = f"{message} | {json.dumps(kwargs, default=str, ensure_ascii=False)}"
        self.logger.warning(message)
    
    def error(self, message: str, exc_info: bool = False, **kwargs):
        """Логировать ошибку с полным traceback"""
        if kwargs:
            message = f"{message} | {json.dumps(kwargs, default=str, ensure_ascii=False)}"
        
        if exc_info:
            # Получаем полный traceback
            exc_type, exc_value, exc_traceback = None, None, None
            try:
                import sys
                exc_type, exc_value, exc_traceback = sys.exc_info()
            except:
                pass
            
            if exc_type:
                full_traceback = ''.join(traceback.format_exception(exc_type, exc_value, exc_traceback))
                message = f"{message}\n\nПолный traceback:\n{full_traceback}"
        
        self.logger.error(message, exc_info=exc_info)
    
    def critical(self, message: str, exc_info: bool = False, **kwargs):
        """Логировать критическую ошибку"""
        if kwargs:
            message = f"{message} | {json.dumps(kwargs, default=str, ensure_ascii=False)}"
        
        if exc_info:
            exc_type, exc_value, exc_traceback = None, None, None
            try:
                import sys
                exc_type, exc_value, exc_traceback = sys.exc_info()
            except:
                pass
            
            if exc_type:
                full_traceback = ''.join(traceback.format_exception(exc_type, exc_value, exc_traceback))
                message = f"{message}\n\nПолный traceback:\n{full_traceback}"
        
        self.logger.critical(message, exc_info=exc_info)
    
    def log_task_start(self, **kwargs):
        """Логировать начало задачи"""
        self.info(f"=== ЗАДАЧА НАЧАТА: {self.task_name} ===", task_id=self.task_id, **kwargs)
    
    def log_task_end(self, success: bool = True, result: Optional[Any] = None, **kwargs):
        """Логировать завершение задачи"""
        status = "УСПЕШНО" if success else "С ОШИБКОЙ"
        self.info(f"=== ЗАДАЧА ЗАВЕРШЕНА: {self.task_name} - {status} ===", 
                 task_id=self.task_id, success=success, result=str(result) if result else None, **kwargs)
    
    def log_progress(self, current: int, total: int, **kwargs):
        """Логировать прогресс"""
        percent = int((current / total) * 100) if total > 0 else 0
        self.info(f"Прогресс: {current}/{total} ({percent}%)", 
                 current=current, total=total, percent=percent, **kwargs)
    
    def get_log_file_path(self) -> Optional[str]:
        """Получить путь к файлу лога"""
        return self.log_file


def get_task_logger(task_name: str, task_id: Optional[str] = None) -> TaskLogger:
    """Получить логгер для задачи"""
    return TaskLogger(task_name, task_id)

