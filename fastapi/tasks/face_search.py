from celery import Task
from tasks.celery_app import celery_app
import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from utils.face_recognition import get_face_recognition
import os
from typing import List, Dict


class CallbackTask(Task):
    def on_progress(self, current: int, total: int):
        self.update_state(
            state='PROGRESS',
            meta={
                'progress': int((current / total) * 100),
                'current': current,
                'total': total
            }
        )


@celery_app.task(bind=True, base=CallbackTask)
def search_similar_faces(self, query_image_path: str, event_id: str = None, threshold: float = 0.6):
    """
    Поиск похожих фотографий (InsightFace + cosine distance)
    """
    import logging
    import numpy as np
    from app.database import SessionLocal
    from app.models import Photo

    logger = logging.getLogger("search_similar_faces")
    logger.setLevel(logging.DEBUG)

    db = SessionLocal()
    # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №1: Используем singleton вместо создания нового экземпляра
    face_recognition = get_face_recognition()

    try:
        logger.info(f"START search_similar_faces: event_id={event_id}, threshold={threshold}")
        logger.info(f"Query image: {query_image_path}")
        
        # Проверяем, что файл существует
        if not os.path.exists(query_image_path):
            error_msg = f"Query image file not found: {query_image_path}"
            logger.error(error_msg)
            return {"error": error_msg, "results": []}

        # ---- 1) Извлекаем embedding запроса ----
        # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №3: Убран параметр apply_exif
        # EXIF применяется ТОЛЬКО ОДИН РАЗ при загрузке фото через remove_exif_and_rotate
        query_embedding = face_recognition.extract_embedding(query_image_path)
        if query_embedding is None:
            logger.warning("No face found in query image")
            return {"error": "No face found in query image", "results": []}

        logger.info(f"Query embedding length: {len(query_embedding)}")
        logger.debug(f"Query embedding dtype: {query_embedding.dtype}, shape: {query_embedding.shape}")
        logger.debug(f"Query embedding (first 5): {query_embedding[:5]}")

        # ---- 2) Грузим фото из базы ----
        # ВАЖНО: Проверяем разные варианты фильтрации has_faces
        # В PostgreSQL boolean может быть True, False или NULL
        # Используем более надежный фильтр
        if event_id:
            # Сначала проверяем все фотографии события для диагностики
            all_photos_count = db.query(Photo).filter(Photo.event_id == event_id).count()
            logger.info(f"Total photos in event {event_id}: {all_photos_count}")
            
            # Проверяем фотографии с has_faces=True
            has_faces_true_count = db.query(Photo).filter(
                Photo.event_id == event_id,
                Photo.has_faces == True
            ).count()
            logger.info(f"Photos with has_faces=True in event {event_id}: {has_faces_true_count}")
            
            # Проверяем фотографии с face_encodings не пустыми
            has_encodings_count = db.query(Photo).filter(
                Photo.event_id == event_id,
                Photo.face_encodings.isnot(None),
                Photo.face_encodings != []
            ).count()
            logger.info(f"Photos with face_encodings in event {event_id}: {has_encodings_count}")
            
            # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Используем только face_encodings как источник истины
            # has_faces оставляем как вспомогательное поле, не как фильтр
            q = db.query(Photo).filter(
                Photo.event_id == event_id,
                Photo.face_encodings.isnot(None),
                Photo.face_encodings != []
            )
        else:
            # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Используем только face_encodings как источник истины
            q = db.query(Photo).filter(
                Photo.face_encodings.isnot(None),
                Photo.face_encodings != []
            )

        photos = q.all()
        total_photos = len(photos)
        logger.info(f"Loaded {total_photos} photos for comparison")
        
        # ВАЖНО: Логируем детали для диагностики
        if total_photos == 0:
            logger.warning(f"No photos found with faces! Event_id={event_id}")
            if event_id:
                # Показываем примеры фотографий для диагностики
                sample_photos = db.query(Photo).filter(Photo.event_id == event_id).limit(5).all()
                for sample in sample_photos:
                    logger.warning(f"Sample photo {sample.id}: has_faces={sample.has_faces}, face_encodings={len(sample.face_encodings) if sample.face_encodings else 0}")

        results = []

        # ---- 3) Перебор фотографий ----
        for idx, photo in enumerate(photos, start=1):

            try:
                # ---- Загружаем embeddings (из БД или файл) ----
                emb_list = None

                if photo.face_encodings:
                    emb_list = photo.face_encodings  # SQLAlchemy сам десериализует JSON
                    logger.debug(f"Photo {photo.id}: Loaded {len(emb_list) if emb_list else 0} embeddings from DB")

                if not emb_list:
                    emb_list = face_recognition.load_embeddings(photo.id)
                    if emb_list:
                        logger.debug(f"Photo {photo.id}: Loaded {len(emb_list)} embeddings from file")
                        # Преобразуем numpy массивы в списки для дальнейшей обработки
                        # ВАЖНО: Сохраняем точность float32
                        emb_list = [emb.tolist() if hasattr(emb, 'tolist') else (list(emb) if not isinstance(emb, list) else emb) for emb in emb_list]

                if not emb_list:
                    logger.debug(f"Photo {photo.id}: no embeddings found")
                    continue

                # Преобразуем в numpy массивы с правильным типом
                try:
                emb_list = [np.array(e, dtype=np.float32) for e in emb_list]
                    # Проверяем, что все embeddings имеют правильную форму
                    emb_list = [emb for emb in emb_list if emb.ndim == 1 and len(emb) > 0]
                except Exception as e:
                    logger.error(f"Photo {photo.id}: Error converting embeddings to numpy arrays: {str(e)}")
                    continue

                if not emb_list:
                    logger.warning(f"Photo {photo.id}: No valid embeddings after conversion")
                    continue

                # Проверка длины - разные модели могут иметь разную размерность
                # Проверяем только первую, остальные должны быть такой же
                first_emb_len = len(emb_list[0])
                if first_emb_len != len(query_embedding):
                    logger.warning(
                        f"Photo {photo.id}: embedding size mismatch "
                        f"{first_emb_len} != {len(query_embedding)}. Skipping."
                    )
                    continue
                
                # Проверяем, что все embeddings имеют одинаковую длину
                if not all(len(emb) == first_emb_len for emb in emb_list):
                    logger.warning(f"Photo {photo.id}: Inconsistent embedding sizes, filtering...")
                    emb_list = [emb for emb in emb_list if len(emb) == first_emb_len]
                    if not emb_list:
                    continue

                logger.debug(f"Photo {photo.id}: {len(emb_list)} embeddings, dtype: {emb_list[0].dtype}, shape: {emb_list[0].shape}")
                
                # Нормализуем query_embedding один раз (вынесено из цикла для оптимизации)
                query_norm = np.linalg.norm(query_embedding)
                if query_norm == 0:
                    logger.warning(f"Query embedding has zero norm, skipping photo {photo.id}")
                    continue
                query_embedding_normalized = query_embedding / query_norm
                logger.debug(f"Query embedding normalized, norm: {np.linalg.norm(query_embedding_normalized):.6f}")

                # ---- 4) Сравнение ----
                best_distance = 99

                for emb_idx, emb in enumerate(emb_list):
                    # Нормализуем embedding из базы
                    emb_norm = np.linalg.norm(emb)
                    if emb_norm == 0:
                        logger.warning(f"Photo {photo.id}, embedding {emb_idx + 1}: zero norm, skipping")
                        continue
                    emb_normalized = emb / emb_norm
                    
                    # Косинусное расстояние (1 - cosine similarity)
                    # Используем прямое вычисление для точности
                    cosine_similarity = np.dot(query_embedding_normalized, emb_normalized)
                    # Ограничиваем значение в диапазоне [-1, 1] для избежания численных ошибок
                    cosine_similarity = np.clip(cosine_similarity, -1.0, 1.0)
                    dist = 1.0 - cosine_similarity
                    
                    logger.debug(f"Photo {photo.id}, embedding {emb_idx + 1}: distance={dist:.4f}, similarity={cosine_similarity:.4f}")

                    if dist < best_distance:
                        best_distance = dist

                logger.debug(f"Photo {photo.id}: best_distance={best_distance:.4f}, threshold={threshold}")

                if best_distance <= threshold:
                    results.append({
                        "photo_id": photo.id,
                        "distance": float(best_distance),
                        "similarity": 1.0 - best_distance
                    })
                    logger.info(f"Photo {photo.id}: MATCH found! distance={best_distance:.4f} <= threshold={threshold}")

                # ---- progress ----
                if idx % 20 == 0:
                    self.on_progress(idx, total_photos)
                    logger.info(f"Progress: {idx}/{total_photos} photos processed, {len(results)} matches found")

            except Exception as e:
                logger.error(f"Error comparing photo {photo.id}: {str(e)}", exc_info=True)
                continue

        # ---- 5) Сортировка ----
        results.sort(key=lambda x: x["distance"])

        logger.info(f"FOUND {len(results)} similar faces")
        if results:
            logger.info(f"Best match: photo_id={results[0]['photo_id']}, distance={results[0]['distance']:.4f}")

        return {
            "status": "completed",
            "results": results,
            "total_found": len(results)
        }

    except Exception as e:
        error_msg = f"CRITICAL ERROR in search_similar_faces: {str(e)}"
        logger.error(error_msg, exc_info=True)
        # Возвращаем ошибку вместо raise, чтобы задача не падала полностью
        return {
            "status": "error",
            "error": error_msg,
            "results": []
        }
    finally:
        db.close()
        # Удаляем временный файл запроса после обработки
        if query_image_path and os.path.exists(query_image_path):
            try:
                # Проверяем, что это временный файл из uploads (безопасность)
                if query_image_path.startswith('/app/uploads/'):
                    os.unlink(query_image_path)
                    logger.debug(f"Deleted temporary query file: {query_image_path}")
            except Exception as e:
                logger.warning(f"Failed to delete temporary file {query_image_path}: {str(e)}")


def extract_face_embeddings(image_path: str) -> List:
    """Извлечь embeddings всех лиц на фотографии (для обратной совместимости)"""
    import logging
    logger = logging.getLogger(__name__)
    
    try:
        # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №1: Используем singleton вместо создания нового экземпляра
        face_recognition = get_face_recognition()
        faces_data = face_recognition.extract_faces_with_bboxes(image_path)
        
        if faces_data:
            logger.info(f"Extracted {len(faces_data)} face(s) from {image_path}")
            # Возвращаем только embeddings для обратной совместимости
            return [face['embedding'] for face in faces_data]
        else:
            logger.warning(f"No faces found in {image_path}")
        
        return []
    except Exception as e:
        logger.error(f"Error extracting face embeddings from {image_path}: {str(e)}", exc_info=True)
        return []


def extract_faces_with_bboxes(image_path: str) -> List[Dict]:
    """
    Извлечь embeddings и bbox всех лиц на фотографии
    
    КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №3: Убран параметр apply_exif
    EXIF применяется ТОЛЬКО ОДИН РАЗ при загрузке фото через remove_exif_and_rotate
    
    Args:
        image_path: Путь к изображению
    """
    import logging
    logger = logging.getLogger(__name__)
    
    try:
        # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №1: Используем singleton вместо создания нового экземпляра
        face_recognition = get_face_recognition()
        faces_data = face_recognition.extract_faces_with_bboxes(image_path)
        
        if faces_data:
            logger.info(f"Extracted {len(faces_data)} face(s) with bboxes from {image_path}")
        else:
            logger.warning(f"No faces found in {image_path}")
        
        return faces_data
    except Exception as e:
        logger.error(f"Error extracting faces with bboxes from {image_path}: {str(e)}", exc_info=True)
        return []

