import easyocr
import cv2
import numpy as np
from typing import List
from app.config import settings
import logging
import os
import sys

logger = logging.getLogger(__name__)

# КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Singleton для NumberRecognition
# EasyOCR должен быть singleton на процесс, иначе модель инициализируется каждый раз заново
_number_recognition_instance = None

def get_number_recognition():
    """Получить singleton экземпляр NumberRecognition"""
    global _number_recognition_instance
    pid = os.getpid()
    
    logger.info(f"get_number_recognition() CALLED PID={pid} instance_exists={_number_recognition_instance is not None}")
    
    if _number_recognition_instance is None:
        logger.info(f"get_number_recognition() - Creating new NumberRecognition instance PID={pid}")
        _number_recognition_instance = NumberRecognition()
        logger.info(f"get_number_recognition() - NumberRecognition instance created successfully PID={pid}")
    else:
        logger.info(f"get_number_recognition() - Returning existing instance PID={pid}")
    
    return _number_recognition_instance


class NumberRecognition:
    """Распознавание номеров с помощью EasyOCR"""
    
    def __init__(self):
        # КРИТИЧЕСКОЕ ЛОГИРОВАНИЕ: Логируем окружение для диагностики
        pid = os.getpid()
        cwd = os.getcwd()
        
        self.logger = logging.getLogger(__name__)
        logger.info(f"EASYOCR INIT PID={pid} CWD={cwd}")
        logger.info(f"EASYOCR INIT - Python path (first 3): {sys.path[:3]}")
        
        # Инициализируем EasyOCR с языками из настроек
        languages = settings.easyocr_languages_list
        logger.info(f"EASYOCR INIT - Initializing with languages: {languages}")
        self.logger.info(f"Initializing EasyOCR with languages: {languages}")
        
        try:
            # Используем параметры для улучшения распознавания номеров
            # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: EasyOCR не thread-safe, используем CPU и отключаем verbose
            self.reader = easyocr.Reader(
                languages,
                gpu=False,  # Используем CPU (можно включить GPU если доступен)
                verbose=False,  # Отключаем лишний вывод
                model_storage_directory=None,  # Используем дефолтную директорию
                download_enabled=True  # Разрешаем загрузку моделей если нужно
            )
            logger.info(f"EASYOCR INIT - SUCCESS: Model loaded and ready, PID={pid}")
            self.logger.info("EasyOCR initialized successfully")
        except Exception as e:
            logger.error(f"EASYOCR INIT - FAILED: {str(e)}")
            self.logger.error(f"Failed to initialize EasyOCR: {str(e)}", exc_info=True)
            raise
    
    def extract(self, image_path: str) -> List[str]:
        """
        Извлечь номера с изображения
        
        Returns: список найденных номеров
        """
        try:
            self.logger.info(f"Starting number extraction from: {image_path}")
            
            # Проверяем, что файл существует
            import os
            if not os.path.exists(image_path):
                self.logger.error(f"Image file not found: {image_path}")
                return []
            
            # Предобработка изображения для улучшения распознавания
            img = cv2.imread(image_path)
            if img is None:
                self.logger.error(f"Failed to load image: {image_path}")
                return []
            
            # Улучшаем изображение для лучшего распознавания
            # Конвертируем в grayscale если нужно
            if len(img.shape) == 3:
                gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
            else:
                gray = img
            
            # Применяем адаптивную бинаризацию для улучшения контраста
            # Это помогает распознавать номера на темном фоне
            # ВАЖНО: Используем правильные параметры для разных размеров изображений
            try:
                # Определяем размер блока для адаптивной бинаризации
                # Блок должен быть нечетным и достаточно большим
                block_size = 11
                if gray.shape[0] < 200 or gray.shape[1] < 200:
                    block_size = 7  # Для маленьких изображений используем меньший блок
                
                adaptive_thresh = cv2.adaptiveThreshold(
                    gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, block_size, 2
                )
            except Exception as e:
                self.logger.warning(f"Failed to apply adaptive threshold: {str(e)}, using original image")
                adaptive_thresh = gray
            
            # Сохраняем обработанное изображение во временный файл
            import tempfile
            temp_fd, temp_path = tempfile.mkstemp(suffix='.jpg')
            os.close(temp_fd)
            cv2.imwrite(temp_path, adaptive_thresh)
            
            try:
                # ВАЖНО: Добавляем таймаут для EasyOCR, чтобы избежать зависаний
                # EasyOCR может зависать на больших или сложных изображениях
                from concurrent.futures import ThreadPoolExecutor, TimeoutError as FuturesTimeoutError
                import time
                
                results_processed = []
                results_original = []
                
                # Устанавливаем таймаут 30 секунд для каждого вызова EasyOCR
                timeout_seconds = 30
                
                # Пробуем распознать на обработанном изображении с таймаутом
                try:
                    def read_processed():
                        return self.reader.readtext(temp_path, detail=1)
                    
                    with ThreadPoolExecutor(max_workers=1) as executor:
                        future = executor.submit(read_processed)
                        try:
                            results_processed = future.result(timeout=timeout_seconds)
                            self.logger.info(f"EasyOCR found {len(results_processed)} text regions in processed image")
                            if results_processed:
                                # Логируем первые 5 результатов для диагностики
                                sample_results = [(r[1] if len(r) > 1 else 'N/A', r[2] if len(r) > 2 else 'N/A') for r in results_processed[:5]]
                                self.logger.info(f"Processed image sample results: {sample_results}")
                        except FuturesTimeoutError:
                            self.logger.warning(f"EasyOCR timeout on processed image after {timeout_seconds}s, skipping")
                            future.cancel()
                            results_processed = []
                        except Exception as e:
                            self.logger.warning(f"Error in processed image OCR: {str(e)}")
                            results_processed = []
                except Exception as e:
                    self.logger.warning(f"Error reading processed image: {str(e)}, continuing with original")
                    results_processed = []
                
                # Пробуем на оригинальном изображении с таймаутом
                try:
                    def read_original():
                        return self.reader.readtext(image_path, detail=1)
                    
                    with ThreadPoolExecutor(max_workers=1) as executor:
                        future = executor.submit(read_original)
                        try:
                            results_original = future.result(timeout=timeout_seconds)
                            self.logger.info(f"EasyOCR found {len(results_original)} text regions in original image")
                            if results_original:
                                # Логируем первые 5 результатов для диагностики
                                sample_results = [(r[1] if len(r) > 1 else 'N/A', r[2] if len(r) > 2 else 'N/A') for r in results_original[:5]]
                                self.logger.info(f"Original image sample results: {sample_results}")
                            if results_original:
                                self.logger.info(f"Original image results: {[(r[1] if len(r) > 1 else 'N/A', r[2] if len(r) > 2 else 'N/A') for r in results_original[:5]]}")
                        except FuturesTimeoutError:
                            self.logger.warning(f"EasyOCR timeout on original image after {timeout_seconds}s, skipping")
                            future.cancel()
                            results_original = []
                        except Exception as e:
                            self.logger.warning(f"Error in original image OCR: {str(e)}")
                            results_original = []
                except Exception as e:
                    self.logger.warning(f"Error reading original image: {str(e)}, using processed results only")
                    results_original = []
                
                # Объединяем результаты
                all_results = results_processed + results_original
                
            finally:
                # Удаляем временный файл
                if os.path.exists(temp_path):
                    try:
                        os.unlink(temp_path)
                    except:
                        pass
            
            numbers = []
            seen_numbers = set()  # Для избежания дубликатов
            
            for result in all_results:
                if len(result) == 3:
                    bbox, text, confidence = result
                else:
                    # Если формат другой, пропускаем
                    continue
                
                # ВАЖНО: Логируем все найденные тексты для диагностики
                self.logger.info(f"Found text: '{text}', confidence: {confidence:.2f}")
                
                # Фильтруем только числа и буквы (номера обычно содержат цифры)
                cleaned_text = self._clean_number(text)
                self.logger.info(f"Cleaned text: '{cleaned_text}' (original: '{text}')")
                
                # ВАЖНО: Используем очень низкий порог confidence (0.15) для лучшего распознавания
                # но логируем низкую уверенность для отладки
                if cleaned_text and confidence > 0.15:
                    # Избегаем дубликатов
                    if cleaned_text not in seen_numbers:
                        numbers.append(cleaned_text)
                        seen_numbers.add(cleaned_text)
                        if confidence < 0.5:
                            self.logger.warning(f"Added number with low confidence: '{cleaned_text}' (confidence: {confidence:.2f})")
                        else:
                            self.logger.info(f"Added number: '{cleaned_text}' (confidence: {confidence:.2f})")
            
            self.logger.info(f"Number extraction completed. Found {len(numbers)} unique numbers: {numbers}")
            return numbers
            
        except Exception as e:
            self.logger.error(f"Error extracting numbers from {image_path}: {str(e)}", exc_info=True)
            return []
    
    def _clean_number(self, text: str) -> str:
        """
        Очистить и нормализовать номер - оставить ТОЛЬКО цифры
        
        ВАЖНО: Номера могут быть разной длины:
        - Короткие номера: 1-3 цифры (например, "123")
        - Средние номера: 4-6 цифр (например, "1234", "12345")
        - Длинные номера: 7-12 цифр (например, "12345678", "123456789012")
        """
        # Извлекаем только цифры
        digits_only = ''.join(c for c in text if c.isdigit())
        
        # Фильтруем слишком короткие (меньше 1 цифры) или слишком длинные (больше 12 цифр)
        # 12 цифр - разумный максимум для номеров (например, длинные серийные номера)
        if len(digits_only) < 1 or len(digits_only) > 12:
            return ""
        
        return digits_only


