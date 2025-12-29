#!/usr/bin/env python3
"""
Тестовый скрипт для проверки работы InsightFace детекции лиц
"""
import sys
import os

# Добавляем путь к приложению
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from utils.face_recognition import get_face_recognition
import logging

# Настраиваем логирование
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)

logger = logging.getLogger(__name__)

def test_face_detection():
    """Тест детекции лиц"""
    
    # Путь к тестовому фото
    test_photo_path = "/var/www/html/storage/app/public/events/load/test_photo.JPG"
    
    # Альтернативные пути (на случай если файл в другом месте)
    alternative_paths = [
        test_photo_path,
        "/app/uploads/test_photo.JPG",
        "test_photo.JPG",
        os.path.join(os.path.dirname(__file__), "test_photo.JPG"),
    ]
    
    # Ищем файл
    photo_path = None
    for path in alternative_paths:
        if os.path.exists(path):
            photo_path = path
            logger.info(f"Found test photo at: {photo_path}")
            break
    
    if not photo_path:
        logger.error("Test photo not found! Tried paths:")
        for path in alternative_paths:
            logger.error(f"  - {path}")
        return False
    
    # Проверяем размер файла
    file_size = os.path.getsize(photo_path)
    logger.info(f"Test photo size: {file_size} bytes ({file_size / 1024 / 1024:.2f} MB)")
    
    try:
        # Получаем экземпляр FaceRecognition (singleton)
        logger.info("Initializing FaceRecognition...")
        face_recognition = get_face_recognition()
        logger.info("FaceRecognition initialized successfully")
        
        # Извлекаем лица
        logger.info(f"Extracting faces from: {photo_path}")
        faces = face_recognition.extract_faces_with_bboxes(photo_path)
        
        # Выводим результаты
        logger.info("=" * 60)
        logger.info(f"RESULT: Found {len(faces)} face(s)")
        logger.info("=" * 60)
        
        if len(faces) == 0:
            logger.warning("NO FACES DETECTED! This could mean:")
            logger.warning("  1. InsightFace model is not working correctly")
            logger.warning("  2. Image quality is too low")
            logger.warning("  3. No faces in the image")
            logger.warning("  4. Detection threshold is too high")
            return False
        else:
            logger.info("SUCCESS! Faces detected:")
            for idx, face_data in enumerate(faces, 1):
                bbox = face_data.get('bbox', [])
                det_score = face_data.get('det_score', 0)
                embedding = face_data.get('embedding')
                
                logger.info(f"  Face {idx}:")
                logger.info(f"    - BBox: {bbox}")
                logger.info(f"    - Detection Score: {det_score:.3f}")
                if embedding is not None:
                    logger.info(f"    - Embedding shape: {embedding.shape if hasattr(embedding, 'shape') else 'N/A'}")
                    logger.info(f"    - Embedding length: {len(embedding) if hasattr(embedding, '__len__') else 'N/A'}")
            
            return True
            
    except Exception as e:
        logger.error(f"Error during face detection: {str(e)}", exc_info=True)
        return False

if __name__ == "__main__":
    logger.info("Starting face detection test...")
    success = test_face_detection()
    
    if success:
        logger.info("Test completed successfully!")
        sys.exit(0)
    else:
        logger.error("Test failed!")
        sys.exit(1)

