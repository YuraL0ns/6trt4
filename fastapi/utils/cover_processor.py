from PIL import Image, ImageDraw, ImageFont, ImageEnhance
import os
from typing import Optional, Tuple
import logging

logger = logging.getLogger(__name__)


class CoverProcessor:
    """Обработка обложки события: затемнение, добавление текста и логотипа"""
    
    def __init__(self):
        self.darkness_level = 40  # Уровень затемнения (40% - было 30%, добавлено еще 10%)
        self.font_size_ratio = 0.05  # 5% от высоты изображения
        self.logo_size_ratio = 0.10  # 10% от размера изображения
        
    def process_cover(
        self,
        image_path: str,
        title: str,
        city: str,
        date: str,
        logo_path: Optional[str] = None,
        output_path: Optional[str] = None
    ) -> str:
        """
        Обработать обложку события:
        1. Затемнить изображение на 30%
        2. Добавить текст: название события, город, дата
        3. Добавить логотип (если есть)
        
        Args:
            image_path: Путь к исходному изображению
            title: Название события
            city: Город проведения
            date: Дата проведения (формат: d.m.Y)
            logo_path: Путь к логотипу (опционально)
            output_path: Путь для сохранения (если None, перезаписывает исходный)
        
        Returns:
            str: Путь к обработанному изображению
        """
        logger.info(f"Processing cover: {image_path}")
        
        if not os.path.exists(image_path):
            raise FileNotFoundError(f"Cover image not found: {image_path}")
        
        if output_path is None:
            output_path = image_path
        
        # Создаем директорию для выходного файла если её нет
        output_dir = os.path.dirname(output_path)
        if output_dir and not os.path.exists(output_dir):
            os.makedirs(output_dir, mode=0o755, exist_ok=True)
            logger.info(f"Created directory: {output_dir}")
        
        try:
            # Открываем изображение
            img = Image.open(image_path).convert("RGB")
            width, height = img.size
            
            logger.debug(f"Image size: {width}x{height}")
            
            # 1. Затемнение изображения на 40% (было 30%, добавлено еще 10%)
            enhancer = ImageEnhance.Brightness(img)
            img = enhancer.enhance(1.0 - (self.darkness_level / 100))
            
            # 2. Подготовка текста
            text = f"{title}\n{city}, {date}"
            
            # Размер шрифта (5% от высоты изображения, минимум 24px)
            font_size = max(24, int(height * self.font_size_ratio))
            
            # Пытаемся найти системный шрифт
            font_path = self._find_system_font()
            
            # Создаем объект для рисования
            draw = ImageDraw.Draw(img)
            
            if font_path and os.path.exists(font_path):
                try:
                    font = ImageFont.truetype(font_path, font_size)
                    logger.debug(f"Using font: {font_path}")
                except Exception as e:
                    logger.warning(f"Failed to load font {font_path}: {e}, using default")
                    font = ImageFont.load_default()
            else:
                logger.debug("Using default font")
                font = ImageFont.load_default()
            
            # Получаем размер текста для центрирования
            bbox = draw.textbbox((0, 0), text, font=font)
            text_width = bbox[2] - bbox[0]
            text_height = bbox[3] - bbox[1]
            
            # Позиция текста (центр изображения)
            text_x = (width - text_width) // 2
            text_y = (height - text_height) // 2
            
            # Рисуем текст белым цветом
            draw.text(
                (text_x, text_y),
                text,
                font=font,
                fill=(255, 255, 255),
                align="center"
            )
            
            logger.debug(f"Text added: {text}")
            
            # 3. Наложение логотипа (если есть)
            if logo_path and os.path.exists(logo_path):
                try:
                    logo = Image.open(logo_path).convert("RGBA")
                    # Масштабируем логотип до 10% от размера изображения
                    logo_size = int(min(width, height) * self.logo_size_ratio)
                    logo.thumbnail((logo_size, logo_size), Image.Resampling.LANCZOS)
                    
                    # Размещаем в правом нижнем углу с отступом 10px
                    logo_x = width - logo_size - 10
                    logo_y = height - logo_size - 10
                    
                    # Создаем копию изображения для наложения логотипа
                    img_rgba = img.convert("RGBA")
                    img_rgba.paste(logo, (logo_x, logo_y), logo)
                    img = img_rgba.convert("RGB")
                    
                    logger.debug(f"Logo added at position ({logo_x}, {logo_y})")
                except Exception as e:
                    logger.warning(f"Failed to add logo: {e}")
            
            # Сохраняем обработанное изображение
            img.save(output_path, "JPEG", quality=95)
            
            # Устанавливаем права доступа
            os.chmod(output_path, 0o644)
            
            logger.info(f"Cover processed successfully: {output_path}")
            
            return output_path
            
        except Exception as e:
            logger.error(f"Error processing cover: {e}", exc_info=True)
            raise
    
    def _find_system_font(self) -> Optional[str]:
        """Найти системный шрифт"""
        possible_paths = [
            "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
            "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
            "/System/Library/Fonts/Helvetica.ttc",
            "/Windows/Fonts/arial.ttf",
        ]
        
        for path in possible_paths:
            if os.path.exists(path):
                return path
        
        return None

