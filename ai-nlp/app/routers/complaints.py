"""
Complaints router: Handles complaint processing and result retrieval.
"""

import uuid
from fastapi import APIRouter, HTTPException, BackgroundTasks
from typing import Dict, Any

from app.schemas import ComplaintRequest, ComplaintResponse, AnalysisResult
from app.services.queue_service import add_job, get_result

router = APIRouter()


@router.post("/process-complaint", response_model=ComplaintResponse)
async def process_complaint(
    request: ComplaintRequest,
    background_tasks: BackgroundTasks
):
    """Queue complaint for async processing, return job_id."""
    if not request.complaint_text.strip():
        raise HTTPException(status_code=400, detail="complaint_text cannot be empty")

    job_id = str(uuid.uuid4())
    await add_job(job_id, request.complaint_text.strip(), request.days_pending)

    return ComplaintResponse(
        job_id=job_id,
        status="queued",
        message="Complaint queued for processing"
    )


@router.get("/result/{job_id}", response_model=AnalysisResult)
async def get_result_endpoint(job_id: str):
    """Get processing result for job_id."""
    result = await get_result(job_id)
    if result.get('status') == 'not_found':
        raise HTTPException(status_code=404, detail="Job not found or still processing")

    return AnalysisResult(**result)