"""
Complaint-related Pydantic schemas.
"""

from pydantic import BaseModel
from typing import Optional


class ComplaintRequest(BaseModel):
    complaint_text: str
    days_pending: int = 0

class ComplaintResponse(BaseModel):
    job_id: str
    status: str
    message: str

class AnalysisResult(BaseModel):
    job_id: str
    status: str
    category: Optional[str] = None
    category_confidence: Optional[float] = None
    sentiment: Optional[str] = None
    sentiment_score: Optional[float] = None
    composite_score: Optional[float] = None
    severity: Optional[str] = None
    processed_at: Optional[str] = None
    error: Optional[str] = None