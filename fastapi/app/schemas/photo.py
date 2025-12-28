from pydantic import BaseModel
from typing import Optional, List


class PhotoResponse(BaseModel):
    id: str
    event_id: str
    original_path: str
    custom_path: Optional[str] = None
    s3_custom_url: Optional[str] = None
    price: float
    has_faces: bool = False
    numbers: Optional[List[str]] = None
    
    class Config:
        from_attributes = True


