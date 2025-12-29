from PIL import Image, ImageOps
import os
from typing import Optional
import logging

# Получаем тег ориентации из ExifTags
try:
    from PIL import ExifTags
    # Ищем тег ориентации в ExifTags.TAGS
    ORIENTATION_TAG = None
    for tag_id, tag_name in ExifTags.TAGS.items():
        if tag_name == 'Orientation':
            ORIENTATION_TAG = tag_id
            break
    # Если не нашли, используем стандартный тег 274 (0x0112)
    if ORIENTATION_TAG is None:
        ORIENTATION_TAG = 274
except ImportError:
    # Fallback на стандартный тег ориентации
    ORIENTATION_TAG = 274


class ImageProcessor:
    """Обработка изображений: удаление EXIF, конвертация в WebP"""
    
    def remove_to_exif_and_rotate(self, image_path: str, output_path: Optional[str] = None) -> str:
        """
        Удалить EXIF данные из изображения и применить ориентацию из EXIF
        
        ВАЖНО: EXIF ориентация уже правильно обрабатывает все случаи поворота,
        дополнительный поворот не требуется.
        
        Returns: путь к обработанному изображению
        """
        import logging
        logger = logging.getLogger(__name__)
        
        if output_path is None:
            base, ext = os.path.splitext(image_path)
            output_path = f"{base}_no_exif.jpg"
        
        # Открываем изображение
        img = Image.open(image_path)
        
        # ВАЖНО: Применяем EXIF ориентацию перед удалением EXIF
        # Сначала пытаемся получить EXIF данные для логирования и проверки
        original_size = img.size
        orientation_applied = False
        
        try:
            # Пытаемся получить EXIF через getexif() (новый метод PIL)
            exif = img.getexif()
            if exif:
                # Получаем ориентацию используя найденный тег
                orientation = exif.get(ORIENTATION_TAG) or exif.get(274) or exif.get(0x0112)
                if orientation and orientation != 1:  # 1 = нормальная ориентация
                    logger.info(f"Image has EXIF orientation tag: {orientation}, size before: {original_size}")
                else:
                    logger.debug("Image has EXIF but orientation is normal (1) or not set")
            else:
                logger.debug("Image has no EXIF data")
        except Exception as e:
            logger.debug(f"Could not read EXIF via getexif(): {str(e)}, trying _getexif()")
            try:
                # Пробуем старый метод для совместимости
                exif = img._getexif()
                if exif:
                    orientation = exif.get(ORIENTATION_TAG) or exif.get(274) or exif.get(0x0112)
                    if orientation and orientation != 1:
                        logger.info(f"Image has EXIF orientation (via _getexif): {orientation}")
            except Exception:
                pass
        
        # Применяем EXIF ориентацию используя ImageOps.exif_transpose()
        # Это правильный способ применения EXIF ориентации в PIL
        logger.info(f"Applying EXIF orientation: original_size={original_size}")
        
        # ВАЖНО: Сначала проверяем, есть ли EXIF ориентация, чтобы понять, нужно ли поворачивать
        exif_orientation = None
        try:
            exif = img.getexif()
            if exif:
                exif_orientation = exif.get(ORIENTATION_TAG) or exif.get(274) or exif.get(0x0112)
                if exif_orientation:
                    logger.info(f"Found EXIF orientation tag: {exif_orientation} (1=normal, 3=180°, 6=270°CW, 8=90°CCW)")
        except:
            try:
                exif = img._getexif()
                if exif:
                    exif_orientation = exif.get(ORIENTATION_TAG) or exif.get(274) or exif.get(0x0112)
                    if exif_orientation:
                        logger.info(f"Found EXIF orientation tag (via _getexif): {exif_orientation}")
            except:
                pass
        
        # ВАЖНО: Применяем EXIF ориентацию ТОЛЬКО ОДИН РАЗ используя ImageOps.exif_transpose()
        # Это стандартный и надежный способ применения EXIF ориентации в PIL
        # Не применяем ориентацию вручную, чтобы избежать двойного поворота
        try:
            img_before = img.copy()
            img = ImageOps.exif_transpose(img)
            new_size = img.size
            if original_size != new_size:
                logger.info(f"EXIF orientation applied via exif_transpose: {original_size} -> {new_size}")
                orientation_applied = True
            else:
                logger.debug("No EXIF orientation change needed (image already in correct orientation)")
        except (AttributeError, Exception) as e:
            logger.warning(f"Could not apply EXIF orientation via exif_transpose: {str(e)}")
            # Если exif_transpose не работает, пробуем применить вручную только если есть EXIF ориентация
        if exif_orientation and exif_orientation != 1:
                logger.info(f"Trying manual EXIF orientation as fallback: {exif_orientation}")
            try:
                # Используем старые константы для совместимости с разными версиями PIL
                try:
                    FLIP_LEFT_RIGHT = Image.Transpose.FLIP_LEFT_RIGHT
                    FLIP_TOP_BOTTOM = Image.Transpose.FLIP_TOP_BOTTOM
                except AttributeError:
                    # Старые версии PIL используют другие константы
                    FLIP_LEFT_RIGHT = Image.FLIP_LEFT_RIGHT
                    FLIP_TOP_BOTTOM = Image.FLIP_TOP_BOTTOM
                
                if exif_orientation == 2:
                    img = img.transpose(FLIP_LEFT_RIGHT)
                    logger.info(f"Applied FLIP_LEFT_RIGHT (orientation 2)")
                elif exif_orientation == 3:
                    img = img.rotate(180, expand=True)
                    logger.info(f"Applied 180° rotation (orientation 3)")
                elif exif_orientation == 4:
                    img = img.transpose(FLIP_TOP_BOTTOM)
                    logger.info(f"Applied FLIP_TOP_BOTTOM (orientation 4)")
                elif exif_orientation == 5:
                    img = img.transpose(FLIP_LEFT_RIGHT).rotate(90, expand=True)
                    logger.info(f"Applied FLIP_LEFT_RIGHT + 90° (orientation 5)")
                elif exif_orientation == 6:
                    img = img.rotate(-90, expand=True)
                    logger.info(f"Applied -90° rotation (orientation 6)")
                elif exif_orientation == 7:
                    img = img.transpose(FLIP_LEFT_RIGHT).rotate(-90, expand=True)
                    logger.info(f"Applied FLIP_LEFT_RIGHT + -90° (orientation 7)")
                elif exif_orientation == 8:
                    img = img.rotate(90, expand=True)
                    logger.info(f"Applied 90° rotation (orientation 8)")
                
                new_size = img.size
                orientation_applied = True
                logger.info(f"Manual EXIF orientation applied successfully: {original_size} -> {new_size}")
            except Exception as manual_error:
                    logger.error(f"Manual orientation also failed: {str(manual_error)}")
        
        # Создаем новое изображение без EXIF
        # Используем convert('RGB') для гарантии правильного формата
        if img.mode != 'RGB':
            img = img.convert('RGB')
        
        # ВАЖНО: EXIF ориентация уже применена выше, дополнительный поворот не требуется
        # Если изображение все еще отображается неправильно, проблема может быть в другом месте
        # (например, в CSS или в том, как изображение отображается на фронтенде)
        
        # Сохраняем без EXIF данных
        # ВАЖНО: Не используем exif=None, так как это вызывает ошибку в PIL
        # (PIL пытается проверить len(exif), но exif=None вызывает TypeError)
        # Вместо этого не передаем параметр exif вообще - PIL автоматически не включит EXIF
        # для нового изображения, созданного через convert() или ImageOps.exif_transpose()
        try:
            # Сохраняем без параметра exif (самый безопасный способ)
            # При сохранении нового изображения без EXIF данных, PIL не включит EXIF метаданные
            img.save(output_path, "JPEG", quality=95)
            logger.info(f"Image EXIF orientation applied and saved (EXIF removed): {output_path}")
        except Exception as e:
            logger.warning(f"Failed to save without exif parameter: {str(e)}, trying alternative method")
            # Если не сработало, пробуем создать новое изображение и скопировать данные
            # ВАЖНО: Image уже импортирован в начале файла, не импортируем снова
            try:
                # Создаем новое изображение без EXIF (используем глобальный Image)
                img_copy = Image.new('RGB', img.size)
                img_copy.paste(img)
                img_copy.save(output_path, "JPEG", quality=95)
                logger.info(f"Image EXIF orientation applied and saved (EXIF removed, via copy): {output_path}")
            except Exception as e2:
                logger.error(f"Failed to save image: {str(e2)}", exc_info=True)
                raise
        
        return output_path
    
    def remove_exif(self, image_path: str, output_path: Optional[str] = None) -> str:
        """
        Удалить EXIF данные из изображения, но сохранить ориентацию
        DEPRECATED: Используйте remove_to_exif_and_rotate вместо этого
        
        Returns: путь к обработанному изображению
        """
        # Вызываем новую функцию для обратной совместимости
        return self.remove_to_exif_and_rotate(image_path, output_path)
    
    def convert_to_webp(self, image_path: str, quality: int = 85) -> str:
        """Конвертировать изображение в WebP"""
        base, ext = os.path.splitext(image_path)
        output_path = f"{base}.webp"
        
        img = Image.open(image_path)
        img.save(output_path, "WEBP", quality=quality)
        
        return output_path
    
    def compress_image(self, image_path: str, max_size_mb: float = 5.0) -> str:
        """Сжать изображение до указанного размера"""
        file_size = os.path.getsize(image_path) / (1024 * 1024)  # MB
        
        if file_size <= max_size_mb:
            return image_path
        
        img = Image.open(image_path)
        quality = 95
        
        while file_size > max_size_mb and quality > 50:
            base, ext = os.path.splitext(image_path)
            output_path = f"{base}_compressed.jpg"
            img.save(output_path, "JPEG", quality=quality, optimize=True)
            file_size = os.path.getsize(output_path) / (1024 * 1024)
            quality -= 5
        
        return output_path


