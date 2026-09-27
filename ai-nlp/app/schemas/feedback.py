"""
Feedback-related Pydantic schemas.
"""

from pydantic import BaseModel
from typing import Optional


class FeedbackRequest(BaseModel):
    complaint_text:      str
    original_category:   str
    corrected_category:  str
    original_severity:   str
    corrected_severity:  str
    final_decision:      Optional[str] = ""
    engineer_id:         str   
    incident_id:         Optional[int] = None

class FeedbackResponse(BaseModel):
    status: str
    total_feedback: int
    timestamp: str

class FeedbackStats(BaseModel):
    total_feedback: int
    category_corrections: int
    severity_corrections: int
    category_patterns: list[dict]
    severity_patterns: list[dict]
    ready_for_retrain: bool
    retrain_recommendation: str