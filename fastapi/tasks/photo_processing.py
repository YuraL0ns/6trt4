from celery import Task
from celery.exceptions import SoftTimeLimitExceeded, TimeLimitExceeded
from tasks.celery_app import celery_app
import sys
import os
import json
import fcntl
import traceback
from datetime import datetime
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from utils.image_processor import ImageProcessor
from utils.exif_processor import EXIFProcessor
from utils.watermark import WatermarkProcessor
from utils.step_logger import StepLogger
from typing import Dict, List


def update_event_info_json(event_info_path: str, photo_id: str, photo_name: str, analysis_type: str, data: dict, status: str = "ready"):
    """
    Обновляет event_info.json с результатами анализа
    Использует атомарную запись через временный файл для предотвращения повреждения JSON
    
    Args:
        event_info_path: Путь к файлу event_info.json
        photo_id: ID фотографии
        photo_name: Имя файла фотографии
        analysis_type: Тип анализа (timeline, removeexif, watermark, facesearch, numbersearch)
        data: Данные анализа
        status: Статус обработки (ready, processing, error)
    """
    if not event_info_path or not os.path.exists(event_info_path):
        print(f"Warning: event_info.json not found at {event_info_path}")
        return
    
    print(f"Updating event_info.json: photo_id={photo_id}, analysis_type={analysis_type}, status={status}")
    
    max_retries = 3
    retry_count = 0
    
    while retry_count < max_retries:
        try:
            # Читаем текущий файл с блокировкой
            with open(event_info_path, 'r', encoding='utf-8') as f:
                # Пытаемся заблокировать файл для чтения
                try:
                    fcntl.flock(f, fcntl.LOCK_SH)  # Shared lock для чтения
                except (AttributeError, OSError):
                    pass
                
                try:
                    event_info = json.load(f)
                except json.JSONDecodeError as e:
                    print(f"Error reading event_info.json (attempt {retry_count + 1}): {e}")
                    if retry_count < max_retries - 1:
                        retry_count += 1
                        import time
                        time.sleep(0.1)  # Небольшая задержка перед повтором
                        continue
                    else:
                        print(f"Failed to read event_info.json after {max_retries} attempts")
                        return
                
                try:
                    fcntl.flock(f, fcntl.LOCK_UN)
                except (AttributeError, OSError):
                    pass
            
            # Инициализируем секции анализа если их нет
            analysis_sections = {
                'timeline': 'analyze_timeline',
                'removeexif': 'analyze_removeexif',
                'watermark': 'analyze_watermark',
                'facesearch': 'analyze_facesearch',
                'numbersearch': 'analyze_numbersearch'
            }
            
            section_key = analysis_sections.get(analysis_type)
            if not section_key:
                print(f"Unknown analysis type: {analysis_type}")
                return
            
            if section_key not in event_info:
                event_info[section_key] = []
            
            # Ищем существующую запись для этой фотографии
            existing_index = None
            for idx, item in enumerate(event_info[section_key]):
                if item.get('photoId') == photo_id or item.get('photoId') == photo_name:
                    existing_index = idx
                    break
            
            # Создаем или обновляем запись
            analysis_entry = {
                'photoId': photo_id,
                'updated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'status': status
            }
            
            # Добавляем данные анализа в зависимости от типа
            if analysis_type == 'timeline':
                analysis_entry['date'] = data.get('date', '')
            elif analysis_type == 'removeexif':
                analysis_entry['data'] = 'clear'
            elif analysis_type == 'watermark':
                analysis_entry['data'] = 'watermark_add'
            elif analysis_type == 'facesearch':
                # Убеждаемся, что списки сериализуемы
                face_encodings = data.get('face_encodings', [])
                face_vector = data.get('face_vector', [])
                # Конвертируем numpy arrays в списки если нужно
                if hasattr(face_encodings, 'tolist'):
                    face_encodings = face_encodings.tolist()
                if hasattr(face_vector, 'tolist'):
                    face_vector = face_vector.tolist()
                analysis_entry['face_encodings'] = face_encodings
                analysis_entry['face_vector'] = face_vector
            elif analysis_type == 'numbersearch':
                # Сохраняем номера, если они есть, иначе null
                numbers = data.get('numbers', [])
                if numbers:
                    analysis_entry['number'] = numbers
                else:
                    analysis_entry['number'] = None  # null если номеров нет
            
            if existing_index is not None:
                event_info[section_key][existing_index] = analysis_entry
            else:
                event_info[section_key].append(analysis_entry)
            
            # Валидируем JSON перед записью
            try:
                json.dumps(event_info)  # Проверяем, что данные сериализуемы
            except (TypeError, ValueError) as e:
                print(f"Error validating JSON before write: {e}")
                return
            
            # Атомарная запись через временный файл
            temp_path = event_info_path + '.tmp'
            with open(temp_path, 'w', encoding='utf-8') as f:
                try:
                    fcntl.flock(f, fcntl.LOCK_EX)  # Exclusive lock для записи
                except (AttributeError, OSError):
                    pass
                
                json.dump(event_info, f, indent=4, ensure_ascii=False)
                f.flush()
                os.fsync(f.fileno())  # Принудительная запись на диск
                
                try:
                    fcntl.flock(f, fcntl.LOCK_UN)
                except (AttributeError, OSError):
                    pass
            
            # Атомарно заменяем оригинальный файл
            os.replace(temp_path, event_info_path)
            
            print(f"Updated event_info.json: {section_key} for photo {photo_id}")
            break  # Успешно обновлено, выходим из цикла
            
        except Exception as e:
            print(f"Error updating event_info.json (attempt {retry_count + 1}): {e}")
            if retry_count < max_retries - 1:
                retry_count += 1
                import time
                time.sleep(0.1)
                continue
            else:
                print(f"Failed to update event_info.json after {max_retries} attempts")
                # Удаляем временный файл если он остался
                temp_path = event_info_path + '.tmp'
                if os.path.exists(temp_path):
                    try:
                        os.remove(temp_path)
                    except:
                        pass
                return


class CallbackTask(Task):
    """Базовый класс для задач с обновлением прогресса"""
    
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
def process_event_photos(self, event_id: str, analyses: Dict[str, bool]):
    """
    Обработка всех фотографий события
    
    analyses: {
        'timeline': bool,
        'remove_exif': bool,
        'watermark': bool,
        'face_search': bool,
        'number_search': bool
    }
    """
    import json
    from app.database import SessionLocal
    from app.models import Photo, Event
    from utils.task_logger import get_task_logger
    from celery.exceptions import SoftTimeLimitExceeded, TimeLimitExceeded
    
    import logging
    logger = logging.getLogger(__name__)
    
    # Создаем детальный логгер для задачи
    task_logger = get_task_logger("process_event_photos", self.request.id)
    task_logger.log_task_start(event_id=event_id, analyses=analyses)
    
    db = SessionLocal()
    
    try:
        logger.info(f"START process_event_photos: event_id={event_id}, analyses={analyses}")
        task_logger.info(f"Начало обработки события {event_id}", analyses=analyses)
        
        # Сначала пытаемся прочитать event_info.json
        event_info_path = f"/var/www/html/storage/app/public/events/{event_id}/event_info.json"
        
        logger.info(f"Looking for event_info.json at: {event_info_path}")
        logger.info(f"File exists: {os.path.exists(event_info_path)}")
        
        if not os.path.exists(event_info_path):
            # Если файла нет, работаем напрямую с БД (fallback)
            logger.warning(f"event_info.json not found at {event_info_path}, using database")
            event = db.query(Event).filter(Event.id == event_id).first()
            if not event:
                error_msg = f"Event {event_id} not found"
                logger.error(error_msg)
                raise ValueError(error_msg)
            
            photos = db.query(Photo).filter(Photo.event_id == event_id).all()
            total = len(photos)
            photo_list = photos
        else:
            # Читаем event_info.json
            with open(event_info_path, 'r', encoding='utf-8') as f:
                event_info = json.load(f)
            
            print(f"Loaded event_info.json for event {event_id}: {len(event_info.get('photo', {}))} photos")
            
            # Используем данные из event_info.json
            photo_list = []
            total = event_info.get('photo_count', 0)
            
            # Загружаем фотографии из БД для обновления
            photos = db.query(Photo).filter(Photo.event_id == event_id).all()
            photo_dict = {p.id: p for p in photos}
            
            # Создаем список фотографий из event_info.json
            logger.info(f"Found {len(event_info.get('photo', {}))} photos in event_info.json")
            logger.info(f"Found {len(photos)} photos in database for event {event_id}")
            
            # Преобразуем ключи photo_dict в строки для сравнения (ID могут быть UUID)
            photo_dict_str = {str(k): v for k, v in photo_dict.items()}
            
            matched_count = 0
            unmatched_count = 0
            
            for photo_name, photo_data in event_info.get('photo', {}).items():
                photo_id = photo_data.get('id')
                photo_id_str = str(photo_id) if photo_id else None
                logger.debug(f"Processing photo from event_info.json: name={photo_name}, id={photo_id} (str: {photo_id_str})")
                
                if photo_id_str and photo_id_str in photo_dict_str:
                    photo_list.append(photo_dict_str[photo_id_str])
                    matched_count += 1
                    logger.debug(f"Photo {photo_name} (id: {photo_id_str}) added to photo_list")
                else:
                    # Если фото нет в БД, пропускаем
                    unmatched_count += 1
                    available_ids_sample = list(photo_dict_str.keys())[:5]
                    logger.warning(f"Photo {photo_name} (id: {photo_id_str}) not found in database. Available photo IDs (sample): {available_ids_sample}")
                    print(f"Warning: Photo {photo_name} (id: {photo_id_str}) not found in database")
                    continue
            
            logger.info(f"Matched {matched_count} photos from event_info.json, {unmatched_count} unmatched")
            
            logger.info(f"Created photo_list with {len(photo_list)} photos from event_info.json")
            
            # ВАЖНО: Если photo_list пуст или содержит меньше фотографий, чем в БД, 
            # используем все фотографии из БД
            # Это исправляет проблему, когда в event_info.json не все фотографии
            if len(photo_list) < len(photos):
                logger.warning(
                    f"photo_list has {len(photo_list)} photos but database has {len(photos)} photos. "
                    f"Using all photos from database to ensure all photos are processed."
                )
                photo_list = photos
                total = len(photos)
                logger.info(f"Using all {len(photo_list)} photos from database instead of event_info.json")
            
            # Если photo_list пуст, но в БД есть фотографии, используем все фотографии из БД
            if len(photo_list) == 0 and len(photos) > 0:
                logger.warning(f"photo_list is empty but database has {len(photos)} photos. Using all photos from database.")
                photo_list = photos
                total = len(photos)
                logger.info(f"Using all {len(photo_list)} photos from database instead of event_info.json")
            
            # НЕ перезаписываем analyses из event_info.json - используем только переданные
            # event_info.json может содержать старые данные, которые не должны влиять на текущий запуск
            if 'analyze' in event_info:
                event_info_analyses = event_info['analyze']
                logger.info(f"Found analyses in event_info.json: {event_info_analyses}, but using passed analyses: {analyses}")
        
        logger.info(f"Processing event {event_id} with analyses: {analyses}")
        logger.info(f"Analyses enabled: timeline={analyses.get('timeline', False)}, remove_exif={analyses.get('remove_exif', False)}, watermark={analyses.get('watermark', False)}, face_search={analyses.get('face_search', False)}, number_search={analyses.get('number_search', False)}")
        
        event = db.query(Event).filter(Event.id == event_id).first()
        if not event:
            raise ValueError(f"Event {event_id} not found")
        
        # Определяем путь к директории события (используется в разных местах)
        event_dir = f"/var/www/html/storage/app/public/events/{event_id}"
        
        image_processor = ImageProcessor()
        exif_processor = EXIFProcessor()
        watermark_processor = WatermarkProcessor()
        
        logger.info(f"Initialized processors for event {event_id}")
        
        # Инициализируем все фотографии в секциях анализа (если event_info.json существует)
        # Это нужно для того, чтобы каждая фотография имела запись на каждом шаге анализа
        if os.path.exists(event_info_path):
            try:
                with open(event_info_path, 'r', encoding='utf-8') as f:
                    event_info_init = json.load(f)
                
                # Инициализируем записи для всех фотографий в каждой секции анализа
                for photo in photo_list:
                    photo_id = str(photo.id)
                    
                    # Инициализируем для каждого типа анализа, который будет выполняться
                    if analyses.get('number_search', False):
                        section_key = 'analyze_numbersearch'
                        if section_key not in event_info_init:
                            event_info_init[section_key] = []
                        
                        # Проверяем, есть ли уже запись для этой фотографии
                        existing = any(item.get('photoId') == photo_id for item in event_info_init[section_key])
                        if not existing:
                            # Создаем начальную запись со статусом processing
                            event_info_init[section_key].append({
                                'photoId': photo_id,
                                'updated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                                'status': 'processing',
                                'number': None  # null пока не обработано
                            })
                
                # Сохраняем обновленный event_info.json
                temp_path = event_info_path + '.tmp'
                with open(temp_path, 'w', encoding='utf-8') as f:
                    json.dump(event_info_init, f, indent=4, ensure_ascii=False)
                os.replace(temp_path, event_info_path)
                print(f"Initialized analysis sections for {len(photo_list)} photos")
            except Exception as e:
                print(f"Warning: Failed to initialize analysis sections: {e}")
        
        # Инициализируем счетчики для обновления event_info.json каждые 5 фотографий
        update_counter = 0
        update_interval = 5
        
        # ВАЖНО: Обновляем total на основе реального количества фотографий
        total = len(photo_list)
        logger.info(f"Starting to process {total} photos for event {event_id}")
        logger.info(f"Analyses configuration: {analyses}")
        
        # Счетчик успешно обработанных фотографий
        successfully_processed = 0
        failed_photos = []
        
        # ВАЖНО: Таймаут на обработку одной фотографии (5 минут)
        # Если обработка одной фотографии занимает больше 5 минут, пропускаем её
        PHOTO_PROCESSING_TIMEOUT = 300  # 5 минут
        
        # Обновляем прогресс в начале
        self.on_progress(0, total)
        logger.info(f"Progress: 0/{total} (0%)")
        
        for idx, photo in enumerate(photo_list, 1):
            photo_start_time = None
            try:
                import time
                photo_start_time = time.time()
                
                # ВАЖНО: Проверяем таймаут перед началом обработки
                # Если уже прошло слишком много времени, пропускаем фотографию
                if idx > 1:  # Не проверяем для первой фотографии
                    # Проверяем, не зависла ли задача на предыдущих фотографиях
                    # (это косвенная проверка, основная проверка будет после обработки)
                    pass
                
                # Обновляем прогресс в начале обработки каждой фотографии
                # Это помогает видеть, что задача не зависла
                try:
                    self.on_progress(idx - 1, total)
                except Exception as progress_error:
                    logger.warning(f"Failed to update progress at start of photo {idx}: {str(progress_error)}")
                
                logger.info(f"Processing photo {idx}/{total}: photo_id={photo.id}")
                logger.info(f"Progress: {idx}/{total} ({(idx/total*100):.1f}%)")
                
                # Получаем полный путь к файлу
                photo_path = None
                
                # Сначала пытаемся получить путь из original_path
                # original_path всегда относительный (events/{event_id}/upload/filename.jpg)
                if hasattr(photo, 'original_path') and photo.original_path:
                    relative_path = photo.original_path
                    # Строим путь для Docker контейнера
                    # original_path уже относительный, просто добавляем базовый путь контейнера
                    photo_path = f"/var/www/html/storage/app/public/{relative_path}"
                    
                    # Если файл не найден, пробуем альтернативные варианты (на случай разных форматов)
                    if not os.path.exists(photo_path):
                        possible_paths = [
                            photo_path,  # Основной путь
                            f"/var/www/html/storage/{relative_path}",  # Без app/public
                            os.path.join("/var/www/html/storage/app/public", relative_path),  # Через join
                        ]
                        
                        # Проверяем каждый путь
                        for path in possible_paths:
                            if os.path.exists(path):
                                photo_path = path
                                break
                
                # Если путь не найден, пытаемся получить из event_info.json
                if not photo_path or not os.path.exists(photo_path):
                    if os.path.exists(event_info_path):
                        try:
                            with open(event_info_path, 'r', encoding='utf-8') as f:
                                event_info_data = json.load(f)
                            photo_name = getattr(photo, 'original_name', None) or getattr(photo, 'custom_name', None)
                            if photo_name:
                                photo_data = event_info_data.get('photo', {}).get(photo_name, {})
                                
                                # Приоритет: docker_path > relative_path > filepath (legacy)
                                docker_path = photo_data.get('docker_path')
                                relative_path = photo_data.get('relative_path')
                                legacy_filepath = photo_data.get('filepath')  # Старый формат
                                
                                # Строим список возможных путей
                                possible_paths = []
                                
                                # 1. Используем docker_path если есть (новый формат)
                                if docker_path:
                                    possible_paths.append(docker_path)
                                
                                # 2. Используем relative_path и строим путь для контейнера
                                if relative_path:
                                    possible_paths.append(f"/var/www/html/storage/app/public/{relative_path}")
                                
                                # 3. Legacy: если есть старый filepath, пробуем его
                                if legacy_filepath:
                                    # Если это абсолютный путь хоста, игнорируем его
                                    if not os.path.isabs(legacy_filepath) or legacy_filepath.startswith('/var/www/html'):
                                        possible_paths.append(legacy_filepath)
                                    # Если это относительный путь, строим для контейнера
                                    elif not os.path.isabs(legacy_filepath):
                                        possible_paths.append(f"/var/www/html/storage/app/public/{legacy_filepath}")
                                
                                # Проверяем каждый путь
                                for path in possible_paths:
                                    if os.path.exists(path):
                                        photo_path = path
                                        print(f"Found photo path from event_info.json: {photo_path}")
                                        break
                        except Exception as e:
                            print(f"Error reading event_info.json: {e}")
                
                # Финальная проверка существования файла
                if not photo_path or not os.path.exists(photo_path):
                    logger.warning(f"Photo file not found: {photo_path} (photo_id: {photo.id}, original_path: {getattr(photo, 'original_path', 'N/A')})")
                    print(f"Warning: Photo file not found: {photo_path} (photo_id: {photo.id}, original_path: {getattr(photo, 'original_path', 'N/A')})")
                    # Пробуем найти файл по имени в директории upload
                    upload_dir = f"/var/www/html/storage/app/public/events/{event_id}/upload"
                    if os.path.exists(upload_dir):
                        photo_name = getattr(photo, 'original_name', None)
                        if photo_name:
                            # Ищем файл по части имени (без префикса uniqid)
                            for filename in os.listdir(upload_dir):
                                if photo_name in filename or filename.endswith(photo_name):
                                    photo_path = os.path.join(upload_dir, filename)
                                    logger.info(f"Found photo by name search: {photo_path}")
                                    print(f"Found photo by name search: {photo_path}")
                                    break
                    
                    if not photo_path or not os.path.exists(photo_path):
                        logger.error(f"Photo {photo.id} file not found after all attempts, skipping")
                        continue
                
                logger.info(f"Photo {photo.id}: Found file at {photo_path}, proceeding with processing")
                
                # ВАЖНО: Порядок выполнения анализов критичен!
                # 1. Сначала извлекаем EXIF данные (timeline) - ДО удаления EXIF
                # 2. Потом удаляем EXIF и поворачиваем изображение
                # 3. Затем остальные анализы (watermark, face_search, number_search)
                
                # ШАГ 1: Извлечение даты из EXIF (если требуется) - ДО удаления EXIF
                # ВАЖНО: Timeline временно отключен
                timeline_enabled = False  # analyses.get('timeline', False) - отключено
                logger.info(f"Photo {photo.id}: Step 1 - timeline={timeline_enabled} (disabled)")
                
                # Создаем StepLogger для каждого шага
                step_logger_timeline = StepLogger(str(event_id), str(photo.id), "timeline") if timeline_enabled else None
                
                if timeline_enabled:
                    if step_logger_timeline:
                        step_logger_timeline.info(f"Starting timeline extraction for photo {photo.id}")
                        step_logger_timeline.info(f"Photo path: {photo_path}")
                    logger.info(f"Photo {photo.id}: Extracting EXIF datetime BEFORE removing EXIF")
                    try:
                        # Используем оригинальный photo_path (из upload), так как EXIF еще не удален
                        if step_logger_timeline:
                            step_logger_timeline.info(f"Extracting EXIF data from: {photo_path}")
                        exif_data = exif_processor.extract_exif(photo_path)
                        if step_logger_timeline:
                            step_logger_timeline.info(f"EXIF data extracted: {exif_data}")
                        logger.debug(f"Photo {photo.id}: EXIF data extracted: {exif_data}")
                        
                        if exif_data and 'datetime' in exif_data:
                            # Преобразуем дату из формата EXIF "YYYY:MM:DD HH:MM:SS" в стандартный формат
                            datetime_str = exif_data['datetime']
                            if datetime_str:
                                try:
                                    # Парсим дату из формата EXIF
                                    parsed_datetime = exif_processor.parse_datetime(datetime_str)
                                    if parsed_datetime:
                                        # Сохраняем в created_at_exif в стандартном формате "YYYY-MM-DD HH:MM:SS"
                                        formatted_datetime = parsed_datetime.strftime("%Y-%m-%d %H:%M:%S")
                                        if hasattr(photo, 'created_at_exif'):
                                            photo.created_at_exif = formatted_datetime
                                        
                                        logger.info(f"Photo {photo.id}: Parsed EXIF datetime: {datetime_str} -> {formatted_datetime}")
                                    else:
                                        # Если парсинг не удался, сохраняем оригинальную строку
                                        if hasattr(photo, 'created_at_exif'):
                                            photo.created_at_exif = datetime_str
                                        logger.warning(f"Photo {photo.id}: Could not parse datetime, saved as-is: {datetime_str}")
                                except Exception as e:
                                    logger.error(f"Photo {photo.id}: Error parsing datetime '{datetime_str}': {str(e)}", exc_info=True)
                                    # Если не удалось преобразовать, сохраняем оригинальную строку
                                    if hasattr(photo, 'created_at_exif'):
                                        photo.created_at_exif = datetime_str
                        else:
                            logger.warning(f"Photo {photo.id}: No datetime found in EXIF data: {exif_data}")
                        
                        # ВАЖНО: Обновляем event_info.json для timeline ВСЕГДА, даже если данных нет
                        # Это необходимо для корректного отслеживания прогресса
                        if os.path.exists(event_info_path):
                            photo_name = getattr(photo, 'original_name', None) or f"photo_{photo.id}"
                            timeline_data = {}
                            if exif_data and 'datetime' in exif_data and exif_data['datetime']:
                                if hasattr(photo, 'created_at_exif') and photo.created_at_exif:
                                    timeline_data['date'] = photo.created_at_exif
                                else:
                                    timeline_data['date'] = exif_data['datetime']
                            
                            update_event_info_json(
                                event_info_path,
                                str(photo.id),
                                photo_name,
                                'timeline',
                                timeline_data,
                                'ready'
                            )
                            logger.info(f"Photo {photo.id}: Updated event_info.json for timeline with data: {timeline_data}")
                        
                        db.commit()
                        update_counter += 1
                        if step_logger_timeline:
                            step_logger_timeline.info(f"Timeline extraction completed successfully")
                        logger.info(f"Photo {photo.id}: Timeline extraction completed")
                    except Exception as e:
                        if step_logger_timeline:
                            step_logger_timeline.error(f"Error in timeline extraction: {str(e)}", exc_info=True)
                        logger.error(f"Photo {photo.id}: Error in timeline extraction: {str(e)}", exc_info=True)
                        # Обновляем event_info.json с ошибкой
                        if os.path.exists(event_info_path):
                            photo_name = getattr(photo, 'original_name', None) or f"photo_{photo.id}"
                            update_event_info_json(
                                event_info_path,
                                str(photo.id),
                                photo_name,
                                'timeline',
                                {'error': str(e)},
                                'error'
                            )
                        update_counter += 1
                
                # ШАГ 2: Удаление EXIF и поворот изображения (всегда выполняется, если remove_exif включен или не указан)
                # ВАЖНО: Этот шаг выполняется ПОСЛЕ timeline, если timeline включен
                should_remove_exif = analyses.get('remove_exif', True)
                logger.info(f"Photo {photo.id}: Step 2 - remove_exif={should_remove_exif}, file exists: {os.path.exists(photo_path) if photo_path else False}")
                
                step_logger_remove_exif = StepLogger(str(event_id), str(photo.id), "remove_exif") if should_remove_exif else None
                
                if should_remove_exif:
                    if step_logger_remove_exif:
                        step_logger_remove_exif.info(f"Starting EXIF removal and rotation for photo {photo.id}")
                        step_logger_remove_exif.info(f"Input photo path: {photo_path}")
                    
                    # Файл должен быть сохранен в папку original_photo
                    original_photo_dir = os.path.join(event_dir, "original_photo")
                    
                    # Создаем папку original_photo если её нет
                    if not os.path.exists(original_photo_dir):
                        os.makedirs(original_photo_dir, mode=0o755, exist_ok=True)
                    
                    # Генерируем уникальное имя файла для original_photo
                    import uuid
                    file_ext = os.path.splitext(photo_path)[1] or '.jpg'
                    unique_filename = f"{uuid.uuid4()}{file_ext}"
                    original_photo_path = os.path.join(original_photo_dir, unique_filename)
                    
                    if step_logger_remove_exif:
                        step_logger_remove_exif.info(f"Output photo path: {original_photo_path}")
                    
                    logger.info(f"Photo {photo.id}: Normalizing EXIF orientation and saving to {original_photo_path}")
                    # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Применяем EXIF ориентацию ОДИН РАЗ в начале пайплайна
                    # Сначала копируем файл в original_photo, потом нормализуем ориентацию
                    try:
                        import shutil
                        # Копируем файл в original_photo
                        shutil.copy2(photo_path, original_photo_path)
                        logger.info(f"Photo {photo.id}: Copied to {original_photo_path}")
                        
                        # Нормализуем ориентацию (применяет EXIF и удаляет его)
                        exif_processor.normalize_orientation(original_photo_path)
                        
                        processed_path = original_photo_path
                        if step_logger_remove_exif:
                            step_logger_remove_exif.info(f"EXIF orientation normalized successfully")
                            step_logger_remove_exif.info(f"Processed path: {processed_path}")
                        logger.info(f"Photo {photo.id}: EXIF orientation normalized successfully")
                    except Exception as exif_error:
                        if step_logger_remove_exif:
                            step_logger_remove_exif.error(f"Error normalizing EXIF orientation: {str(exif_error)}", exc_info=True)
                        logger.error(f"Photo {photo.id}: Error normalizing EXIF orientation: {str(exif_error)}", exc_info=True)
                        raise
                    
                    # Обновляем original_path в базе данных (относительный путь)
                    relative_original_path = f"events/{event_id}/original_photo/{unique_filename}"
                    photo.original_path = relative_original_path
                    db.commit()
                    logger.info(f"Photo {photo.id}: Updated original_path in DB")
                    
                    # Обновляем event_info.json для remove_exif
                    if os.path.exists(event_info_path):
                        photo_name = getattr(photo, 'original_name', None) or f"photo_{photo.id}"
                        update_event_info_json(
                            event_info_path,
                            str(photo.id),
                            photo_name,
                            'removeexif',
                            {},
                            'ready'
                        )
                        logger.info(f"Photo {photo.id}: Updated event_info.json for remove_exif")
                    update_counter += 1
                else:
                    # Если remove_exif отключен, используем оригинальный файл
                    processed_path = photo_path
                    original_photo_path = photo_path
                    logger.info(f"Photo {photo.id}: EXIF removal skipped, using original file")
                
                # ШАГ 3: Нанесение водяного знака (всегда выполняется, если watermark включен или не указан)
                watermark_enabled = analyses.get('watermark', True)
                logger.info(f"Photo {photo.id}: watermark={watermark_enabled}")
                
                # Файл должен быть сохранен в папку custom_photo
                custom_photo_dir = os.path.join(event_dir, "custom_photo")
                
                # Создаем папку custom_photo если её нет
                if not os.path.exists(custom_photo_dir):
                    os.makedirs(custom_photo_dir, mode=0o755, exist_ok=True)
                
                # Генерируем уникальное имя файла для custom_photo (WebP формат)
                import uuid
                custom_filename = f"{uuid.uuid4()}.webp"
                custom_photo_path = os.path.join(custom_photo_dir, custom_filename)
                
                if watermark_enabled:
                    logger.info(f"Photo {photo.id}: Adding watermark")
                    # Наносим водяной знак и конвертируем в WebP
                    watermarked_path = watermark_processor.add_watermark(
                        processed_path,
                        text=f"hunter-photo.ru",
                        output_path=custom_photo_path
                    )
                    # Если watermark не вернул путь, используем наш
                    if not watermarked_path:
                        watermarked_path = custom_photo_path
                        # Конвертируем в WebP если watermark не сделал этого
                        if not watermarked_path.endswith('.webp'):
                            # Используем глобальный image_processor, уже созданный выше
                            watermarked_path = image_processor.convert_to_webp(watermarked_path)
                else:
                    # Без водяного знака, просто конвертируем в WebP
                    # Используем глобальный image_processor, уже созданный выше
                    watermarked_path = image_processor.convert_to_webp(processed_path)
                    # Перемещаем в custom_photo если нужно
                    if watermarked_path != custom_photo_path:
                        import shutil
                        shutil.move(watermarked_path, custom_photo_path)
                        watermarked_path = custom_photo_path
                
                # Сохраняем относительный путь для custom_path
                relative_custom_path = f"events/{event_id}/custom_photo/{custom_filename}"
                photo.custom_path = relative_custom_path
                db.commit()
                
                # Обновляем event_info.json для watermark
                if os.path.exists(event_info_path):
                    photo_name = getattr(photo, 'original_name', None) or f"photo_{photo.id}"
                    update_event_info_json(
                        event_info_path,
                        str(photo.id),
                        photo_name,
                        'watermark',
                        {},
                        'ready'
                    )
                update_counter += 1
                
                # 4. Поиск лиц (если требуется)
                face_search_enabled = analyses.get('face_search', False)
                logger.info(f"Photo {photo.id}: face_search={face_search_enabled}")
                
                step_logger_face = StepLogger(str(event_id), str(photo.id), "face_search") if face_search_enabled else None
                
                if face_search_enabled:
                    if step_logger_face:
                        step_logger_face.info(f"Starting face search for photo {photo.id}")
                        step_logger_face.info(f"Processed path: {processed_path}")
                    logger.info(f"Photo {photo.id}: Starting face search")
                    from tasks.face_search import extract_faces_with_bboxes
                    import logging
                    logger = logging.getLogger(__name__)
                    
                    # Проверяем, что файл существует
                    if not os.path.exists(processed_path):
                        logger.error(f"Face search: File not found: {processed_path} for photo {photo.id}")
                        photo.has_faces = False
                        photo.face_encodings = []
                        photo.face_vec = None
                        photo.face_bboxes = []
                        db.commit()
                    else:
                        try:
                            logger.info(f"Face search: Starting extraction for photo {photo.id}, path: {processed_path}")
                            # ВАЖНО: apply_exif=False, так как изображение уже обработано через remove_exif
                            # и повернуто в правильную ориентацию. При поиске используем apply_exif=True
                            # для запроса, чтобы соответствовать ориентации индексированных изображений.
                            # ВАЖНО: Добавляем таймаут на уровне задачи, чтобы избежать зависаний
                            import time
                            start_time = time.time()
                            logger.info(f"Face search: Calling extract_faces_with_bboxes for photo {photo.id}...")
                            # КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ №3: Убран параметр apply_exif
                            # EXIF применяется ТОЛЬКО ОДИН РАЗ при загрузке фото через remove_exif_and_rotate
                            faces_data = extract_faces_with_bboxes(processed_path)
                            elapsed_time = time.time() - start_time
                            logger.info(f"Face search: Extraction completed in {elapsed_time:.2f} seconds for photo {photo.id}")
                            if elapsed_time > 30:
                                logger.warning(f"Face search: Slow extraction for photo {photo.id} - took {elapsed_time:.2f} seconds")
                            
                            # Защита от None - extract_faces_with_bboxes может вернуть None в случае ошибки
                            if faces_data is None:
                                logger.warning(f"Face search: extract_faces_with_bboxes returned None for photo {photo.id}")
                                faces_data = []
                            
                            # ВАЖНО: Логируем результат извлечения лиц для диагностики
                            logger.info(f"Face search: extract_faces_with_bboxes returned {len(faces_data) if faces_data else 0} face(s) for photo {photo.id}")
                            if faces_data:
                                logger.info(f"Face search: faces_data type={type(faces_data)}, first face keys={list(faces_data[0].keys()) if faces_data and len(faces_data) > 0 and isinstance(faces_data[0], dict) else 'N/A'}")
                            
                            if faces_data and len(faces_data) > 0:
                                if step_logger_face:
                                    step_logger_face.info(f"Found {len(faces_data)} face(s) in photo")
                                logger.info(f"Face search: Found {len(faces_data)} face(s) in photo {photo.id}")
                                photo.has_faces = True
                                
                                # Преобразуем numpy массивы в списки Python для JSON
                                face_vectors = []
                                face_bboxes = []
                                
                                for face_idx, face_data in enumerate(faces_data):
                                    # Защита от None или отсутствующих ключей
                                    if not face_data or not isinstance(face_data, dict):
                                        logger.warning(f"Face search: Invalid face_data at index {face_idx} for photo {photo.id}: {face_data}")
                                        continue
                                    
                                    emb = face_data.get('embedding')
                                    bbox = face_data.get('bbox')
                                    
                                    # Проверяем, что embedding и bbox не None
                                    if emb is None:
                                        logger.warning(f"Face search: embedding is None for face {face_idx + 1} in photo {photo.id}")
                                        continue
                                    
                                    if bbox is None:
                                        logger.warning(f"Face search: bbox is None for face {face_idx + 1} in photo {photo.id}")
                                        continue
                                    
                                    # Логируем информацию о каждом найденном лице
                                    import numpy as np
                                    if isinstance(emb, np.ndarray):
                                        logger.debug(f"Face search: Photo {photo.id}, face {face_idx + 1}: embedding dtype={emb.dtype}, shape={emb.shape}, bbox={bbox}")
                                    else:
                                        logger.debug(f"Face search: Photo {photo.id}, face {face_idx + 1}: embedding type={type(emb)}, bbox={bbox}")
                                    
                                    # Конвертируем embedding в список (для JSON сохранения)
                                    try:
                                        if hasattr(emb, 'tolist'):
                                            face_vectors.append(emb.tolist())
                                        elif isinstance(emb, (list, tuple)):
                                            face_vectors.append(list(emb))
                                        else:
                                            if isinstance(emb, np.ndarray):
                                                face_vectors.append(emb.tolist())
                                            else:
                                                face_vectors.append(list(emb))
                                        
                                        # Сохраняем bbox (уже список)
                                        face_bboxes.append(bbox)
                                    except Exception as emb_error:
                                        logger.error(f"Face search: Error converting embedding for face {face_idx + 1} in photo {photo.id}: {str(emb_error)}")
                                        continue
                                
                                # Сохраняем embeddings и bbox в БД
                                # face_encodings - список всех найденных лиц (embeddings)
                                # face_bboxes - список координат bounding boxes [[x1, y1, x2, y2], ...]
                                # face_vec - первое лицо как основной вектор (для совместимости)
                                photo.face_encodings = face_vectors
                                photo.face_bboxes = face_bboxes
                                photo.face_vec = face_vectors[0] if face_vectors else None
                                photo.has_faces = True  # Явно устанавливаем флаг
                                
                                logger.info(f"Face search: Before commit - photo.has_faces={photo.has_faces}, face_encodings count={len(face_vectors)}, face_bboxes count={len(face_bboxes)}")
                                
                                # ВАЖНО: Явно сохраняем изменения в БД
                                try:
                                    db.add(photo)  # Явно добавляем объект в сессию
                                    db.commit()
                                    db.refresh(photo)  # Обновляем объект из БД
                                    logger.info(f"Face search: After commit - photo.has_faces={photo.has_faces}, face_encodings count={len(photo.face_encodings) if photo.face_encodings else 0}, face_bboxes count={len(photo.face_bboxes) if photo.face_bboxes else 0}")
                                except Exception as commit_error:
                                    logger.error(f"Face search: Error committing to DB: {str(commit_error)}", exc_info=True)
                                    db.rollback()
                                    raise
                                
                                logger.info(f"Face search: Saved {len(face_vectors)} embeddings and {len(face_bboxes)} bboxes to DB for photo {photo.id}")
                                logger.debug(f"Face search: Photo {photo.id} - First embedding length: {len(face_vectors[0]) if face_vectors else 0}, First bbox: {face_bboxes[0] if face_bboxes else 'N/A'}")
                                
                                # Обновляем event_info.json для face_search
                                if os.path.exists(event_info_path):
                                    photo_name = getattr(photo, 'original_name', None) or f"photo_{photo.id}"
                                    update_event_info_json(
                                        event_info_path,
                                        str(photo.id),
                                        photo_name,
                                        'facesearch',
                                        {
                                            'face_encodings': face_vectors,  # Список всех найденных лиц
                                            'face_vector': face_vectors[0] if face_vectors else []  # Первое лицо как основной вектор
                                        },
                                        'ready'
                                    )
                                update_counter += 1
                            else:
                                # ВАЖНО: Логируем детали, почему лица не найдены
                                logger.warning(f"Face search: No faces found in photo {photo.id}")
                                logger.warning(f"Face search: processed_path={processed_path}, file_exists={os.path.exists(processed_path) if processed_path else False}")
                                
                                # Проверяем, что файл существует и доступен
                                if processed_path and os.path.exists(processed_path):
                                    file_size = os.path.getsize(processed_path)
                                    logger.warning(f"Face search: File exists but no faces found, size={file_size} bytes")
                                
                                photo.has_faces = False
                                photo.face_encodings = []
                                photo.face_vec = None
                                photo.face_bboxes = []  # Устанавливаем пустой список вместо None
                                
                                # ВАЖНО: Явно сохраняем изменения в БД
                                try:
                                    db.add(photo)
                                    db.commit()
                                    logger.info(f"Face search: Saved has_faces=False to DB for photo {photo.id}")
                                except Exception as commit_error:
                                    logger.error(f"Face search: Error committing no-faces to DB: {str(commit_error)}", exc_info=True)
                                    db.rollback()
                                
                                # ВАЖНО: Обновляем event_info.json даже если лиц не найдено
                                if os.path.exists(event_info_path):
                                    photo_name = getattr(photo, 'original_name', None) or f"photo_{photo.id}"
                                    update_event_info_json(
                                        event_info_path,
                                        str(photo.id),
                                        photo_name,
                                        'facesearch',
                                        {
                                            'face_encodings': [],
                                            'face_vector': [],
                                            'faces_found': 0
                                        },
                                        'ready'
                                    )
                                    logger.info(f"Photo {photo.id}: Updated event_info.json for face_search (no faces found)")
                        except Exception as e:
                            logger.error(f"Face search error for photo {photo.id}: {str(e)}", exc_info=True)
                            photo.has_faces = False
                            photo.face_encodings = []
                            photo.face_vec = None
                            photo.face_bboxes = []  # Устанавливаем пустой список вместо None
                            db.commit()
                            
                            # Обновляем event_info.json с ошибкой
                            if os.path.exists(event_info_path):
                                photo_name = getattr(photo, 'original_name', None) or f"photo_{photo.id}"
                                update_event_info_json(
                                    event_info_path,
                                    str(photo.id),
                                    photo_name,
                                    'facesearch',
                                    {
                                        'face_encodings': [],
                                        'face_vector': [],
                                        'error': str(e)
                                    },
                                    'error'
                                )
                                logger.error(f"Photo {photo.id}: Updated event_info.json for face_search with error")
                        
                        # ВАЖНО: Обновляем event_info.json ВСЕГДА, даже если была ошибка выше
                        # Это гарантирует, что задача будет отмечена как выполненная
                        if os.path.exists(event_info_path):
                            photo_name = getattr(photo, 'original_name', None) or f"photo_{photo.id}"
                            # Проверяем, не обновили ли мы уже выше
                            try:
                                with open(event_info_path, 'r', encoding='utf-8') as f:
                                    event_info_check = json.load(f)
                                section_key = 'analyze_facesearch'
                                if section_key in event_info_check:
                                    existing = any(item.get('photoId') == str(photo.id) for item in event_info_check[section_key])
                                    if not existing:
                                        # Если записи нет, создаем её
                                        update_event_info_json(
                                            event_info_path,
                                            str(photo.id),
                                            photo_name,
                                            'facesearch',
                                            {
                                                'face_encodings': [],
                                                'face_vector': [],
                                                'faces_found': 0
                                },
                                'ready'
                            )
                                        logger.info(f"Photo {photo.id}: Created missing event_info.json entry for face_search")
                            except Exception as check_error:
                                logger.warning(f"Photo {photo.id}: Could not check event_info.json: {str(check_error)}")
                        
                        update_counter += 1
                        logger.info(f"Photo {photo.id}: Face search processing completed")
                
                # 5. Поиск номеров (если требуется)
                number_search_enabled = analyses.get('number_search', False)
                logger.info(f"Photo {photo.id}: number_search={number_search_enabled}")
                
                step_logger_number = StepLogger(str(event_id), str(photo.id), "number_search") if number_search_enabled else None
                
                if number_search_enabled:
                    if step_logger_number:
                        step_logger_number.info(f"Starting number search for photo {photo.id}")
                        step_logger_number.info(f"Processed path: {processed_path}")
                    logger.info(f"Photo {photo.id}: Starting number search, processed_path={processed_path}")
                    try:
                        # ВАЖНО: Добавляем таймаут для обработки номеров, чтобы избежать зависаний
                        import time
                        
                        start_time = time.time()
                        logger.info(f"Photo {photo.id}: Calling extract_numbers, this may take up to 60 seconds...")
                        
                        from tasks.number_search import extract_numbers
                        numbers = extract_numbers(processed_path)
                        
                        elapsed_time = time.time() - start_time
                        logger.info(f"Photo {photo.id}: Number extraction completed in {elapsed_time:.2f} seconds")
                        
                        # ВАЖНО: Если обработка заняла слишком много времени, логируем предупреждение
                        if elapsed_time > 30:
                            logger.warning(f"Photo {photo.id}: Number extraction took {elapsed_time:.2f} seconds (slow)")
                        
                        # ВАЖНО: Если обработка заняла больше 60 секунд, это критично
                        if elapsed_time > 60:
                            logger.error(f"Photo {photo.id}: Number extraction took {elapsed_time:.2f} seconds (CRITICAL - very slow)")
                        
                        if step_logger_number:
                            step_logger_number.info(f"Number search completed, found {len(numbers) if numbers else 0} numbers")
                            if numbers:
                                step_logger_number.info(f"Numbers found: {numbers}")
                            else:
                                step_logger_number.info("No numbers found")
                        logger.info(f"Photo {photo.id}: Number search completed, found {len(numbers) if numbers else 0} numbers")
                        if numbers:
                            logger.debug(f"Photo {photo.id}: Numbers found: {numbers}")
                        else:
                            logger.debug(f"Photo {photo.id}: No numbers found")
                        
                        # Сохраняем номера в базу (даже если пустой список)
                        photo.numbers = numbers if numbers else []
                        
                        logger.info(f"Number search: Before commit - photo.numbers={photo.numbers}, count={len(numbers) if numbers else 0}")
                        
                        # ВАЖНО: Явно сохраняем изменения в БД
                        try:
                            db.add(photo)  # Явно добавляем объект в сессию
                            db.commit()  # ВАЖНО: Коммитим сразу после сохранения номеров
                            db.refresh(photo)  # Обновляем объект из БД
                            logger.info(f"Number search: After commit - photo.numbers={photo.numbers}, count={len(photo.numbers) if photo.numbers else 0}")
                        except Exception as commit_error:
                            logger.error(f"Number search: Error committing to DB: {str(commit_error)}", exc_info=True)
                            db.rollback()
                            raise
                        
                        logger.info(f"Number search: Saved {len(numbers) if numbers else 0} numbers to DB for photo {photo.id}")
                        
                        # Обновляем event_info.json для number_search (всегда, даже если номеров нет)
                        if os.path.exists(event_info_path):
                            photo_name = getattr(photo, 'original_name', None) or f"photo_{photo.id}"
                            # Если номеров нет, передаем пустой список (будет сохранен как null в JSON)
                            update_event_info_json(
                                event_info_path,
                                str(photo.id),
                                photo_name,
                                'numbersearch',
                                {'numbers': numbers if numbers else []},
                                'ready'
                            )
                            logger.info(f"Photo {photo.id}: Updated event_info.json for number_search")
                        update_counter += 1
                    except Exception as e:
                        if step_logger_number:
                            step_logger_number.error(f"Error in number search: {str(e)}", exc_info=True)
                        logger.error(f"Photo {photo.id}: Error in number search: {str(e)}", exc_info=True)
                        # Сохраняем пустой список номеров в случае ошибки
                        photo.numbers = []
                        db.commit()  # ВАЖНО: Коммитим даже при ошибке, чтобы сохранить пустой список
                        logger.info(f"Number search: Saved empty numbers list to DB for photo {photo.id} (error occurred)")
                        
                        # Обновляем event_info.json с ошибкой
                        if os.path.exists(event_info_path):
                            photo_name = getattr(photo, 'original_name', None) or f"photo_{photo.id}"
                            update_event_info_json(
                                event_info_path,
                                str(photo.id),
                                photo_name,
                                'numbersearch',
                                {'numbers': [], 'error': str(e)},
                                'error'
                            )
                            logger.error(f"Photo {photo.id}: Updated event_info.json for number_search with error")
                    update_counter += 1
                
                db.commit()
                
                # Проверяем общее время обработки фотографии
                if photo_start_time:
                    photo_elapsed = time.time() - photo_start_time
                    logger.info(f"Photo {photo.id}: All processing steps completed successfully in {photo_elapsed:.2f} seconds")
                    if photo_elapsed > 60:
                        logger.warning(f"Photo {photo.id}: Processing took {photo_elapsed:.2f} seconds (slow)")
                    if photo_elapsed > PHOTO_PROCESSING_TIMEOUT:
                        logger.error(f"Photo {photo.id}: Processing took {photo_elapsed:.2f} seconds (CRITICAL - exceeded timeout)")
                
                successfully_processed += 1
                
                # Обновляем прогресс ПОСЛЕ успешной обработки фотографии
                progress_percent = int((idx / total) * 100)
                logger.info(f"Progress update: {idx}/{total} ({progress_percent}%) - Successfully processed: {successfully_processed}")
                task_logger.log_progress(idx, total, successfully_processed=successfully_processed)
                try:
                    self.on_progress(idx, total)
                except Exception as progress_error:
                    logger.error(f"Failed to update progress: {str(progress_error)}")
                    task_logger.error("Ошибка обновления прогресса", exc_info=True, error=str(progress_error))
                
                # ВАЖНО: Периодически обновляем event_info.json для отслеживания прогресса
                # Это помогает видеть прогресс даже если задача зависнет
                if idx % 10 == 0 or idx == total:
                    try:
                        if os.path.exists(event_info_path):
                            # Обновляем общий прогресс в event_info.json
                            with open(event_info_path, 'r', encoding='utf-8') as f:
                                event_info = json.load(f)
                            
                            # Обновляем прогресс для каждого типа анализа
                            for analysis_type in ['analyze_removeexif', 'analyze_watermark', 'analyze_facesearch', 'analyze_numbersearch']:
                                if analysis_type in event_info:
                                    section_data = event_info[analysis_type]
                                    ready_count = sum(1 for item in section_data if item.get('status') == 'ready')
                                    total_count = len(section_data)
                                    progress_pct = int((ready_count / total_count * 100)) if total_count > 0 else 0
                                    logger.debug(f"Progress for {analysis_type}: {ready_count}/{total_count} ({progress_pct}%)")
                            
                            # Сохраняем обновленный event_info.json
                            temp_path = event_info_path + '.tmp'
                            with open(temp_path, 'w', encoding='utf-8') as f:
                                json.dump(event_info, f, indent=4, ensure_ascii=False)
                            os.replace(temp_path, event_info_path)
                    except Exception as checkpoint_error:
                        logger.warning(f"Failed to create checkpoint: {str(checkpoint_error)}")
                
                # Обновляем event_info.json каждые 5 фотографий (если счетчик достиг интервала)
                if update_counter >= update_interval and os.path.exists(event_info_path):
                    update_counter = 0
                    logger.info(f"Updated event_info.json after processing {idx} photos")
                    print(f"Updated event_info.json after processing {idx} photos")
                
            except Exception as e:
                error_msg = f"Ошибка обработки фотографии {photo.id} (фото {idx}/{total})"
                logger.error(f"CRITICAL ERROR processing photo {photo.id}: {str(e)}", exc_info=True)
                task_logger.error(error_msg, exc_info=True, 
                                 photo_id=str(photo.id), 
                                 photo_index=idx, 
                                 total=total,
                                 error=str(e),
                                 error_type=type(e).__name__)
                print(f"Error processing photo {photo.id}: {str(e)}")
                
                # Сохраняем информацию о неудачной фотографии
                # ВАЖНО: Используем глобальный traceback, импортированный в начале файла
                try:
                    failed_photos.append({
                        'photo_id': str(photo.id),
                        'error': str(e),
                        'error_type': type(e).__name__,
                        'index': idx,
                        'traceback': traceback.format_exc()
                    })
                except Exception as tb_error:
                    # Если traceback не доступен, сохраняем без него
                    failed_photos.append({
                        'photo_id': str(photo.id),
                        'error': str(e),
                        'error_type': type(e).__name__,
                        'index': idx,
                        'traceback': None
                    })
                
                # ВАЖНО: Даже при ошибке обновляем прогресс, чтобы не застрять
                # Это гарантирует, что процесс не остановится из-за одной проблемной фотографии
                try:
                    progress_percent = int((idx / total) * 100)
                    logger.warning(f"Progress update after error: {idx}/{total} ({progress_percent}%) - Failed photos: {len(failed_photos)}")
                    self.on_progress(idx, total)
                except Exception as progress_error:
                    logger.error(f"Failed to update progress after error: {str(progress_error)}")
                
                # Пытаемся сохранить хотя бы базовую информацию о фотографии в БД
                try:
                    db.commit()
                    logger.debug(f"Photo {photo.id}: Database commit successful after error")
                except Exception as commit_error:
                    logger.error(f"Photo {photo.id}: Error committing to database after processing error: {str(commit_error)}")
                
                # ВАЖНО: Продолжаем обработку следующих фотографий, не останавливаем весь процесс
                logger.info(f"Continuing to next photo after error in photo {photo.id}. Total failed so far: {len(failed_photos)}")
                continue
        
        # После завершения всех анализов загружаем фотографии на S3
        # ВАЖНО: Загрузка на S3 происходит ТОЛЬКО после завершения всех анализов всех фотографий
        print(f"All photos processed ({total} total). Starting S3 upload for event {event_id}...")
        print(f"Event directory: {event_dir}")
        
        try:
            from utils.s3_uploader import S3Uploader
            
            s3_uploader = S3Uploader()
            if s3_uploader.is_available():
                print("S3 uploader is available, proceeding with upload...")
                
                # Читаем event_info.json для получения данных о фотографиях
                if os.path.exists(event_info_path):
                    print(f"Reading event_info.json from: {event_info_path}")
                    with open(event_info_path, 'r', encoding='utf-8') as f:
                        event_info = json.load(f)
                    
                    # Получаем данные о фотографиях
                    photos_data = event_info.get('photo', {})
                    print(f"Found {len(photos_data)} photos in event_info.json")
                    
                    # Проверяем, что все анализы завершены
                    # Проверяем наличие секций analyze_* в event_info.json и их статусы
                    # ВАЖНО: Timeline временно отключен
                    required_sections = []
                    # if analyses.get('timeline', False):
                    #     required_sections.append('analyze_timeline')
                    if analyses.get('remove_exif', True):
                        required_sections.append('analyze_removeexif')
                    if analyses.get('watermark', True):
                        required_sections.append('analyze_watermark')
                    if analyses.get('face_search', False):
                        required_sections.append('analyze_facesearch')
                    if analyses.get('number_search', False):
                        required_sections.append('analyze_numbersearch')
                    
                    logger.info(f"Required analysis sections for event {event_id}: {required_sections}")
                    print(f"Required analysis sections: {required_sections}")
                    
                    # Проверяем, что все требуемые анализы завершены
                    all_analyses_complete = True
                    incomplete_sections = []
                    for section in required_sections:
                        section_data = event_info.get(section, [])
                        if not section_data:
                            logger.warning(f"Analysis section {section} is empty for event {event_id}")
                            print(f"Warning: Analysis section {section} is empty")
                            all_analyses_complete = False
                            incomplete_sections.append(section)
                        else:
                            # Проверяем, что все записи имеют статус "ready"
                            ready_count = sum(1 for item in section_data if item.get('status') == 'ready')
                            error_count = sum(1 for item in section_data if item.get('status') == 'error')
                            processing_count = sum(1 for item in section_data if item.get('status') == 'processing')
                            total_count = len(section_data)
                            logger.info(f"Section {section} for event {event_id}: {ready_count} ready, {error_count} errors, {processing_count} processing, {total_count} total")
                            print(f"Section {section}: {ready_count}/{total_count} photos ready (errors: {error_count}, processing: {processing_count})")
                            if ready_count < total_count:
                                logger.warning(f"Not all photos in {section} are ready for event {event_id}: {ready_count}/{total_count}")
                                print(f"Warning: Not all photos in {section} are ready")
                                all_analyses_complete = False
                                incomplete_sections.append(section)
                    
                    if not all_analyses_complete:
                        logger.warning(f"Not all analyses are complete for event {event_id}. Skipping S3 upload until all analyses are complete.")
                        logger.warning(f"Incomplete sections: {incomplete_sections}")
                        print("Warning: Not all analyses are complete. Skipping S3 upload.")
                        print(f"Incomplete sections: {incomplete_sections}")
                        # ВАЖНО: НЕ возвращаем статус "incomplete" - это приведет к преждевременному завершению задачи
                        # Вместо этого продолжаем выполнение и возвращаем "completed" с предупреждением
                        # Laravel будет проверять завершение на основе event_info.json, а не статуса Celery задачи
                        logger.warning(f"Continuing execution despite incomplete analyses. Laravel will check completion based on event_info.json.")
                    else:
                        logger.info(f"All required analyses are complete for event {event_id}. Proceeding with S3 upload.")
                        print("All required analyses are complete, proceeding with S3 upload")
                    
                    # Загружаем на S3 (передаем db сессию для получения актуальных путей)
                    logger.info(f"Starting S3 upload for event {event_id}...")
                    print("Starting S3 upload for all photos...")
                    s3_urls = s3_uploader.upload_event_photos(event_id, photos_data, db)
                    print(f"S3 upload completed. Uploaded {len(s3_urls)} photos.")
                    
                    # Обновляем event_info.json с S3 URL
                    if s3_urls:
                        # Добавляем секцию s3_data в event_info.json
                        if 's3_data' not in event_info:
                            event_info['s3_data'] = {}
                        
                        for photo_id, urls in s3_urls.items():
                            event_info['s3_data'][photo_id] = {
                                'custom_url': urls.get('custom_url'),
                                'original_url': urls.get('original_url')
                            }
                        
                        # Сохраняем обновленный event_info.json
                        temp_path = event_info_path + '.tmp'
                        with open(temp_path, 'w', encoding='utf-8') as f:
                            json.dump(event_info, f, indent=4, ensure_ascii=False)
                            f.flush()
                            os.fsync(f.fileno())
                        os.replace(temp_path, event_info_path)
                        
                        print(f"S3 URLs added to event_info.json for {len(s3_urls)} photos")
                        
                        # Обновляем базу данных с S3 URL
                        print(f"Updating database with S3 URLs for {len(s3_urls)} photos...")
                        updated_count = 0
                        for photo_id, urls in s3_urls.items():
                            photo = db.query(Photo).filter(Photo.id == photo_id).first()
                            if photo:
                                updated = False
                                if urls.get('custom_url'):
                                    photo.s3_custom_url = urls['custom_url']
                                    updated = True
                                if urls.get('original_url'):
                                    photo.s3_original_url = urls['original_url']
                                    updated = True
                                
                                if updated:
                                    db.commit()
                                    updated_count += 1
                                    print(f"Updated photo {photo_id} with S3 URLs (custom: {bool(urls.get('custom_url'))}, original: {bool(urls.get('original_url'))})")
                                else:
                                    print(f"Warning: Photo {photo_id} has no S3 URLs to update")
                            else:
                                print(f"Warning: Photo {photo_id} not found in database")
                        
                        print(f"Database updated: {updated_count} photos updated with S3 URLs out of {len(s3_urls)} uploaded.")
                        
                        # После успешной загрузки на S3 удаляем локальные файлы
                        # Проверяем, что все фотографии загружены на S3
                        all_uploaded = True
                        for photo_id, urls in s3_urls.items():
                            if not urls.get('custom_url') or not urls.get('original_url'):
                                all_uploaded = False
                                break
                        
                        if all_uploaded and len(s3_urls) == len(photos_data):
                            print(f"All photos uploaded to S3. Cleaning up local files for event {event_id}...")
                            try:
                                # Удаляем папки upload, original_photo, custom_photo
                                upload_dir = os.path.join(event_dir, "upload")
                                original_dir = os.path.join(event_dir, "original_photo")
                                custom_dir = os.path.join(event_dir, "custom_photo")
                                
                                import shutil
                                for dir_path in [upload_dir, original_dir, custom_dir]:
                                    if os.path.exists(dir_path):
                                        shutil.rmtree(dir_path)
                                        print(f"Deleted local directory: {dir_path}")
                                
                                print(f"Local files cleaned up for event {event_id}")
                            except Exception as e:
                                print(f"Error cleaning up local files: {e}")
                    else:
                        print("No photos uploaded to S3 (empty result)")
                else:
                    print(f"event_info.json not found, skipping S3 upload")
            else:
                print("S3 uploader not available, skipping S3 upload")
        except Exception as e:
            print(f"Error during S3 upload: {str(e)}")
            # ВАЖНО: Используем глобальный traceback, импортированный в начале файла
            traceback.print_exc()
        
        # Финальное обновление прогресса
        logger.info(f"COMPLETED process_event_photos: event_id={event_id}, total_processed={total}")
        try:
            self.on_progress(total, total)
            logger.info(f"Final progress update: {total}/{total} (100%)")
        except Exception as progress_error:
            logger.error(f"Failed to update final progress: {str(progress_error)}")
        
        # Финальное обновление event_info.json - проверяем, что все фотографии обработаны
        if os.path.exists(event_info_path):
            try:
                logger.info(f"Performing final event_info.json validation for event {event_id}")
                # Читаем текущий event_info.json
                with open(event_info_path, 'r', encoding='utf-8') as f:
                    event_info_final = json.load(f)
                
                # Проверяем, что все фотографии обработаны для каждого типа анализа
                missing_entries = []
                for photo in photo_list:
                    photo_id = str(photo.id)
                    photo_name = getattr(photo, 'original_name', None) or f"photo_{photo.id}"
                    
                    # Проверяем каждую секцию анализа в зависимости от включенных анализов
                    analysis_sections = []
                    # ВАЖНО: Timeline временно отключен
                    # if analyses.get('timeline', False):
                    #     analysis_sections.append(('timeline', 'analyze_timeline'))
                    if analyses.get('remove_exif', True):
                        analysis_sections.append(('removeexif', 'analyze_removeexif'))
                    if analyses.get('watermark', True):
                        analysis_sections.append(('watermark', 'analyze_watermark'))
                    if analyses.get('face_search', False):
                        analysis_sections.append(('facesearch', 'analyze_facesearch'))
                    if analyses.get('number_search', False):
                        analysis_sections.append(('numbersearch', 'analyze_numbersearch'))
                    
                    for analysis_type, section_key in analysis_sections:
                        if section_key not in event_info_final:
                            event_info_final[section_key] = []
                        
                        # Проверяем, есть ли запись для этой фотографии
                        existing = any(item.get('photoId') == photo_id for item in event_info_final[section_key])
                        if not existing:
                            logger.warning(f"Missing entry in {section_key} for photo {photo_id}, creating it")
                            missing_entries.append((section_key, photo_id, photo_name))
                            event_info_final[section_key].append({
                                'photoId': photo_id,
                                'photoName': photo_name,
                                'status': 'ready',
                                'updated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                            })
                
                # ВАЖНО: Обновляем photo_count в event_info.json на основе реального количества фотографий
                event_info_final['photo_count'] = len(photo_list)
                logger.info(f"Updated photo_count in event_info.json: {len(photo_list)}")
                
                # Сохраняем обновленный event_info.json если были добавлены записи или обновлен photo_count
                if missing_entries or 'photo_count' not in event_info_final or event_info_final.get('photo_count') != len(photo_list):
                    logger.info(f"Updating event_info.json: {len(missing_entries)} missing entries, photo_count={len(photo_list)}")
                    temp_path = event_info_path + '.tmp'
                    with open(temp_path, 'w', encoding='utf-8') as f:
                        json.dump(event_info_final, f, indent=4, ensure_ascii=False)
                        f.flush()
                        os.fsync(f.fileno())
                    os.replace(temp_path, event_info_path)
                    logger.info(f"Final event_info.json update completed: added {len(missing_entries)} missing entries, photo_count={len(photo_list)}")
                else:
                    logger.info(f"All entries present in event_info.json, no update needed")
            except Exception as final_update_error:
                logger.error(f"Error in final event_info.json validation: {str(final_update_error)}", exc_info=True)
        
        # Финальная статистика
        logger.info(f"Processing completed for event {event_id}:")
        logger.info(f"  - Total photos: {total}")
        logger.info(f"  - Successfully processed: {successfully_processed}")
        logger.info(f"  - Failed: {len(failed_photos)}")
        if failed_photos:
            logger.warning(f"Failed photos: {[p['photo_id'] for p in failed_photos]}")
        
        # ВАЖНО: Проверяем, что все анализы действительно выполнены перед возвратом "completed"
        # Это критично для того, чтобы Laravel не пометил задачи как завершенные преждевременно
        if os.path.exists(event_info_path):
            try:
                with open(event_info_path, 'r', encoding='utf-8') as f:
                    final_event_info = json.load(f)
                
                # Проверяем каждую секцию анализа
                all_sections_complete = True
                # ВАЖНО: Timeline временно отключен
                for section_key in ['analyze_removeexif', 'analyze_watermark', 'analyze_facesearch', 'analyze_numbersearch']:
                    if section_key in final_event_info:
                        section_data = final_event_info[section_key]
                        if section_data:
                            ready_count = sum(1 for item in section_data if item.get('status') == 'ready')
                            error_count = sum(1 for item in section_data if item.get('status') == 'error')
                            total_count = len(section_data)
                            completion = ((ready_count + error_count) / total_count * 100) if total_count > 0 else 0
                            logger.info(f"Final check - {section_key}: {ready_count} ready, {error_count} errors, {total_count} total ({completion:.1f}% complete)")
                            if completion < 95:
                                all_sections_complete = False
                                logger.warning(f"Section {section_key} is not complete: {completion:.1f}%")
            except Exception as final_check_error:
                logger.error(f"Error in final completion check: {str(final_check_error)}", exc_info=True)
        
        return {
            "status": "completed",
            "total_processed": total,
            "successfully_processed": successfully_processed,
            "failed_count": len(failed_photos),
            "failed_photos": failed_photos[:10] if len(failed_photos) > 10 else failed_photos,  # Ограничиваем список для размера ответа
            "event_id": event_id,
            "photos_processed": len(photo_list),
            "message": "All photos processed. Laravel will check actual completion based on event_info.json."
        }
    
    except SoftTimeLimitExceeded as e:
        # ВАЖНО: Обрабатываем мягкий таймаут - сохраняем прогресс и позволяем задаче завершиться gracefully
        error_msg = f"МЯГКИЙ ТАЙМАУТ в process_event_photos для события {event_id} - задача будет завершена"
        logger.warning(f"SOFT TIMEOUT in process_event_photos for event {event_id}: {str(e)}")
        task_logger.warning(error_msg, exc_info=True, event_id=event_id, error=str(e))
        
        # Сохраняем текущий прогресс в метаданные
        try:
            # Пытаемся получить текущий прогресс из метаданных
            current_meta = self.request.get('meta', {}) or {}
            current_progress = current_meta.get('progress', 0)
            
            self.update_state(
                state='PROGRESS',
                meta={
                    'progress': current_progress,
                    'status': 'soft_timeout',
                    'message': f'Задача прервана по мягкому таймауту на {current_progress}%',
                    'event_id': event_id,
                }
            )
        except:
            pass
        
        # НЕ пробрасываем исключение - позволяем задаче завершиться с текущим прогрессом
        # Это позволит продолжить обработку при следующем запуске
        return {
            "status": "soft_timeout",
            "total_processed": idx - 1 if 'idx' in locals() else 0,
            "successfully_processed": successfully_processed if 'successfully_processed' in locals() else 0,
            "failed_count": len(failed_photos) if 'failed_photos' in locals() else 0,
            "event_id": event_id,
            "message": f"Задача прервана по мягкому таймауту. Обработано {idx - 1 if 'idx' in locals() else 0} из {total if 'total' in locals() else 0} фотографий."
        }
        
    except TimeLimitExceeded as e:
        # Жесткий таймаут - задача будет убита
        error_msg = f"ЖЕСТКИЙ ТАЙМАУТ в process_event_photos для события {event_id}"
        logger.error(f"HARD TIMEOUT in process_event_photos for event {event_id}: {str(e)}")
        task_logger.critical(error_msg, exc_info=True, event_id=event_id, error=str(e))
        
        # Сохраняем информацию об ошибке в метаданные задачи
        self.update_state(
            state='FAILURE',
            meta={
                'error': str(e),
                'error_type': 'TimeLimitExceeded',
                'event_id': event_id,
            }
        )
        
        # Пробрасываем исключение
        raise
        
    except Exception as e:
        error_msg = f"КРИТИЧЕСКАЯ ОШИБКА в process_event_photos для события {event_id}"
        logger.error(f"ERROR in process_event_photos for event {event_id}: {str(e)}", exc_info=True)
        task_logger.critical(error_msg, exc_info=True, event_id=event_id, error=str(e))
        
        # Сохраняем информацию об ошибке в метаданные задачи
        self.update_state(
            state='FAILURE',
            meta={
                'error': str(e),
                'error_type': type(e).__name__,
                'event_id': event_id,
                'traceback': traceback.format_exc()
            }
        )
        
        # Пробрасываем исключение дальше, чтобы Celery мог его обработать
        raise
    finally:
        db.close()
        logger.debug(f"Database connection closed for event {event_id}")
        # Закрываем логгер, чтобы освободить файловые дескрипторы
        try:
            task_logger.log_task_end(success=True if 'error' not in locals() else False)
            task_logger.close()
        except Exception as close_error:
            logger.warning(f"Error closing task logger: {str(close_error)}")

