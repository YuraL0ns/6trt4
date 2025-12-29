from celery import Task
from tasks.celery_app import celery_app
import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from utils.number_recognition import NumberRecognition
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
def search_by_numbers(self, query_image_path: str, event_id: str = None):
    """
    Поиск фотографий по номеру
    """
    from app.database import SessionLocal
    from app.models import Photo
    
    db = SessionLocal()
    number_recognition = NumberRecognition()
    
    try:
        import logging
        logger = logging.getLogger(__name__)
        logger.info(f"START search_by_numbers: event_id={event_id}, query_image={query_image_path}")
        
        # Проверяем, что файл существует
        if not os.path.exists(query_image_path):
            error_msg = f"Query image file not found: {query_image_path}"
            logger.error(error_msg)
            return {"error": error_msg, "results": []}
        
        # Распознаем номера на запросе
        query_numbers = extract_numbers(query_image_path)
        logger.info(f"Extracted {len(query_numbers)} numbers from query image: {query_numbers}")
        
        if not query_numbers:
            logger.warning("No numbers found in query image")
            return {"error": "No numbers found in query image", "results": []}
        
        # Получаем все фотографии события
        query = db.query(Photo)
        if event_id:
            query = query.filter(Photo.event_id == event_id)
        
        photos = query.all()
        results = []
        
        for idx, photo in enumerate(photos, 1):
            try:
                if not photo.numbers:
                    continue
                
                # Проверяем совпадение номеров с улучшенным алгоритмом
                photo_numbers = photo.numbers if isinstance(photo.numbers, list) else []
                if not photo_numbers:
                    continue
                
                # Флаг для отслеживания, найдено ли совпадение для этого фото
                photo_matched = False
                
                for query_num in query_numbers:
                    if photo_matched:
                        break  # Уже нашли совпадение для этого фото
                    
                    # Очищаем запрос - только цифры
                    query_clean = ''.join(c for c in str(query_num) if c.isdigit())
                    if not query_clean or len(query_clean) < 1:
                        continue
                    
                    for photo_num in photo_numbers:
                        # Очищаем номер из фото - только цифры
                        photo_clean = ''.join(c for c in str(photo_num) if c.isdigit())
                        if not photo_clean or len(photo_clean) < 1:
                            continue
                        
                        # Точное совпадение (самый приоритетный)
                        if query_clean == photo_clean:
                            results.append({
                                "photo_id": photo.id,
                                "matched_number": photo_num,
                                "query_number": query_num,
                                "match_type": "exact"
                            })
                            photo_matched = True
                            break
                        
                        # Частичное совпадение (один номер содержит другой)
                        # Это полезно для случаев, когда распознан неполный номер
                        if len(query_clean) >= 2 and len(photo_clean) >= 2:
                            # Проверяем, содержит ли один номер другой
                            # Минимум 2 цифры должны совпадать
                            if query_clean in photo_clean or photo_clean in query_clean:
                                # Проверяем, что совпадающая часть достаточно длинная
                                min_len = min(len(query_clean), len(photo_clean))
                                if min_len >= 2:
                                results.append({
                                    "photo_id": photo.id,
                                    "matched_number": photo_num,
                                    "query_number": query_num,
                                    "match_type": "partial"
                                })
                                    photo_matched = True
                                break
                        
                        # Проверка схожести для номеров одинаковой длины (для опечаток OCR)
                        if len(query_clean) == len(photo_clean) and len(query_clean) >= 3:
                                # Вычисляем расстояние Хэмминга (количество разных позиций)
                                differences = sum(1 for a, b in zip(query_clean, photo_clean) if a != b)
                            # Для номеров длиной 3-4: допускаем 1 ошибку
                            # Для номеров длиной 5-6: допускаем 2 ошибки
                            # Для номеров длиной 7+: допускаем до 20% ошибок
                            max_diff = 1 if len(query_clean) <= 4 else (2 if len(query_clean) <= 6 else max(1, len(query_clean) // 5))
                            
                            if differences <= max_diff:
                                    results.append({
                                        "photo_id": photo.id,
                                        "matched_number": photo_num,
                                        "query_number": query_num,
                                        "match_type": "similar"
                                    })
                                photo_matched = True
                                    break
                
                # Обновляем прогресс
                if idx % 10 == 0:
                    self.on_progress(idx, len(photos))
                    
            except Exception as e:
                print(f"Error processing photo {photo.id}: {str(e)}")
                continue
        
        # Удаляем дубликаты результатов (если одно фото найдено несколько раз)
        seen_photo_ids = set()
        unique_results = []
        for result in results:
            if result["photo_id"] not in seen_photo_ids:
                unique_results.append(result)
                seen_photo_ids.add(result["photo_id"])
        
        logger.info(f"FOUND {len(unique_results)} photos with matching numbers (from {len(results)} total matches)")
        if unique_results:
            logger.info(f"Best matches: {unique_results[:5]}")
        
        return {
            "status": "completed",
            "results": unique_results,
            "total_found": len(unique_results),
            "query_numbers": query_numbers
        }
    
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        error_msg = f"CRITICAL ERROR in search_by_numbers: {str(e)}"
        logger.error(error_msg, exc_info=True)
        # Возвращаем ошибку вместо raise, чтобы задача не падала полностью
        return {
            "status": "error",
            "error": error_msg,
            "results": [],
            "query_numbers": []
        }
    finally:
        db.close()
        # Удаляем временный файл запроса после обработки
        if query_image_path and os.path.exists(query_image_path):
            try:
                # Проверяем, что это временный файл из uploads (безопасность)
                if query_image_path.startswith('/app/uploads/'):
                    os.unlink(query_image_path)
                    import logging
                    logger = logging.getLogger(__name__)
                    logger.debug(f"Deleted temporary query file: {query_image_path}")
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.warning(f"Failed to delete temporary file {query_image_path}: {str(e)}")


def extract_numbers(image_path: str) -> List[str]:
    """Извлечь номера с фотографии"""
    number_recognition = NumberRecognition()
    numbers = number_recognition.extract(image_path)
    return numbers

