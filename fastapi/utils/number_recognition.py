import easyocr
import cv2
import numpy as np
from typing import List
from app.config import settings
import logging
import os
import sys
import itertools

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
    
    def _preprocess_image(self, img: np.ndarray) -> List[tuple]:
        """
        Предобработка изображения для улучшения распознавания номеров
        Возвращает список кортежей (обработанное_изображение, описание_метода)
        """
        processed_images = []
        
        # Конвертируем в grayscale если нужно
        if len(img.shape) == 3:
            gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        else:
            gray = img.copy()
        
        # Метод 1: Оригинальное изображение (увеличенное для лучшего распознавания)
        h, w = gray.shape
        if h < 1000 or w < 1000:
            # Увеличиваем маленькие изображения для лучшего распознавания
            scale = max(1000 / h, 1000 / w)
            new_h, new_w = int(h * scale), int(w * scale)
            gray_large = cv2.resize(gray, (new_w, new_h), interpolation=cv2.INTER_CUBIC)
            processed_images.append((gray_large, "original_large"))
        else:
            processed_images.append((gray, "original"))
        
        # Метод 2: Адаптивная бинаризация
        try:
            block_size = 11
            if gray.shape[0] < 200 or gray.shape[1] < 200:
                block_size = 7
            adaptive_thresh = cv2.adaptiveThreshold(
                gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, block_size, 2
            )
            if adaptive_thresh.shape[0] < 1000 or adaptive_thresh.shape[1] < 1000:
                scale = max(1000 / adaptive_thresh.shape[0], 1000 / adaptive_thresh.shape[1])
                new_h, new_w = int(adaptive_thresh.shape[0] * scale), int(adaptive_thresh.shape[1] * scale)
                adaptive_thresh = cv2.resize(adaptive_thresh, (new_w, new_h), interpolation=cv2.INTER_CUBIC)
            processed_images.append((adaptive_thresh, "adaptive_thresh"))
        except Exception as e:
            self.logger.warning(f"Failed to apply adaptive threshold: {str(e)}")
        
        # Метод 3: Улучшение контраста через CLAHE (Contrast Limited Adaptive Histogram Equalization)
        try:
            clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
            clahe_img = clahe.apply(gray)
            if clahe_img.shape[0] < 1000 or clahe_img.shape[1] < 1000:
                scale = max(1000 / clahe_img.shape[0], 1000 / clahe_img.shape[1])
                new_h, new_w = int(clahe_img.shape[0] * scale), int(clahe_img.shape[1] * scale)
                clahe_img = cv2.resize(clahe_img, (new_w, new_h), interpolation=cv2.INTER_CUBIC)
            processed_images.append((clahe_img, "clahe"))
        except Exception as e:
            self.logger.warning(f"Failed to apply CLAHE: {str(e)}")
        
        # Метод 4: Морфологические операции для улучшения текста
        try:
            # Создаем ядро для морфологических операций
            kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))
            # Применяем закрытие (dilation + erosion) для соединения разорванных символов
            morph = cv2.morphologyEx(gray, cv2.MORPH_CLOSE, kernel)
            # Улучшаем контраст
            morph = cv2.convertScaleAbs(morph, alpha=1.5, beta=30)
            if morph.shape[0] < 1000 or morph.shape[1] < 1000:
                scale = max(1000 / morph.shape[0], 1000 / morph.shape[1])
                new_h, new_w = int(morph.shape[0] * scale), int(morph.shape[1] * scale)
                morph = cv2.resize(morph, (new_w, new_h), interpolation=cv2.INTER_CUBIC)
            processed_images.append((morph, "morphology"))
        except Exception as e:
            self.logger.warning(f"Failed to apply morphology: {str(e)}")
        
        # Метод 5: Увеличение резкости
        try:
            kernel_sharpen = np.array([[-1, -1, -1],
                                      [-1,  9, -1],
                                      [-1, -1, -1]])
            sharpened = cv2.filter2D(gray, -1, kernel_sharpen)
            if sharpened.shape[0] < 1000 or sharpened.shape[1] < 1000:
                scale = max(1000 / sharpened.shape[0], 1000 / sharpened.shape[1])
                new_h, new_w = int(sharpened.shape[0] * scale), int(sharpened.shape[1] * scale)
                sharpened = cv2.resize(sharpened, (new_w, new_h), interpolation=cv2.INTER_CUBIC)
            processed_images.append((sharpened, "sharpened"))
        except Exception as e:
            self.logger.warning(f"Failed to apply sharpening: {str(e)}")
        
        return processed_images
    
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
            
            # Загружаем изображение
            img = cv2.imread(image_path)
            if img is None:
                self.logger.error(f"Failed to load image: {image_path}")
                return []
            
            # Предобрабатываем изображение несколькими методами
            processed_images = self._preprocess_image(img)
            self.logger.info(f"Created {len(processed_images)} preprocessed image variants")
            
            # Сохраняем обработанные изображения во временные файлы
            import tempfile
            temp_files = []
            try:
                for processed_img, method_name in processed_images:
                    temp_fd, temp_path = tempfile.mkstemp(suffix='.jpg', prefix=f'ocr_{method_name}_')
                    os.close(temp_fd)
                    cv2.imwrite(temp_path, processed_img)
                    temp_files.append((temp_path, method_name))
                    self.logger.debug(f"Saved preprocessed image: {temp_path} (method: {method_name})")
            
                # Распознаем текст на всех обработанных изображениях
                from concurrent.futures import ThreadPoolExecutor, TimeoutError as FuturesTimeoutError
                timeout_seconds = 20  # Уменьшили таймаут для каждого варианта
                all_results = []
                
                for temp_path, method_name in temp_files:
                    try:
                        def read_text():
                            return self.reader.readtext(temp_path, detail=1, paragraph=False)
                        
                        with ThreadPoolExecutor(max_workers=1) as executor:
                            future = executor.submit(read_text)
                            try:
                                results = future.result(timeout=timeout_seconds)
                                if results:
                                    self.logger.info(f"EasyOCR found {len(results)} text regions using {method_name}")
                                    # Добавляем информацию о методе к каждому результату
                                    for result in results:
                                        if len(result) >= 3:
                                            all_results.append((*result, method_name))
                            except FuturesTimeoutError:
                                self.logger.warning(f"EasyOCR timeout on {method_name} after {timeout_seconds}s, skipping")
                                future.cancel()
                            except Exception as e:
                                self.logger.warning(f"Error in OCR for {method_name}: {str(e)}")
                    except Exception as e:
                        self.logger.warning(f"Error processing {method_name}: {str(e)}")
                
                self.logger.info(f"Total text regions found across all methods: {len(all_results)}")
                
            finally:
                # Удаляем все временные файлы
                for temp_path, _ in temp_files:
                    if os.path.exists(temp_path):
                        try:
                            os.unlink(temp_path)
                        except:
                            pass
            
            # Фильтруем и обрабатываем результаты
            numbers = []
            seen_numbers = set()  # Для избежания дубликатов
            number_scores = {}  # Храним лучший confidence для каждого номера
            
            for result in all_results:
                # Результат может быть (bbox, text, confidence) или (bbox, text, confidence, method_name)
                if len(result) >= 3:
                    bbox, text, confidence = result[:3]
                    method_name = result[3] if len(result) > 3 else "unknown"
                else:
                    continue
                
                # Фильтруем только числа
                cleaned_text = self._clean_number(text)
                
                # КРИТИЧЕСКОЕ УЛУЧШЕНИЕ: Повышаем порог confidence и фильтруем короткие номера
                # Минимум 2 цифры для номера (исключаем одиночные цифры-мусор)
                if cleaned_text and len(cleaned_text) >= 2 and confidence > 0.4:
                    # Используем лучший confidence для каждого номера
                    if cleaned_text not in number_scores or confidence > number_scores[cleaned_text]:
                        number_scores[cleaned_text] = confidence
                    
                    # Добавляем только если confidence достаточно высокий или номер достаточно длинный
                    if cleaned_text not in seen_numbers:
                        # Для коротких номеров (2-3 цифры) требуем высокий confidence
                        # Для длинных номеров (4+ цифры) допускаем средний confidence
                        min_confidence = 0.6 if len(cleaned_text) <= 3 else 0.4
                        
                        if confidence >= min_confidence:
                            numbers.append(cleaned_text)
                            seen_numbers.add(cleaned_text)
                            self.logger.info(f"Added number: '{cleaned_text}' (confidence: {confidence:.2f}, method: {method_name}, length: {len(cleaned_text)})")
                        else:
                            self.logger.debug(f"Skipped number '{cleaned_text}' (confidence: {confidence:.2f} < {min_confidence:.2f})")
            
            # Сортируем по confidence (лучшие первыми)
            numbers = sorted(set(numbers), key=lambda x: number_scores.get(x, 0), reverse=True)
            
            # КРИТИЧЕСКОЕ УЛУЧШЕНИЕ: Дополнительная фильтрация очевидно неправильных номеров
            filtered_numbers = []
            for num in numbers:
                # Исключаем номера, которые выглядят как случайные последовательности
                # Проверяем на повторяющиеся цифры (например, "1111", "0000")
                if len(num) >= 4 and len(set(num)) == 1:
                    self.logger.debug(f"Filtered out repetitive number: '{num}'")
                    continue
                
                # Проверяем на слишком много одинаковых цифр подряд
                max_repeat = max(len(list(g)) for _, g in itertools.groupby(num))
                if max_repeat > len(num) * 0.7:  # Если более 70% цифр одинаковые
                    self.logger.debug(f"Filtered out number with too many repeats: '{num}'")
                    continue
                
                filtered_numbers.append(num)
            
            self.logger.info(f"Number extraction completed. Found {len(filtered_numbers)} unique numbers (from {len(all_results)} text regions): {filtered_numbers}")
            return filtered_numbers
            
        except Exception as e:
            self.logger.error(f"Error extracting numbers from {image_path}: {str(e)}", exc_info=True)
            return []
    
    def _clean_number(self, text: str) -> str:
        """
        Очистить и нормализовать номер - оставить ТОЛЬКО цифры
        
        КРИТИЧЕСКОЕ УЛУЧШЕНИЕ: Улучшенная фильтрация номеров
        - Минимум 2 цифры (исключаем одиночные цифры-мусор)
        - Максимум 12 цифр (разумный максимум для номеров)
        - Удаляем все нецифровые символы
        """
        if not text:
            return ""
        
        # Извлекаем только цифры
        digits_only = ''.join(c for c in text if c.isdigit())
        
        # КРИТИЧЕСКОЕ УЛУЧШЕНИЕ: Минимум 2 цифры (исключаем одиночные цифры)
        # Одиночные цифры часто являются мусором от OCR
        if len(digits_only) < 2 or len(digits_only) > 12:
            return ""
        
        return digits_only


