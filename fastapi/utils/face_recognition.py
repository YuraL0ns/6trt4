import insightface
import numpy as np
import cv2
import os
import pickle
from typing import List, Optional, Tuple, Dict
from app.config import settings
import logging

logger = logging.getLogger(__name__)

# КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №1: Singleton для FaceRecognition
# Модель должна быть singleton на процесс, иначе InsightFace не инициализируется корректно
_face_recognition_instance = None

def get_face_recognition():
    """Получить singleton экземпляр FaceRecognition"""
    global _face_recognition_instance
    if _face_recognition_instance is None:
        _face_recognition_instance = FaceRecognition()
    return _face_recognition_instance


class FaceRecognition:
    """Распознавание лиц с помощью InsightFace"""
    
    def __init__(self):
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
                # КРИТИЧНО: ctx_id=-1 для CPU (ctx_id=0 для GPU, но GPU может не быть)
                self.model.prepare(ctx_id=-1, det_size=(640, 640))
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
    
    def _prepare_image(self, img: np.ndarray) -> np.ndarray:
        """
        КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №2: Подготовка изображения для InsightFace
        - Конвертация BGR → RGB
        - Resize до максимум 1280px (InsightFace ожидает нормальный размер)
        """
        # Конвертируем BGR → RGB (cv2.imread возвращает BGR, InsightFace ожидает RGB)
        if len(img.shape) == 3 and img.shape[2] == 3:
            img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
        
        # Нормализуем размер (не 4k вертикаль после EXIF)
        h, w = img.shape[:2]
        max_side = max(h, w)
        if max_side > 1280:
            scale = 1280 / max_side
            new_w = int(w * scale)
            new_h = int(h * scale)
            img = cv2.resize(img, (new_w, new_h), interpolation=cv2.INTER_LINEAR)
            logger.debug(f"Image resized from {w}x{h} to {new_w}x{new_h}")
        
        return img
    
    def extract_embedding(self, image_path: str) -> Optional[np.ndarray]:
        """
        Извлечь embedding одного лица (первое найденное)
        
        КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Убран параметр apply_exif
        EXIF применяется ТОЛЬКО ОДИН РАЗ в начале пайплайна через normalize_orientation()
        После этого изображение уже нормализовано и не содержит EXIF
        """
        embeddings = self.extract_all_embeddings(image_path)
        if embeddings and len(embeddings) > 0:
            # Возвращаем первое лицо с правильным типом
            embedding = embeddings[0]
            if not isinstance(embedding, np.ndarray):
                embedding = np.array(embedding, dtype=np.float32)
            return embedding.astype("float32")
        return None
    
    def extract_all_embeddings(self, image_path: str) -> List[np.ndarray]:
        """
        Извлечь embeddings всех лиц на изображении
        
        КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Убран параметр apply_exif
        EXIF применяется ТОЛЬКО ОДИН РАЗ в начале пайплайна через normalize_orientation()
        После этого изображение уже нормализовано и не содержит EXIF
        """
        try:
            img = cv2.imread(image_path)
            if img is None:
                logger.warning(f"Failed to load image: {image_path}")
                return []
            
            # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №2: Подготавливаем изображение (BGR→RGB, resize)
            img = self._prepare_image(img)
            
            # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №5: Убран ThreadPoolExecutor
            # InsightFace не thread-safe, onnxruntime может зависать в thread'ах
            # Если зависает — проблема выше (модель/размер/контекст)
            try:
                faces = self.model.get(img)
                logger.info(f"Found {len(faces)} raw face(s) from InsightFace")
            except Exception as e:
                logger.error(f"Error in InsightFace model.get: {str(e)}", exc_info=True)
                return []
            
            embeddings = [face.embedding.astype("float32") for face in faces]
            return embeddings
        except Exception as e:
            logger.error(f"Error extracting face embeddings: {str(e)}", exc_info=True)
            return []
    
    def extract_faces_with_bboxes(self, image_path: str) -> List[Dict]:
        """
        Извлечь embeddings и bbox всех лиц на изображении
        
        КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Убран параметр apply_exif
        EXIF применяется ТОЛЬКО ОДИН РАЗ в начале пайплайна через normalize_orientation()
        После этого изображение уже нормализовано и не содержит EXIF
        
        Returns: список словарей с ключами 'embedding' и 'bbox'
        bbox: [x1, y1, x2, y2] - координаты bounding box
        """
        try:
            logger.debug(f"Extracting faces from: {image_path}")
            
            img = cv2.imread(image_path)
            if img is None:
                logger.warning(f"Failed to load image: {image_path}")
                return []
            
            # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №2: Подготавливаем изображение (BGR→RGB, resize)
            img = self._prepare_image(img)
            
            logger.info(f"Image loaded: shape={img.shape}, dtype={img.dtype}")
            
            # Проверяем размер изображения
            if img.shape[0] < 100 or img.shape[1] < 100:
                logger.warning(f"Image is very small: {img.shape}, face detection may not work well")
            
            # Проверяем, что модель загружена
            if not hasattr(self, 'model') or self.model is None:
                logger.error("FaceRecognition model is not initialized")
                return []
            
            # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №5: Убран ThreadPoolExecutor
            # InsightFace не thread-safe, onnxruntime может зависать в thread'ах
            try:
                faces = self.model.get(img)
                if faces:
                    logger.info(f"Found {len(faces)} raw face(s) from InsightFace")
                else:
                    logger.info("No raw faces found from InsightFace")
            except Exception as e:
                logger.error(f"Error in InsightFace model.get: {str(e)}", exc_info=True)
                return []
            
            result = []
            # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №6: min_det_score = 0.35 (было 0.2)
            # embeddings с плохих детекций → мусор, потом cosine distance не проходит
            min_det_score = 0.35  # Минимальный порог confidence для детекции лиц
            
            for idx, face in enumerate(faces):
                try:
                    # Получаем det_score (confidence детекции)
                    det_score = float(face.det_score) if hasattr(face, 'det_score') else 1.0
                    
                    # Фильтруем лица с низким confidence
                    if det_score < min_det_score:
                        logger.debug(f"Face {idx + 1} filtered out: det_score={det_score:.3f} < min_det_score={min_det_score}")
                        continue
                    
                    # bbox: [x1, y1, x2, y2]
                    bbox = face.bbox.tolist() if hasattr(face.bbox, 'tolist') else list(face.bbox)
                    
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
            
            logger.info(f"Successfully extracted {len(result)} face(s) from {image_path} after filtering")
            return result
        except Exception as e:
            logger.error(f"Error extracting faces with bboxes from {image_path}: {str(e)}", exc_info=True)
            return []
    
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
            logger.error(f"Error loading embeddings: {str(e)}")
            return None
