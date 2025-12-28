import insightface
import numpy as np
import cv2
import os
import pickle
import tempfile
from typing import List, Optional, Tuple, Dict
from app.config import settings
from PIL import Image, ImageOps


class FaceRecognition:
    """Распознавание лиц с помощью InsightFace"""
    
    def __init__(self):
        import logging
        logger = logging.getLogger(__name__)
        
        # Загружаем модель InsightFace - используем более легковесную модель antelopev2
        # antelopev2 легче чем buffalo_l, но все еще хорошо распознает лица
        # Альтернативы: 'buffalo_s' (еще легче, но менее точная) или 'antelopev2' (баланс)
        model_path = settings.INSIGHTFACE_MODEL_PATH or './models'
        
        # Проверяем, что директория существует
        if not os.path.exists(model_path):
            os.makedirs(model_path, exist_ok=True)
            logger.info(f"Created model directory: {model_path}")
        
        # Список моделей для попытки загрузки (от легкой к тяжелой)
        models_to_try = ['buffalo_s', 'antelopev2', 'buffalo_l']
        self.model = None
        last_error = None
        
        for model_name in models_to_try:
            try:
                logger.info(f"Attempting to load InsightFace model: {model_name}")
                self.model = insightface.app.FaceAnalysis(
                    name=model_name,
                    root=model_path
                )
                # ВАЖНО: Увеличиваем размер детекции для лучшего распознавания лиц
                # det_size=(640, 640) - базовый размер, можно увеличить до (1280, 1280) для больших изображений
                # Но это увеличит время обработки, поэтому используем баланс
                self.model.prepare(ctx_id=0, det_size=(640, 640))
                logger.info(f"FaceRecognition initialized successfully with {model_name} model")
                break
            except AssertionError as e:
                last_error = e
                logger.warning(f"Failed to load {model_name} model (AssertionError): {str(e)}")
                # Продолжаем попытки с другими моделями
                continue
            except Exception as e:
                last_error = e
                logger.warning(f"Failed to load {model_name} model: {str(e)}")
                # Продолжаем попытки с другими моделями
                continue
        
        # Если ни одна модель не загрузилась, выбрасываем исключение
        if self.model is None:
            error_msg = f"Failed to load any InsightFace model. Last error: {str(last_error)}"
            logger.error(error_msg)
            raise RuntimeError(error_msg)
    
    def _apply_exif_orientation(self, image_path: str) -> Optional[str]:
        """
        Применить EXIF ориентацию к изображению и сохранить во временный файл
        
        Returns: путь к обработанному изображению или None если ошибка
        """
        try:
            img = Image.open(image_path)
            # Применяем EXIF ориентацию
            img = ImageOps.exif_transpose(img)
            
            # Сохраняем во временный файл
            temp_fd, temp_path = tempfile.mkstemp(suffix='.jpg')
            os.close(temp_fd)
            
            # Конвертируем в RGB если нужно
            if img.mode != 'RGB':
                img = img.convert('RGB')
            
            img.save(temp_path, "JPEG", quality=95)
            return temp_path
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f"Failed to apply EXIF orientation to {image_path}: {str(e)}")
            return None
    
    def extract_embedding(self, image_path: str, apply_exif: bool = True) -> Optional[np.ndarray]:
        """
        Извлечь embedding одного лица (первое найденное)
        
        Args:
            image_path: Путь к изображению
            apply_exif: Применить EXIF ориентацию перед обработкой (по умолчанию True)
                       ВАЖНО: При поиске используйте apply_exif=True, чтобы соответствовать
                       ориентации изображений при индексации (если они были повернуты через remove_exif)
        """
        embeddings = self.extract_all_embeddings(image_path, apply_exif=apply_exif)
        if embeddings and len(embeddings) > 0:
            # Возвращаем первое лицо с правильным типом
            embedding = embeddings[0]
            if not isinstance(embedding, np.ndarray):
                embedding = np.array(embedding, dtype=np.float32)
            return embedding.astype("float32")
        return None
    
    def extract_all_embeddings(self, image_path: str, apply_exif: bool = True) -> List[np.ndarray]:
        """
        Извлечь embeddings всех лиц на изображении
        
        Args:
            image_path: Путь к изображению
            apply_exif: Применить EXIF ориентацию перед обработкой (по умолчанию True)
        """
        temp_path = None
        try:
            # Применяем EXIF ориентацию если нужно
            if apply_exif:
                temp_path = self._apply_exif_orientation(image_path)
                if temp_path:
                    image_path = temp_path
            
            img = cv2.imread(image_path)
            if img is None:
                return []
            
            # ВАЖНО: Добавляем таймаут для InsightFace, чтобы избежать зависаний
            import time
            from concurrent.futures import ThreadPoolExecutor, TimeoutError as FuturesTimeoutError
            import logging
            logger = logging.getLogger(__name__)
            
            faces = []
            timeout_seconds = 30  # 30 секунд на обработку изображения
            
            try:
                def get_faces():
                    return self.model.get(img)
                
                # Используем ThreadPoolExecutor для таймаута
                with ThreadPoolExecutor(max_workers=1) as executor:
                    future = executor.submit(get_faces)
                    try:
                        faces = future.result(timeout=timeout_seconds)
                    except FuturesTimeoutError:
                        logger.warning(f"InsightFace timeout on image after {timeout_seconds}s, skipping")
                        # Пытаемся отменить задачу
                        future.cancel()
                        faces = []
                    except Exception as e:
                        logger.warning(f"Error in InsightFace processing: {str(e)}")
                        faces = []
            except Exception as e:
                logger.warning(f"Error in face detection with timeout: {str(e)}, trying without timeout")
                # Fallback: пробуем без таймаута (но с ограничением времени)
                try:
                    start_time = time.time()
                    faces = self.model.get(img)
                    elapsed = time.time() - start_time
                    if elapsed > 10:
                        logger.warning(f"Face detection took {elapsed:.2f}s (slow)")
                except Exception as fallback_error:
                    logger.error(f"Error in face detection fallback: {str(fallback_error)}")
                    faces = []
            
            embeddings = [face.embedding.astype("float32") for face in faces]
            return embeddings
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.error(f"Error extracting face embeddings: {str(e)}", exc_info=True)
            return []
        finally:
            # Удаляем временный файл если он был создан
            if temp_path and os.path.exists(temp_path):
                try:
                    os.unlink(temp_path)
                except:
                    pass
    
    def extract_faces_with_bboxes(self, image_path: str, apply_exif: bool = True) -> List[Dict]:
        """
        Извлечь embeddings и bbox всех лиц на изображении
        
        Args:
            image_path: Путь к изображению
            apply_exif: Применить EXIF ориентацию перед обработкой (по умолчанию True)
        
        Returns: список словарей с ключами 'embedding' и 'bbox'
        bbox: [x1, y1, x2, y2] - координаты bounding box
        """
        temp_path = None
        try:
            import logging
            logger = logging.getLogger(__name__)
            
            logger.debug(f"Extracting faces from: {image_path}")
            
            # Применяем EXIF ориентацию если нужно
            if apply_exif:
                temp_path = self._apply_exif_orientation(image_path)
                if temp_path:
                    image_path = temp_path
            
            img = cv2.imread(image_path)
            if img is None:
                logger.warning(f"Failed to load image: {image_path}")
                return []
            
            # ВАЖНО: Логируем информацию об изображении для диагностики
            logger.info(f"Image loaded: shape={img.shape}, dtype={img.dtype}")
            
            # Проверяем размер изображения - если слишком маленькое, может быть проблема с детекцией
            if img.shape[0] < 100 or img.shape[1] < 100:
                logger.warning(f"Image is very small: {img.shape}, face detection may not work well")
            
            # Проверяем, что модель загружена
            if not hasattr(self, 'model') or self.model is None:
                logger.error("FaceRecognition model is not initialized")
                return []
            
            # ВАЖНО: Добавляем таймаут для InsightFace, чтобы избежать зависаний
            # InsightFace может зависать на больших или сложных изображениях
            # Используем concurrent.futures для более надежного таймаута
            import time
            from concurrent.futures import ThreadPoolExecutor, TimeoutError as FuturesTimeoutError
            
            faces = []
            timeout_seconds = 60  # Увеличено до 60 секунд на обработку изображения (было 30)
            
            try:
                def get_faces():
                    try:
                        return self.model.get(img)
                    except Exception as e:
                        logger.error(f"Error in get_faces: {str(e)}")
                        raise
                
                # Используем ThreadPoolExecutor для таймаута
                with ThreadPoolExecutor(max_workers=1) as executor:
                    future = executor.submit(get_faces)
                    try:
                        faces = future.result(timeout=timeout_seconds)
                        if faces:
                            logger.info(f"Found {len(faces)} face(s) in image")
                        else:
                            logger.info("No faces found in image")
                    except FuturesTimeoutError:
                        logger.warning(f"InsightFace timeout on image after {timeout_seconds}s, trying fallback")
                        # Пытаемся отменить задачу
                        future.cancel()
                        # Fallback: пробуем без таймаута для критических случаев
                        try:
                            logger.info("Attempting fallback face detection without timeout")
                            start_time = time.time()
                            faces = self.model.get(img)
                            elapsed = time.time() - start_time
                            if elapsed > 10:
                                logger.warning(f"Face detection took {elapsed:.2f}s (slow)")
                            if faces:
                                logger.info(f"Found {len(faces)} face(s) in image (fallback)")
                            else:
                                logger.info("No faces found in image (fallback)")
                        except Exception as fallback_error:
                            logger.error(f"Error in face detection fallback: {str(fallback_error)}")
                            faces = []
                    except Exception as e:
                        logger.warning(f"Error in InsightFace processing: {str(e)}")
                        # Fallback: пробуем без таймаута
                        try:
                            logger.info("Attempting fallback face detection after error")
                            faces = self.model.get(img)
                            if faces:
                                logger.info(f"Found {len(faces)} face(s) in image (fallback after error)")
                        except Exception as fallback_error:
                            logger.error(f"Error in face detection fallback: {str(fallback_error)}")
                            faces = []
            except Exception as e:
                logger.warning(f"Error in face detection with timeout: {str(e)}, trying without timeout")
                # Fallback: пробуем без таймаута (но с ограничением времени)
                try:
                    start_time = time.time()
                    faces = self.model.get(img)
                    elapsed = time.time() - start_time
                    if elapsed > 10:
                        logger.warning(f"Face detection took {elapsed:.2f}s (slow)")
                    if faces:
                        logger.info(f"Found {len(faces)} face(s) in image (fallback)")
                    else:
                        logger.info("No faces found in image (fallback)")
                except Exception as fallback_error:
                    logger.error(f"Error in face detection fallback: {str(fallback_error)}")
                    faces = []
            
            result = []
            # ВАЖНО: Минимальный порог confidence для детекции лиц
            # InsightFace возвращает det_score от 0 до 1, где 1 - максимальная уверенность
            # По умолчанию InsightFace фильтрует лица с det_score < 0.5, но мы понижаем для лучшего распознавания
            # ВАЖНО: Понижаем порог до 0.2 для более чувствительного распознавания
            min_det_score = 0.2  # Минимальный порог confidence (понижено с 0.3 до 0.2 для лучшего распознавания)
            
            for idx, face in enumerate(faces):
                try:
                    # Получаем det_score (confidence детекции)
                    det_score = float(face.det_score) if hasattr(face, 'det_score') else 1.0
                    
                    # Фильтруем лица с низким confidence
                    if det_score < min_det_score:
                        logger.info(f"Face {idx + 1} filtered out: det_score={det_score:.3f} < min_det_score={min_det_score}")
                        continue
                    
                    # bbox: [x1, y1, x2, y2]
                    bbox = face.bbox.tolist() if hasattr(face.bbox, 'tolist') else list(face.bbox)
                    
                    # ВАЖНО: Логируем на уровне info для диагностики
                    logger.info(f"Face {idx + 1}: bbox={bbox}, det_score={det_score:.3f}")
                    
                    # Проверяем, что embedding не пустой
                    if face.embedding is None or len(face.embedding) == 0:
                        logger.warning(f"Face {idx + 1} has empty embedding, skipping")
                        continue
                    
                    result.append({
                        'embedding': face.embedding.astype("float32"),
                        'bbox': bbox,
                        'det_score': det_score
                    })
                    
                    logger.info(f"Face {idx + 1} added: embedding shape={face.embedding.shape}, bbox={bbox}, det_score={det_score:.3f}")
                except Exception as e:
                    logger.error(f"Error processing face {idx + 1}: {str(e)}", exc_info=True)
                    continue
            
            logger.info(f"Successfully extracted {len(result)} face(s) from {image_path}")
            return result
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.error(f"Error extracting faces with bboxes from {image_path}: {str(e)}", exc_info=True)
            return []
        finally:
            # Удаляем временный файл если он был создан
            if temp_path and os.path.exists(temp_path):
                try:
                    os.unlink(temp_path)
                except:
                    pass
    
    def compare_embeddings(
        self,
        embedding1: np.ndarray,
        embedding2: np.ndarray
    ) -> float:
        """
        Сравнить два embedding
        
        Returns: расстояние (меньше = более похожи)
        Используется косинусное расстояние
        """
        # Убеждаемся, что оба embedding в float32
        embedding1 = np.array(embedding1, dtype=np.float32)
        embedding2 = np.array(embedding2, dtype=np.float32)
        
        # Нормализуем векторы
        embedding1 = embedding1 / np.linalg.norm(embedding1)
        embedding2 = embedding2 / np.linalg.norm(embedding2)
        
        # Косинусное расстояние
        distance = 1 - np.dot(embedding1, embedding2)
        return float(distance)
    
    def save_embeddings(self, photo_id: str, embeddings: List[np.ndarray], storage_path: str = "./embeddings"):
        """Сохранить embeddings в файл"""
        os.makedirs(storage_path, exist_ok=True)
        file_path = os.path.join(storage_path, f"{photo_id}.pkl")
        
        with open(file_path, 'wb') as f:
            pickle.dump(embeddings, f)
    
    def load_embeddings(self, photo_id: str, storage_path: str = "./embeddings") -> Optional[List[np.ndarray]]:
        """Загрузить embeddings из файла"""
        file_path = os.path.join(storage_path, f"{photo_id}.pkl")
        
        if not os.path.exists(file_path):
            return None
        
        try:
            with open(file_path, 'rb') as f:
                return pickle.load(f)
        except Exception as e:
            print(f"Error loading embeddings: {str(e)}")
            return None


