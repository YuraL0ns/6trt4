from PIL import Image, ImageDraw, ImageFont
import os
from typing import Optional


class WatermarkProcessor:
    """Добавление водяного знака на изображения"""
    
    def __init__(self):
        # Прозрачность уменьшена на 15%: было 45%, стало 45% * 0.85 = 38.25%
        self.opacity = 0.3825  # ~38%
        # Шрифт уменьшен на 30%: было 7%, стало 7% * 0.7 = 4.9%
        self.font_size_ratio = 0.049  # ~5% от высоты изображения
        # Отступ увеличен на 30%: было 10%, стало 10% * 1.3 = 13%
        self.interval_ratio = 0.13  # 13% интервал
        self.color = (255, 255, 255)  # Белый цвет
    
    def add_watermark(
        self,
        image_path: str,
        text: str = "hunter-photo.ru",
        output_path: Optional[str] = None
    ) -> str:
        """
        Добавить водяной знак на изображение
        
        Параметры:
        - Прозрачность: 45%
        - Размер шрифта: 7% от высоты изображения (уменьшено на 30%)
        - Интервал: 10% от размера шрифта
        - Цвет: белый
        """
        if output_path is None:
            base, ext = os.path.splitext(image_path)
            output_path = f"{base}_watermarked.jpg"
        
        # Открываем изображение
        img = Image.open(image_path).convert("RGBA")
        width, height = img.size
        
        # Создаем слой для водяного знака
        watermark = Image.new("RGBA", img.size, (255, 255, 255, 0))
        draw = ImageDraw.Draw(watermark)
        
        # Вычисляем размер шрифта (7% от высоты, уменьшено на 30%)
        font_size = int(height * self.font_size_ratio)
        
        # Пытаемся загрузить шрифт
        try:
            font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", font_size)
        except:
            try:
                font = ImageFont.truetype("/System/Library/Fonts/Helvetica.ttc", font_size)
            except:
                font = ImageFont.load_default()
        
        # Получаем размер текста
        bbox = draw.textbbox((0, 0), text, font=font)
        text_width = bbox[2] - bbox[0]
        text_height = bbox[3] - bbox[1]
        
        # Интервал между водяными знаками (13% от размера шрифта, увеличен на 30%)
        interval = int(font_size * self.interval_ratio)
        
        # Рисуем водяной знак по всей поверхности
        # Текст повторяется с места остановки в строке (не как забор)
        y = 0
        x_offset = 0  # Смещение для продолжения текста в следующей строке
        
        while y < height:
            x = -x_offset  # Начинаем с отрицательного смещения для продолжения текста
            while x < width:
                # Рисуем текст с прозрачностью
                draw.text(
                    (x, y),
                    text,
                    font=font,
                    fill=(*self.color, int(255 * self.opacity))
                )
                x += text_width + interval
            
            # Вычисляем смещение для следующей строки (продолжение текста)
            # Берем остаток от деления ширины на (text_width + interval)
            remaining_width = (width + x_offset) % (text_width + interval)
            x_offset = (text_width + interval) - remaining_width if remaining_width > 0 else 0
            
            y += text_height + interval
        
        # Накладываем водяной знак на изображение
        watermarked = Image.alpha_composite(img, watermark)
        
        # Конвертируем обратно в RGB
        watermarked = watermarked.convert("RGB")
        
        # Определяем формат по расширению файла
        ext = os.path.splitext(output_path)[1].lower()
        if ext == '.webp':
            # Сохраняем в WebP формате
            watermarked.save(output_path, "WEBP", quality=85)
        else:
            # Сохраняем в JPEG формате
            watermarked.save(output_path, "JPEG", quality=95)
        
        return output_path


