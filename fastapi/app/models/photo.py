from sqlalchemy import Column, String, Float, Boolean, JSON, ForeignKey
from sqlalchemy.dialects.postgresql import UUID
import uuid
from app.database import Base


class Photo(Base):
    __tablename__ = "photos"
    
    id = Column(UUID(as_uuid=False), primary_key=True, default=lambda: str(uuid.uuid4()))
    event_id = Column(UUID(as_uuid=False), ForeignKey("events.id"), nullable=False)
    original_path = Column(String(500), nullable=False)
    custom_path = Column(String(500), nullable=True)
    s3_custom_url = Column(String(1000), nullable=True)
    price = Column(Float, nullable=False)
    has_faces = Column(Boolean, default=False)
    face_vec = Column(JSON, nullable=True)  # Вектор первого лица (для совместимости)
    face_encodings = Column(JSON, nullable=True)  # Список всех найденных лиц (embeddings)
    face_bboxes = Column(JSON, nullable=True)  # Список координат bounding boxes [[x1, y1, x2, y2], ...]
    numbers = Column(JSON, nullable=True)  # Список найденных номеров
    exif_data = Column(JSON, nullable=True)
    created_at_exif = Column(String(50), nullable=True)  # Дата из EXIF в строковом формате
    # Примечание: created_at - это стандартное поле timestamps Laravel (timestamp в БД)
    # Не используем его для хранения даты из EXIF, используем created_at_exif


