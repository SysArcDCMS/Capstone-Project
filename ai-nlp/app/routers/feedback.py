"""
Feedback router: Handles feedback submission and statistics.
"""

from fastapi import APIRouter, HTTPException, BackgroundTasks

from app.schemas import FeedbackRequest, FeedbackResponse, FeedbackStats
from app.services.feedback_service import log_feedback, get_feedback_stats

router = APIRouter()


@router.post("/submit-feedback", response_model=FeedbackResponse)
async def submit_feedback(request: FeedbackRequest, background_tasks: BackgroundTasks):
    """Log HITL feedback correction."""
    try:
        result = log_feedback(request.dict(), background_tasks)
        return FeedbackResponse(**result)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Feedback logging failed: {str(e)}")


@router.get("/feedback-stats", response_model=FeedbackStats)
async def feedback_stats():
    """Get feedback statistics for admin dashboard."""
    try:
        stats = get_feedback_stats()
        return FeedbackStats(**stats)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Failed to get stats: {str(e)}")