"""
Queue service: Manages background job processing.
"""

import asyncio
import logging
from typing import Dict, Any
from datetime import datetime

from app.config import settings
from app.services.nlp_service import process_complaint

logger = logging.getLogger(__name__)

# Global job queue and results store
job_queue = asyncio.Queue(maxsize=settings.max_queue_size)
results_store: Dict[str, Dict[str, Any]] = {}


async def add_job(job_id: str, text: str, days_pending: int):
    """Add a job to the processing queue."""
    job = {
        'job_id': job_id,
        'complaint_text': text,
        'days_pending': days_pending
    }
    await job_queue.put(job)
    logger.info(f"Job {job_id} added to queue")


async def get_result(job_id: str) -> Dict[str, Any]:
    """Get result for a job_id."""
    return results_store.get(job_id, {'status': 'not_found'})


async def process_jobs_worker():
    """Background worker to process queued jobs."""
    while True:
        try:
            job = await job_queue.get()
            job_id = job['job_id']
            text = job['complaint_text']
            days_pending = job['days_pending']

            logger.info(f"Processing job {job_id}")

            try:
                # Process the complaint
                result = process_complaint(text, days_pending)

                # Store the result
                results_store[job_id] = {
                    'job_id': job_id,
                    'status': 'completed',
                    **result,
                    'processed_at': datetime.now().isoformat()
                }

                logger.info(f"Job {job_id} completed successfully")

            except Exception as e:
                logger.error(f"Error processing job {job_id}: {str(e)}")
                results_store[job_id] = {
                    'job_id': job_id,
                    'status': 'failed',
                    'error': str(e),
                    'processed_at': datetime.now().isoformat()
                }

            job_queue.task_done()

        except Exception as e:
            logger.error(f"Worker error: {str(e)}")
            await asyncio.sleep(1)  # Prevent tight loop on error


def get_queue_status() -> Dict[str, Any]:
    """Get current queue status."""
    return {
        'queue_size': job_queue.qsize(),
        'results_count': len(results_store),
        'max_queue_size': settings.max_queue_size
    }