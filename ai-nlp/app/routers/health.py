"""
Health router: Provides service health check.
"""

from fastapi import APIRouter
from datetime import datetime

from app.schemas import HealthResponse
from app.services.queue_service import get_queue_status

router = APIRouter()


@router.get("/health", response_model=HealthResponse)
async def health():
    """Service health check."""
    queue_status = get_queue_status()

    return HealthResponse(
        status="online",
        service="FastAPI NLP Microservice",
        timestamp=datetime.now().isoformat(),
        queue_size=queue_status['queue_size'],
        results_count=queue_status['results_count']
    )