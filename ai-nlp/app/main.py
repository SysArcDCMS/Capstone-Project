"""
FastAPI NLP Microservice Main Application
=========================================

Entry point for the FastAPI application.
Sets up app, middleware, routes, and startup events.
"""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
import logging
import asyncio

from app.config import settings
from app.core.models import load_classifier, load_sentiment_model
from app.services.queue_service import process_jobs_worker

# Set up logging
logging.basicConfig(
    level=getattr(logging, settings.log_level.upper()),
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
    handlers=[
        logging.FileHandler(f"{settings.log_dir}/fastapi_app.log"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Create FastAPI app
app = FastAPI(
    title=settings.app_name,
    version=settings.version,
    debug=settings.debug
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Configure for production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include routers
from app.routers.complaints import router as complaints_router
from app.routers.feedback import router as feedback_router
from app.routers.admin import router as admin_router
from app.routers.health import router as health_router

app.include_router(complaints_router, prefix="/api/v1", tags=["complaints"])
app.include_router(feedback_router, prefix="/api/v1", tags=["feedback"])
app.include_router(admin_router, prefix="/api/v1", tags=["admin"])
app.include_router(health_router, prefix="/api/v1", tags=["health"])


@app.on_event("startup")
async def startup_event():
    """Load models and start background worker on startup."""
    logger.info("Starting FastAPI NLP Microservice...")

    # Load NLP models
    logger.info("Loading NLP models...")
    load_classifier()
    load_sentiment_model()
    logger.info("Models loaded successfully.")

    # Start background worker
    asyncio.create_task(process_jobs_worker())
    logger.info("Background worker started.")


@app.on_event("shutdown")
async def shutdown_event():
    """Cleanup on shutdown."""
    logger.info("Shutting down FastAPI NLP Microservice...")


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app.main:app",
        host=settings.host,
        port=settings.port,
        reload=settings.debug,
        log_level=settings.log_level.lower()
    )