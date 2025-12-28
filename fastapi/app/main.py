from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.config import settings
from app.routers import health, events, photos, tasks
import logging

# Настройка логирования
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)

logger = logging.getLogger(__name__)

app = FastAPI(
    title="Hunter-Photo API",
    description="API для обработки фотографий и ML задач",
    version="1.0.0",
)

logger.info("FastAPI application initialized")

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.allowed_origins_list,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Routers
app.include_router(health.router, prefix=settings.API_PREFIX, tags=["health"])
app.include_router(events.router, prefix=settings.API_PREFIX, tags=["events"])
app.include_router(photos.router, prefix=settings.API_PREFIX, tags=["photos"])
app.include_router(tasks.router, prefix=settings.API_PREFIX, tags=["tasks"])


@app.get("/")
async def root():
    return {
        "message": "Hunter-Photo API",
        "version": "1.0.0",
        "docs": "/docs"
    }

