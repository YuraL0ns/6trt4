from sqlalchemy import Column, String, DateTime, Text, Float
from sqlalchemy.dialects.postgresql import UUID
import uuid
import enum
from app.database import Base


class EventStatus(str, enum.Enum):
    """Статусы события - значения должны совпадать с Laravel миграцией"""
    DRAFT = "draft"
    PROCESSING = "processing"
    PUBLISHED = "published"
    COMPLETED = "completed"
    ARCHIVED = "archived"


class Event(Base):
    __tablename__ = "events"
    
    id = Column(UUID(as_uuid=False), primary_key=True, default=lambda: str(uuid.uuid4()))
    title = Column(String(255), nullable=False)
    city = Column(String(255), nullable=False)
    date = Column(DateTime, nullable=False)
    description = Column(Text, nullable=True)
    # Используем String вместо Enum для совместимости с PostgreSQL enum из Laravel
    # Laravel создает enum как строковый тип в PostgreSQL
    status = Column(String(20), nullable=False, default=EventStatus.DRAFT.value)
    price = Column(Float, nullable=True)
    author_id = Column(UUID(as_uuid=False), nullable=False)
    cover_path = Column(String(500), nullable=True)
    created_at = Column(DateTime, nullable=False)
    updated_at = Column(DateTime, nullable=False)


