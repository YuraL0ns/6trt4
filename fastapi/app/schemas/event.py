from pydantic import BaseModel
from typing import Optional
from datetime import datetime


class EventCreate(BaseModel):
    title: str
    city: str
    date: datetime
    description: Optional[str] = None


class EventResponse(BaseModel):
    id: str
    title: str
    city: str
    date: datetime
    description: Optional[str] = None
    status: str
    price: Optional[float] = None
    
    class Config:
        from_attributes = True


