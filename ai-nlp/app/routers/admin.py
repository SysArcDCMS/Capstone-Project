"""
Admin router: Handles retraining, config updates, and config retrieval.
"""

from fastapi import APIRouter, HTTPException, BackgroundTasks

from app.schemas import (
    SeverityConfigUpdate,
    SeverityConfigResponse,
    RetrainResponse
)
from app.core.models import reload_config, get_current_config
from app.services.feedback_service import load_feedback_for_training
from app.services.retrain_service import retrain_classifier

router = APIRouter()


@router.post("/retrain", response_model=RetrainResponse)
async def retrain(background_tasks: BackgroundTasks):
    """Trigger SVM retraining with accumulated feedback."""
    try:
        # Run in background so endpoint returns immediately
        background_tasks.add_task(retrain_classifier)
        return RetrainResponse(
            status='retrain_queued',
            accuracy=None,
            total_samples=None,
            message='Retraining started in background'
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Retraining failed: {str(e)}")


@router.post("/update-severity-config", response_model=SeverityConfigResponse)
async def update_severity_config(request: SeverityConfigUpdate):
    """Update severity configuration in-memory."""
    try:
        updated = reload_config(request.dict(exclude_unset=True))
        return SeverityConfigResponse(**updated)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Config update failed: {str(e)}")


@router.get("/severity-config", response_model=SeverityConfigResponse)
async def severity_config():
    """Get current severity configuration."""
    try:
        config = get_current_config()
        return SeverityConfigResponse(**config)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Failed to get config: {str(e)}")