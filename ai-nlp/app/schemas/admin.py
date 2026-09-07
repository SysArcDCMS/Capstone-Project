"""
Admin-related Pydantic schemas.
"""

from pydantic import BaseModel
from typing import Optional


class SeverityConfigUpdate(BaseModel):
    high_negative_threshold: Optional[float] = None
    medium_negative_threshold: Optional[float] = None
    high_days_pending: Optional[int] = None
    medium_days_pending: Optional[int] = None
    urgency_keywords: Optional[list[str]] = None

class SeverityConfigResponse(BaseModel):
    high_negative_threshold: float
    medium_negative_threshold: float
    high_days_pending: int
    medium_days_pending: int
    urgency_keywords: list[str]
    keyword_count: int

class RetrainResponse(BaseModel):
    status: str
    accuracy: Optional[float] = None
    total_samples: Optional[int] = None
    original_samples: Optional[int] = None
    feedback_samples: Optional[int] = None
    message: str

class FeedbackResponse(BaseModel):
    status: str
    total_feedback: int
    timestamp: str
    auto_retrain_triggered: bool = False    