from PIL import Image, ImageOps
from PIL.ExifTags import TAGS
from datetime import datetime
from typing import Optional, Dict
import logging

logger = logging.getLogger(__name__)


class EXIFProcessor:
    """Обработка EXIF данных"""
    
    def extract_exif(self, image_path: str) -> Optional[Dict]:
        """Извлечь EXIF данные из изображения"""
        try:
            img = Image.open(image_path)
            exif_data = img._getexif()
            
            if not exif_data:
                return None
            
            exif_dict = {}
            for tag_id, value in exif_data.items():
                tag = TAGS.get(tag_id, tag_id)
                exif_dict[tag] = value
            
            # Извлекаем дату
            datetime_str = None
            if 'DateTime' in exif_dict:
                datetime_str = exif_dict['DateTime']
            elif 'DateTimeOriginal' in exif_dict:
                datetime_str = exif_dict['DateTimeOriginal']
            elif 'DateTimeDigitized' in exif_dict:
                datetime_str = exif_dict['DateTimeDigitized']
            
            result = {
                'datetime': datetime_str,
                'camera': exif_dict.get('Make', '') + ' ' + exif_dict.get('Model', ''),
                'iso': exif_dict.get('ISOSpeedRatings'),
                'focal_length': exif_dict.get('FocalLength'),
                'aperture': exif_dict.get('FNumber'),
                'shutter_speed': exif_dict.get('ExposureTime'),
            }
            
            return result
            
        except Exception as e:
            print(f"Error extracting EXIF: {str(e)}")
            return None
    
    def parse_datetime(self, datetime_str: str) -> Optional[datetime]:
        """Парсинг строки даты из EXIF с поддержкой различных форматов"""
        if not datetime_str:
            return None
            
        # Убираем лишние пробелы
        datetime_str = datetime_str.strip()
        
        # Список возможных форматов EXIF
        formats = [
            "%Y:%m:%d %H:%M:%S",  # Стандартный формат: "2024:01:15 12:30:45"
            "%Y-%m-%d %H:%M:%S",  # Альтернативный: "2024-01-15 12:30:45"
            "%Y/%m/%d %H:%M:%S",  # Слэши: "2024/01/15 12:30:45"
            "%Y:%m:%d",           # Только дата: "2024:01:15"
            "%Y-%m-%d",           # Только дата с дефисами: "2024-01-15"
            "%Y/%m/%d",           # Только дата со слэшами: "2024/01/15"
        ]
        
        # Пробуем распарсить каждый формат
        for fmt in formats:
            try:
                parsed = datetime.strptime(datetime_str, fmt)
                # Если формат был только с датой, добавляем время 00:00:00
                if fmt in ["%Y:%m:%d", "%Y-%m-%d", "%Y/%m/%d"]:
                    parsed = parsed.replace(hour=0, minute=0, second=0)
                return parsed
            except ValueError:
                continue
        
        # Если стандартные форматы не подошли, пробуем более гибкий парсинг
        try:
            # Пробуем найти дату и время в строке
            import re
            # Ищем паттерн даты: YYYY:MM:DD или YYYY-MM-DD или YYYY/MM/DD
            date_match = re.search(r'(\d{4})[:/-](\d{1,2})[:/-](\d{1,2})', datetime_str)
            if date_match:
                year = int(date_match.group(1))
                month = int(date_match.group(2))
                day = int(date_match.group(3))
                
                # Ищем паттерн времени: HH:MM:SS или HH:MM
                time_match = re.search(r'(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?', datetime_str)
                if time_match:
                    hour = int(time_match.group(1))
                    minute = int(time_match.group(2))
                    second = int(time_match.group(3)) if time_match.group(3) else 0
                else:
                    # Если время не найдено, используем 00:00:00
                    hour = minute = second = 0
                
                return datetime(year, month, day, hour, minute, second)
        except Exception as e:
            print(f"Error parsing datetime with regex: {str(e)}")
        
        return None
    
    def normalize_orientation(self, image_path: str) -> None:
        """
        КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Повернуть изображение согласно EXIF и УДАЛИТЬ EXIF Orientation
        Перезаписывает файл на месте
        
        Это ключевая функция всего проекта - EXIF применяется ОДИН РАЗ в начале пайплайна,
        после этого EXIF удаляется навсегда, и дальше ВСЁ работает с "чистым" изображением.
        
        Args:
            image_path: Путь к изображению (будет перезаписан)
        """
        try:
            logger.info(f"Normalizing EXIF orientation for: {image_path}")
            img = Image.open(image_path)
            original_size = img.size
            
            # Применяем ориентацию согласно EXIF
            img = ImageOps.exif_transpose(img)
            new_size = img.size
            
            if original_size != new_size:
                logger.info(f"EXIF orientation applied: {original_size} -> {new_size}")
            else:
                logger.debug("No EXIF orientation change needed (image already in correct orientation)")
            
            # Конвертируем в RGB (важно для OpenCV / JPEG)
            if img.mode != "RGB":
                img = img.convert("RGB")
            
            # Сохраняем БЕЗ EXIF (перезаписываем файл)
            img.save(image_path, "JPEG", quality=95)
            logger.info(f"Image normalized and saved without EXIF: {image_path}")
            
        except Exception as e:
            logger.error(f"Error normalizing EXIF orientation for {image_path}: {str(e)}", exc_info=True)
            raise


