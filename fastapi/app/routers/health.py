from fastapi import APIRouter, HTTPException
from datetime import datetime
from io import BytesIO
import uuid
import os
from app.database import get_db
from app.config import settings
from utils.s3_uploader import S3Uploader

router = APIRouter()


@router.get("/health")
async def health_check():
    """Проверка здоровья API"""
    return {
        "status": "healthy",
        "timestamp": datetime.utcnow().isoformat(),
        "service": "hunter-photo-api"
    }


@router.get("/health/database")
async def health_check_database():
    """Проверка подключения к базе данных"""
    try:
        from sqlalchemy import text
        db = next(get_db())
        # Простой запрос для проверки подключения (SQLAlchemy требует text())
        db.execute(text("SELECT 1"))
        db.close()
        return {
            "status": "ok",
            "message": "Database is reachable",
            "timestamp": datetime.utcnow().isoformat()
        }
    except Exception as e:
        raise HTTPException(
            status_code=503,
            detail={
                "status": "error",
                "message": f"Database connection failed: {str(e)}",
                "timestamp": datetime.utcnow().isoformat()
            }
        )


@router.get("/health/s3")
async def health_check_s3():
    """Проверка подключения к S3"""
    try:
        s3_uploader = S3Uploader()
        
        if not s3_uploader.is_available():
            return {
                "status": "error",
                "message": "S3 uploader is not available or not configured",
                "timestamp": datetime.utcnow().isoformat()
            }
        
        # Пробуем загрузить тестовый файл
        test_key = f"health-check/{uuid.uuid4()}.txt"
        test_content = b"ping"
        
        s3_uploader.s3_client.upload_fileobj(
            BytesIO(test_content),
            s3_uploader.bucket_name,
            test_key,
            ExtraArgs={"ContentType": "text/plain"}
        )
        
        # Удаляем тестовый файл
        try:
            s3_uploader.s3_client.delete_object(
                Bucket=s3_uploader.bucket_name,
                Key=test_key
            )
        except Exception:
            pass  # Игнорируем ошибки удаления
        
        return {
            "status": "ok",
            "message": "S3 is reachable",
            "timestamp": datetime.utcnow().isoformat()
        }
    except Exception as e:
        return {
            "status": "error",
            "message": str(e),
            "timestamp": datetime.utcnow().isoformat()
        }


@router.get("/health/storage")
async def health_check_storage():
    """Проверка доступности папки хранения файлов"""
    try:
        storage_path = "/var/www/html/storage/app/public"
        
        checks = {
            "storage_path_exists": os.path.exists(storage_path),
            "storage_path_readable": os.access(storage_path, os.R_OK),
            "storage_path_writable": os.access(storage_path, os.W_OK),
            "storage_path": storage_path
        }
        
        # Проверяем папку events
        events_path = os.path.join(storage_path, "events")
        checks["events_path_exists"] = os.path.exists(events_path)
        checks["events_path_readable"] = os.access(events_path, os.R_OK) if checks["events_path_exists"] else False
        checks["events_path_writable"] = os.access(events_path, os.W_OK) if checks["events_path_exists"] else False
        
        if all([
            checks["storage_path_exists"],
            checks["storage_path_readable"],
            checks["storage_path_writable"]
        ]):
            return {
                "status": "ok",
                "message": "Storage is accessible",
                "checks": checks,
                "timestamp": datetime.utcnow().isoformat()
            }
        else:
            return {
                "status": "warning",
                "message": "Storage has some access issues",
                "checks": checks,
                "timestamp": datetime.utcnow().isoformat()
            }
    except Exception as e:
        return {
            "status": "error",
            "message": str(e),
            "timestamp": datetime.utcnow().isoformat()
        }


@router.get("/health/all")
async def health_check_all():
    """Проверка всех компонентов системы"""
    results = {
        "api": {"status": "ok"},
        "database": None,
        "s3": None,
        "storage": None,
        "timestamp": datetime.utcnow().isoformat()
    }
    
    # Проверка базы данных
    try:
        from sqlalchemy import text
        db = next(get_db())
        db.execute(text("SELECT 1"))
        db.close()
        results["database"] = {"status": "ok", "message": "Database is reachable"}
    except Exception as e:
        results["database"] = {"status": "error", "message": str(e)}
    
    # Проверка S3
    try:
        s3_uploader = S3Uploader()
        if s3_uploader.is_available():
            test_key = f"health-check/{uuid.uuid4()}.txt"
            s3_uploader.s3_client.upload_fileobj(
                BytesIO(b"ping"),
                s3_uploader.bucket_name,
                test_key,
                ExtraArgs={"ContentType": "text/plain"}
            )
            try:
                s3_uploader.s3_client.delete_object(
                    Bucket=s3_uploader.bucket_name,
                    Key=test_key
                )
            except Exception:
                pass
            results["s3"] = {"status": "ok", "message": "S3 is reachable"}
        else:
            results["s3"] = {"status": "warning", "message": "S3 not configured"}
    except Exception as e:
        results["s3"] = {"status": "error", "message": str(e)}
    
    # Проверка хранилища
    try:
        storage_path = "/var/www/html/storage/app/public"
        if os.path.exists(storage_path) and os.access(storage_path, os.R_OK) and os.access(storage_path, os.W_OK):
            results["storage"] = {"status": "ok", "message": "Storage is accessible"}
        else:
            results["storage"] = {"status": "warning", "message": "Storage has access issues"}
    except Exception as e:
        results["storage"] = {"status": "error", "message": str(e)}
    
    # Определяем общий статус
    all_ok = all([
        results["database"]["status"] == "ok" if results["database"] else False,
        results["s3"]["status"] in ["ok", "warning"] if results["s3"] else False,
        results["storage"]["status"] in ["ok", "warning"] if results["storage"] else False
    ])
    
    return {
        "status": "ok" if all_ok else "degraded",
        "results": results
    }


