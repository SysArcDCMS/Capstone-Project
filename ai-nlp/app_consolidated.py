# FastAPI NLP Microservice - Consolidated App Code
# ================================================
# All Python files from the app/ directory combined into one file for review
# Generated on: Tue Apr 28 05:59:15 PM PST 2026

# ==============================================================================
# app/config.py
# ==============================================================================

from pydantic_settings import BaseSettings
from pydantic import Field
from typing import Optional


class Settings(BaseSettings):
    # Remove model_config line I told you to add
    # Keep ONLY this for the namespace warning:
    model_config = {
        'env_file': '.env',
        'case_sensitive': False,
        'protected_namespaces': ('settings_',)
    }

    # App settings
    app_name: str = "FastAPI NLP Microservice"
    version: str = "1.0.0"
    debug: bool = Field(default=False)

    # Server settings
    host: str = Field(default="0.0.0.0")
    port: int = Field(default=8000)

    # Model paths
    model_dir: str = Field(default="models")
    data_dir: str = Field(default="data")
    log_dir: str = Field(default="logs")

    # External API URLs
    laravel_config_url: Optional[str] = Field(default=None)

    # Queue settings
    max_queue_size: int = Field(default=1000)
    worker_timeout: int = Field(default=30)

    # Logging
    log_level: str = Field(default="INFO")

    # NO class Config here — model_config replaces it


settings = Settings()

# ==============================================================================
# app/core/config/__init__.py
# ==============================================================================

"""
Core config package.
"""

# ==============================================================================
# app/core/config/settings.py
# ==============================================================================

"""
Core settings and configuration management.
"""

# This can be expanded with additional settings if needed
# For now, it's a placeholder for future core configurations

# ==============================================================================
# app/core/config/severity_config.py
# ==============================================================================

"""
Severity configuration management.
This module handles loading and updating severity settings.
"""

# Placeholder for additional severity config logic if needed
# Currently handled in severity.py

# ==============================================================================
# app/core/models/classifier.py
# ==============================================================================

"""
TF-IDF + SVM complaint classifier.
Adapted from Flask version for FastAPI.
"""

import pickle
import re
import os
import logging

logger = logging.getLogger(__name__)

# Module-level model references
_vectorizer = None
_classifier = None

# Use settings for paths
from app.config import settings

MODEL_DIR = settings.model_dir
VECTORIZER_PATH = os.path.join(MODEL_DIR, 'tfidf_vectorizer.pkl')
CLASSIFIER_PATH = os.path.join(MODEL_DIR, 'svm_classifier.pkl')


def preprocess_for_svm(text: str) -> str:
    """
    Minimal cleaning for TF-IDF vectorizer.
    Keeps Taglish words intact — just normalizes formatting.
    """
    text = text.lower()
    # Remove URLs
    text = re.sub(r'http\S+', '', text)
    # Remove special characters except spaces
    text = re.sub(r'[^a-zA-Z\s]', ' ', text)
    # Collapse multiple spaces
    text = re.sub(r'\s+', ' ', text).strip()
    return text


def load_classifier():
    """
    Load saved TF-IDF vectorizer and SVM model from disk.
    Called once at FastAPI startup.
    If no model exists yet, logs a warning —
    run train_classifier.py first.
    """
    global _vectorizer, _classifier

    if not os.path.exists(VECTORIZER_PATH):
        logger.warning(
            f"No trained vectorizer found at {VECTORIZER_PATH}. "
            "Run train_classifier.py to generate models."
        )
        return

    if not os.path.exists(CLASSIFIER_PATH):
        logger.warning(
            f"No trained classifier found at {CLASSIFIER_PATH}. "
            "Run train_classifier.py to generate models."
        )
        return

    try:
        with open(VECTORIZER_PATH, 'rb') as f:
            _vectorizer = pickle.load(f)

        with open(CLASSIFIER_PATH, 'rb') as f:
            _classifier = pickle.load(f)

        logger.info("SVM classifier loaded successfully.")
    except Exception as e:
        logger.error(f"Error loading classifier: {e}")


def classify_complaint(text: str):
    """
    Classify complaint text into one of 4 categories.

    Returns:
        category   (str)   - Billing / Water Quality / Metering / Operations
        confidence (float) - highest class probability
    """
    if _vectorizer is None or _classifier is None:
        # Fallback if model not trained yet
        logger.warning("Classifier not loaded. Returning default category.")
        return "Operations", 0.0

    try:
        cleaned = preprocess_for_svm(text)
        vector = _vectorizer.transform([cleaned])

        category = _classifier.predict(vector)[0]
        probabilities = _classifier.predict_proba(vector)[0]
        confidence = probabilities.max()

        return category, confidence
    except Exception as e:
        logger.error(f"Error in classification: {e}")
        return "Operations", 0.0


def reload_models():
    """
    Reload models from disk after retraining.
    Called by retrain.py after saving new models.
    """
    global _vectorizer, _classifier

    try:
        with open(VECTORIZER_PATH, 'rb') as f:
            _vectorizer = pickle.load(f)

        with open(CLASSIFIER_PATH, 'rb') as f:
            _classifier = pickle.load(f)

        logger.info("Classifier reloaded after retraining.")
    except Exception as e:
        logger.error(f"Error reloading models: {e}")

# ==============================================================================
# app/core/models/__init__.py
# ==============================================================================

"""
Core models package.
"""

from .classifier import load_classifier, classify_complaint, reload_models
from .sentiment import load_sentiment_model, analyze_sentiment
from .severity import get_severity, reload_config, get_current_config

__all__ = [
    "load_classifier", "classify_complaint", "reload_models",
    "load_sentiment_model", "analyze_sentiment",
    "get_severity", "reload_config", "get_current_config"
]

# ==============================================================================
# app/core/models/sentiment.py
# ==============================================================================

"""
Sentiment analysis using dost-asti/RoBERTa-tl-sentiment-analysis.
Adapted from Flask version for FastAPI.
"""

import logging
import re

logger = logging.getLogger(__name__)

_sentiment_pipeline = None

MODEL_NAME = "dost-asti/RoBERTa-tl-sentiment-analysis"
MAX_TOKENS = 512  # RoBERTa hard limit


def preprocess_for_roberta(text: str) -> str:
    """
    Minimal cleaning for RoBERTa.
    DO NOT remove stopwords — transformer needs full context.
    Just normalize whitespace and truncate.
    """
    # Normalize whitespace
    text = re.sub(r'\s+', ' ', text).strip()
    # Truncate to safe character length
    # (512 tokens ≈ ~1800 characters for Taglish)
    return text[:1800]


def load_sentiment_model():
    """
    Load RoBERTa model from HuggingFace.
    Downloads on first run (~500MB), cached locally after.
    Called once at FastAPI startup.
    """
    global _sentiment_pipeline

    try:
        from transformers import pipeline
        logger.info(f"Loading sentiment model: {MODEL_NAME}")
        logger.info("This may take a moment on first run...")

        _sentiment_pipeline = pipeline(
            task="sentiment-analysis",
            model=MODEL_NAME,
            top_k=1  # Return only highest scoring label
        )
        logger.info("RoBERTa sentiment model loaded successfully.")

    except Exception as e:
        logger.error(f"Failed to load sentiment model: {e}")
        logger.warning(
            "Falling back to rule-based sentiment. "
            "Install transformers and torch to use RoBERTa."
        )
        _sentiment_pipeline = None


def analyze_sentiment(text: str):
    """
    Run sentiment analysis on complaint text.

    Returns:
        label (str)   - POSITIVE / NEGATIVE / NEUTRAL
        score (float) - confidence score 0.0 to 1.0

    Example:
        "Walang tubig sa amin since kahapon!"
        → ("NEGATIVE", 0.9412)
    """
    cleaned = preprocess_for_roberta(text)

    if _sentiment_pipeline is not None:
        try:
            result = _sentiment_pipeline(cleaned)[0]
            label = result['label'].upper()
            score = result['score']
            return label, score

        except Exception as e:
            logger.error(f"RoBERTa inference error: {e}")
            logger.warning("Falling back to rule-based sentiment.")

    # ── Fallback: Rule-based sentiment ───────────────────
    return _rule_based_sentiment(text)


def _rule_based_sentiment(text: str):
    """
    Simple fallback sentiment if RoBERTa unavailable.
    Not for production — only emergency fallback.
    """
    text_lower = text.lower()

    negative_words = [
        'walang', 'wala', 'hindi', 'ayaw', 'grabe', 'matagal',
        'broken', 'leak', 'contaminated', 'mabaho', 'amoy',
        'no water', 'problema', 'mali', 'sobrang', 'galit',
        'frustrated', 'unfair', 'bad', 'poor', 'terrible',
        'worst', 'never', 'always', 'still', 'already'
    ]

    positive_words = [
        'salamat', 'thank', 'okay', 'ok', 'good', 'great',
        'naresolba', 'fixed', 'ayos na', 'sige', 'fine'
    ]

    neg_count = sum(1 for w in negative_words if w in text_lower)
    pos_count = sum(1 for w in positive_words if w in text_lower)

    if neg_count > pos_count:
        score = min(0.5 + (neg_count * 0.1), 0.95)
        return "NEGATIVE", score
    elif pos_count > neg_count:
        score = min(0.5 + (pos_count * 0.1), 0.95)
        return "POSITIVE", score
    else:
        return "NEUTRAL", 0.60

# ==============================================================================
# app/core/models/severity.py
# ==============================================================================

"""
Severity scoring engine.
Adapted from Flask version for FastAPI.
Combines RoBERTa sentiment + days_pending + urgency keywords.
Config loaded from JSON file, with fallback defaults.
"""

import logging
import requests
import os
import json

logger = logging.getLogger(__name__)

from app.config import settings

# Config file path
CONFIG_FILE = os.path.join(settings.data_dir, 'severity_config.json')

# ── Default Config ────────────────────────────────────────
# Overwritten at startup by load from file or Laravel/DB
_CONFIG = {
    'high_negative_threshold':   0.80,
    'medium_negative_threshold': 0.50,
    'high_days_pending':         3,
    'medium_days_pending':       1,
    'urgency_keywords': [
        # Tagalog
        'walang tubig', 'tumagas', 'amoy', 'mabaho',
        'hindi malinis', 'may kulay', 'may worm', 'may uod',
        'apurahan', 'delikado', 'agad', 'tulungan',
        'ilang araw na', 'matagal na', 'hindi pa rin',
        'hindi pa naaayos', 'wala pa ring tubig',
        'bumagsak na', 'baha',
        # Taglish
        'grabe na', 'sobrang tagal', 'days na',
        'hindi pa rin naaayos', 'wala na talaga',
        'emergency na ito', 'kailangan na agad',
        # English
        'no water', 'leak', 'burst pipe', 'busted pipe',
        'contaminated', 'emergency', 'urgent', 'flooding',
        'no supply', 'broken meter', 'hazardous',
        'health risk', 'days without water',
    ]
}


def _load_from_file():
    """
    Load severity config from JSON file on startup.
    Falls back to defaults if file doesn't exist.
    """
    global _CONFIG
    if os.path.exists(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, 'r') as f:
                data = json.load(f)
            _CONFIG.update(data)
            logger.info("Severity config loaded from file.")
        except Exception as e:
            logger.warning(f"Could not load config from file: {e}. Using defaults.")
    else:
        logger.info("No config file found. Using defaults.")


def _load_from_laravel():
    """
    Fetch latest severity config from Laravel API on startup.
    Laravel reads from tbl_severity_config (latest row).
    """
    global _CONFIG
    if settings.laravel_config_url:
        try:
            response = requests.get(settings.laravel_config_url, timeout=5)
            if response.status_code == 200:
                data = response.json()
                _CONFIG.update({
                    'high_negative_threshold':
                        float(data.get('high_negative_threshold', _CONFIG['high_negative_threshold'])),
                    'medium_negative_threshold':
                        float(data.get('medium_negative_threshold', _CONFIG['medium_negative_threshold'])),
                    'high_days_pending':
                        int(data.get('high_days_pending', _CONFIG['high_days_pending'])),
                    'medium_days_pending':
                        int(data.get('medium_days_pending', _CONFIG['medium_days_pending'])),
                    'urgency_keywords':
                        data.get('urgency_keywords', _CONFIG['urgency_keywords']),
                })
                logger.info("Severity config loaded from Laravel DB.")
        except Exception as e:
            logger.warning(f"Could not reach Laravel config endpoint: {e}. Using file or defaults.")


# Load from file first, then try Laravel
_load_from_file()
_load_from_laravel()


def get_severity(
    sentiment_label: str,
    sentiment_score: float,
    days_pending: int,
    text: str
) -> str:
    """
    Determine severity level: High / Medium / Low

    Logic:
    HIGH   → strongly NEGATIVE sentiment
              OR urgency keyword detected
              OR complaint pending too long
    MEDIUM → moderately NEGATIVE
              OR NEUTRAL sentiment
              OR pending 1-2 days
    LOW    → POSITIVE or mildly negative, fresh complaint
    """
    text_lower = text.lower()

    # Check urgency keywords
    urgency_hit = any(
        kw in text_lower
        for kw in _CONFIG['urgency_keywords']
    )

    # ── HIGH ─────────────────────────────────────────────
    if (
        (
            sentiment_label == 'NEGATIVE'
            and sentiment_score >= _CONFIG['high_negative_threshold']
        )
        or urgency_hit
        or days_pending >= _CONFIG['high_days_pending']
    ):
        return "High"

    # ── MEDIUM ───────────────────────────────────────────
    elif (
        (
            sentiment_label == 'NEGATIVE'
            and sentiment_score >= _CONFIG['medium_negative_threshold']
        )
        or sentiment_label == 'NEUTRAL'
        or days_pending >= _CONFIG['medium_days_pending']
    ):
        return "Medium"

    # ── LOW ──────────────────────────────────────────────
    else:
        return "Low"


def reload_config(new_config: dict) -> dict:
    """
    Update config in-memory and save to file.
    Called by /update-severity-config endpoint.
    """
    global _CONFIG

    if 'high_negative_threshold' in new_config:
        _CONFIG['high_negative_threshold'] = float(new_config['high_negative_threshold'])

    if 'medium_negative_threshold' in new_config:
        _CONFIG['medium_negative_threshold'] = float(new_config['medium_negative_threshold'])

    if 'high_days_pending' in new_config:
        _CONFIG['high_days_pending'] = int(new_config['high_days_pending'])

    if 'medium_days_pending' in new_config:
        _CONFIG['medium_days_pending'] = int(new_config['medium_days_pending'])

    if 'urgency_keywords' in new_config:
        _CONFIG['urgency_keywords'] = list(new_config['urgency_keywords'])

    # Save to file
    try:
        os.makedirs(settings.data_dir, exist_ok=True)
        with open(CONFIG_FILE, 'w') as f:
            json.dump(_CONFIG, f, indent=2)
        logger.info("Config saved to file.")
    except Exception as e:
        logger.error(f"Could not save config to file: {e}")

    logger.info(
        f"Severity config updated. "
        f"HIGH threshold: {_CONFIG['high_negative_threshold']}, "
        f"Keywords count: {len(_CONFIG['urgency_keywords'])}"
    )

    return get_current_config()


def get_current_config() -> dict:
    """Return current config."""
    return {
        'high_negative_threshold':   _CONFIG['high_negative_threshold'],
        'medium_negative_threshold': _CONFIG['medium_negative_threshold'],
        'high_days_pending':         _CONFIG['high_days_pending'],
        'medium_days_pending':       _CONFIG['medium_days_pending'],
        'urgency_keywords':          _CONFIG['urgency_keywords'],
        'keyword_count':             len(_CONFIG['urgency_keywords']),
    }

# ==============================================================================
# app/dependencies.py
# ==============================================================================

"""
Dependency injection functions.
Provides injectable dependencies for routes.
"""

from app.services.queue_service import get_queue_status


def get_nlp_models():
    """Dependency to ensure models are loaded."""
    # Models are loaded at startup, this is just a placeholder
    # In a more complex setup, this could return model instances
    return {"status": "models_loaded"}


def get_queue_info():
    """Dependency to get queue status."""
    return get_queue_status()

# ==============================================================================
# app/__init__.py
# ==============================================================================



# ==============================================================================
# app/main.py
# ==============================================================================

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

# ==============================================================================
# app/routers/admin.py
# ==============================================================================

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

# ==============================================================================
# app/routers/complaints.py
# ==============================================================================

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

# ==============================================================================
# app/routers/feedback.py
# ==============================================================================

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

# ==============================================================================
# app/routers/health.py
# ==============================================================================

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

# ==============================================================================
# app/routers/__init__.py
# ==============================================================================



# ==============================================================================
# app/schemas/admin.py
# ==============================================================================

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

# ==============================================================================
# app/schemas/complaints.py
# ==============================================================================

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
    severity: Optional[str] = None
    processed_at: Optional[str] = None
    error: Optional[str] = None

# ==============================================================================
# app/schemas/feedback.py
# ==============================================================================

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

# ==============================================================================
# app/schemas/health.py
# ==============================================================================

from pydantic import BaseModel

class HealthResponse(BaseModel):
    status: str
    service: str
    message: str = "OK"
    timestamp: str
    queue_size: int
    results_count: int    

# ==============================================================================
# app/schemas/__init__.py
# ==============================================================================

"""
Schemas package.
"""

from .complaints import ComplaintRequest, ComplaintResponse, AnalysisResult
from .feedback import FeedbackRequest, FeedbackResponse, FeedbackStats
from .admin import SeverityConfigUpdate, SeverityConfigResponse, RetrainResponse
from .health import HealthResponse

__all__ = [
    "ComplaintRequest", "ComplaintResponse", "AnalysisResult",
    "FeedbackRequest", "FeedbackResponse", "FeedbackStats",
    "SeverityConfigUpdate", "SeverityConfigResponse", "RetrainResponse",
    "HealthResponse"
]

# ==============================================================================
# app/services/feedback_service.py
# ==============================================================================

"""
Feedback service: Handles logging and statistics for HITL corrections.
Adapted from Flask version for FastAPI.
"""

import os
import pandas as pd
import logging
from datetime import datetime
from typing import Dict, Any, Optional

from app.config import settings

logger = logging.getLogger(__name__)

FEEDBACK_PATH = os.path.join(settings.data_dir, 'feedback_data.csv')

FEEDBACK_COLUMNS = [
    'timestamp',
    'complaint_text',
    'original_category',
    'corrected_category',
    'original_severity',
    'corrected_severity',
    'final_decision',
    'engineer_id',
    'source',
    'used_for_training' 
]


def log_feedback(data: dict, background_tasks=None) -> dict:

    os.makedirs(settings.data_dir, exist_ok=True)

    if os.path.exists(FEEDBACK_PATH):
        df = pd.read_csv(FEEDBACK_PATH)
        if 'used_for_training' not in df.columns:
            df['used_for_training'] = False
    else:
        df = pd.DataFrame(columns=FEEDBACK_COLUMNS)

    complaint_text = data.get('complaint_text', '').strip()

    # ── Duplicate guard ──────────────────────────────────────
    # Only Engineer decisions reach here.
    # If same complaint_text is pending (not yet trained),
    # UPDATE it — this means Engineer corrected their own submission
    pending_mask = (
        (df['complaint_text'].str.strip() == complaint_text) &
        (df['used_for_training'] == False)
    )

    if pending_mask.any():
        idx = df[pending_mask].index[-1]
        df.loc[idx, 'corrected_category'] = data.get('corrected_category', '')
        df.loc[idx, 'corrected_severity']  = data.get('corrected_severity', '')
        df.loc[idx, 'original_severity']   = data.get('original_severity', '')
        df.loc[idx, 'final_decision']      = data.get('final_decision', '')
        df.loc[idx, 'engineer_id']         = data.get('engineer_id', '')
        df.loc[idx, 'timestamp']           = datetime.now().isoformat()
        df.to_csv(FEEDBACK_PATH, index=False)

        logger.info(
            f"Engineer resubmission detected — "
            f"updated pending correction for: '{complaint_text[:50]}'"
        )

        return {
            'status':                 'feedback_updated',
            'total_feedback':         len(df),
            'timestamp':              df.loc[idx, 'timestamp'],
            'auto_retrain_triggered': False,
            'note':                   'Engineer correction updated'
        }
    # ── End duplicate guard ───────────────────────────────────

    # Normal append — fresh complaint correction
    new_row = {
        'timestamp':          datetime.now().isoformat(),
        'complaint_text':     complaint_text,
        'original_category':  data.get('original_category', ''),
        'corrected_category': data.get('corrected_category', ''),
        'original_severity':  data.get('original_severity', ''),
        'corrected_severity':  data.get('corrected_severity', ''),
        'final_decision':     data.get('final_decision', ''),
        'engineer_id':        data.get('engineer_id', ''),
        'source':             'hitl',
        'used_for_training':  False
    }

    df = pd.concat([df, pd.DataFrame([new_row])], ignore_index=True)
    df.to_csv(FEEDBACK_PATH, index=False)

    total = len(df)

    if new_row['original_category'] != new_row['corrected_category']:
        logger.info(
            f"CATEGORY CORRECTION by Engineer {new_row['engineer_id']}: "
            f"{new_row['original_category']} → {new_row['corrected_category']}"
        )

    if new_row['original_severity'] != new_row['corrected_severity']:
        logger.info(
            f"SEVERITY CORRECTION by Engineer {new_row['engineer_id']}: "
            f"{new_row['original_severity']} → {new_row['corrected_severity']}"
        )

    # Auto-retrain check
    from app.services.retrain_service import RETRAIN_THRESHOLD

    category_corrections = len(
        df[df['original_category'] != df['corrected_category']]
    )

    auto_retrain_triggered = False
    if category_corrections > 0 and category_corrections % RETRAIN_THRESHOLD == 0:
        logger.info(
            f"Auto-retrain triggered at {category_corrections} corrections"
        )
        if background_tasks is not None:
            from app.services.retrain_service import retrain_classifier
            background_tasks.add_task(retrain_classifier)
            auto_retrain_triggered = True

    return {
        'status':                 'feedback_logged',
        'total_feedback':         total,
        'timestamp':              new_row['timestamp'],
        'auto_retrain_triggered': auto_retrain_triggered
    }


def get_feedback_stats() -> Dict[str, Any]:
    """
    Return summary statistics about logged feedback.
    Used by Admin dashboard.
    """
    if not os.path.exists(FEEDBACK_PATH):
        return {
            'total_feedback': 0,
            'message': 'No feedback logged yet.'
        }

    df = pd.read_csv(FEEDBACK_PATH)
    total = len(df)

    # Category corrections
    cat_corrections = df[df['original_category'] != df['corrected_category']]

    # Severity corrections
    sev_corrections = df[df['original_severity'] != df['corrected_severity']]

    # Most common category corrections
    cat_pattern = (
        cat_corrections
        .groupby(['original_category', 'corrected_category'])
        .size()
        .reset_index(name='count')
        .sort_values('count', ascending=False)
        .head(5)
        .to_dict('records')
    ) if len(cat_corrections) > 0 else []

    # Most common severity corrections
    sev_pattern = (
        sev_corrections
        .groupby(['original_severity', 'corrected_severity'])
        .size()
        .reset_index(name='count')
        .sort_values('count', ascending=False)
        .head(5)
        .to_dict('records')
    ) if len(sev_corrections) > 0 else []

    return {
        'total_feedback':          total,
        'category_corrections':    len(cat_corrections),
        'severity_corrections':    len(sev_corrections),
        'category_patterns':       cat_pattern,
        'severity_patterns':       sev_pattern,
        'ready_for_retrain':       total >= 20,
        'retrain_recommendation':  (
            'Sufficient feedback for retraining.'
            if total >= 20 else
            f'Need {20 - total} more corrections before retraining.'
        )
    }


def load_feedback_for_training() -> pd.DataFrame:
    """
    Load feedback data formatted for SVM retraining.
    Only returns rows where category was corrected.
    """
    if not os.path.exists(FEEDBACK_PATH):
        return pd.DataFrame(columns=['complaint_text', 'category'])

    df = pd.read_csv(FEEDBACK_PATH)

    # Use corrected_category as the ground truth label
    training_rows = df[['complaint_text', 'corrected_category']].copy()
    training_rows.rename(
        columns={'corrected_category': 'category'},
        inplace=True
    )

    return training_rows.dropna()

# ==============================================================================
# app/services/__init__.py
# ==============================================================================



# ==============================================================================
# app/services/nlp_service.py
# ==============================================================================

"""
NLP service: Orchestrates classification, sentiment, and severity analysis.
"""

import logging
from typing import Tuple

from app.core.models import (
    classify_complaint,
    analyze_sentiment,
    get_severity
)

logger = logging.getLogger(__name__)


def process_complaint(text: str, days_pending: int) -> dict:
    """
    Process a complaint through the full NLP pipeline.

    Args:
        text: Complaint text
        days_pending: Days the complaint has been pending

    Returns:
        dict: Analysis results
    """
    try:
        # Task 1: Classification
        category, confidence = classify_complaint(text)

        # Task 2: Sentiment
        sentiment_label, sentiment_score = analyze_sentiment(text)

        # Task 3: Severity
        severity = get_severity(sentiment_label, sentiment_score, days_pending, text)

        logger.info(
            f"Processed complaint: category={category}, "
            f"sentiment={sentiment_label}({sentiment_score:.2f}), "
            f"severity={severity}"
        )

        return {
            'category': category,
            'category_confidence': round(float(confidence), 4),
            'sentiment': sentiment_label,
            'sentiment_score': round(float(sentiment_score), 4),
            'severity': severity
        }

    except Exception as e:
        logger.error(f"Error processing complaint: {str(e)}")
        raise

# ==============================================================================
# app/services/queue_service.py
# ==============================================================================

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

# ==============================================================================
# app/services/retrain_service.py
# ==============================================================================

"""
Retrain service: Handles SVM retraining pipeline.
Combines training_data.csv + unused feedback corrections.
Deduplicates, retrains, saves models, hot-reloads.
"""

import os
import pandas as pd
import pickle
import logging
from datetime import datetime
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.svm import SVC
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, classification_report

from app.config import settings

logger = logging.getLogger(__name__)

MODEL_DIR = settings.model_dir
VECTORIZER_PATH = os.path.join(MODEL_DIR, 'tfidf_vectorizer.pkl')
CLASSIFIER_PATH = os.path.join(MODEL_DIR, 'svm_classifier.pkl')
TRAINING_DATA_PATH = os.path.join(settings.data_dir, 'training_data.csv')
FEEDBACK_DATA_PATH = os.path.join(settings.data_dir, 'feedback_data.csv')

VALID_CATEGORIES = ['Billing', 'Water Quality', 'Metering', 'Operations']
MIN_SAMPLES_REQUIRED = 10
RETRAIN_THRESHOLD = 20


def load_training_data() -> pd.DataFrame:
    """Load base training data."""
    if not os.path.exists(TRAINING_DATA_PATH):
        logger.error(f"training_data.csv not found at {TRAINING_DATA_PATH}")
        logger.error("Run: copy the seed CSV to data/training_data.csv first")
        return pd.DataFrame(columns=['complaint_text', 'category'])

    df = pd.read_csv(TRAINING_DATA_PATH)

    # Validate columns
    if 'complaint_text' not in df.columns or 'category' not in df.columns:
        logger.error("training_data.csv must have 'complaint_text' and 'category' columns")
        return pd.DataFrame(columns=['complaint_text', 'category'])

    # Drop invalid categories
    df = df[df['category'].isin(VALID_CATEGORIES)]
    df = df.dropna(subset=['complaint_text', 'category'])
    df['complaint_text'] = df['complaint_text'].str.strip()
    df = df[df['complaint_text'] != '']

    logger.info(f"Loaded {len(df)} training samples")
    return df


def load_new_feedback() -> pd.DataFrame:
    if not os.path.exists(FEEDBACK_DATA_PATH):
        return pd.DataFrame(columns=['complaint_text', 'category'])

    df = pd.read_csv(FEEDBACK_DATA_PATH)

    if 'used_for_training' not in df.columns:
        df['used_for_training'] = False

    unused = df[
        (df['used_for_training'] == False) &
        (df['original_category'] != df['corrected_category'])
    ].copy()

    if len(unused) == 0:
        logger.info("No new feedback corrections to add")
        return pd.DataFrame(columns=['complaint_text', 'category'])

    # Simple dedup — same complaint_text appearing twice means
    # Engineer resubmitted. The duplicate guard in log_feedback()
    # should have caught this already, but keep as safety net.
    # Latest timestamp wins since all rows are Engineer decisions.
    unused['timestamp'] = pd.to_datetime(unused['timestamp'])
    unused = unused.sort_values('timestamp', ascending=True)
    unused = unused.drop_duplicates(
        subset=['complaint_text'],
        keep='last'
    )

    # Mark as used
    df.loc[unused.index, 'used_for_training'] = True
    df.to_csv(FEEDBACK_DATA_PATH, index=False)

    result = unused[['complaint_text', 'corrected_category']].copy()
    result.rename(columns={'corrected_category': 'category'}, inplace=True)
    result = result[result['category'].isin(VALID_CATEGORIES)]

    logger.info(f"Loaded {len(result)} Engineer-adjudicated corrections")
    return result

def dedup_feedback_against_training(
    training_df: pd.DataFrame,
    feedback_df: pd.DataFrame
) -> pd.DataFrame:
    """
    Remove feedback rows where complaint_text already exists in training data.
    Prevents duplicate training samples.
    """
    if len(feedback_df) == 0:
        return feedback_df

    existing_texts = set(
        training_df['complaint_text'].str.strip().str.lower()
    )

    mask = ~feedback_df['complaint_text'].str.strip().str.lower().isin(existing_texts)
    deduped = feedback_df[mask]

    removed = len(feedback_df) - len(deduped)
    if removed > 0:
        logger.info(f"Dedup removed {removed} feedback rows already in training data")

    return deduped


def merge_into_training_data(new_feedback_df: pd.DataFrame):
    """
    Append new feedback rows into training_data.csv permanently.
    So future retrains include them as base data.
    """
    if len(new_feedback_df) == 0:
        return

    training_df = load_training_data()
    merged = pd.concat([training_df, new_feedback_df], ignore_index=True)
    merged.to_csv(TRAINING_DATA_PATH, index=False)
    logger.info(f"Merged {len(new_feedback_df)} feedback rows into training_data.csv")


def retrain_classifier() -> dict:
    """
    Full retraining pipeline:
    1. Load base training data
    2. Load unused feedback corrections
    3. Dedup feedback against training
    4. Merge feedback into training_data.csv
    5. Retrain TF-IDF + SVM
    6. Save models
    7. Hot-reload in-memory models
    """
    try:
        logger.info("=== Starting retraining pipeline ===")

        # Step 1: Load base data
        training_df = load_training_data()

        # Step 2: Load new feedback only
        feedback_df = load_new_feedback()

        # Step 3: Dedup
        feedback_df = dedup_feedback_against_training(training_df, feedback_df)

        # Step 4: Merge feedback into training_data.csv permanently
        merge_into_training_data(feedback_df)

        # Step 5: Combine for this training run
        combined_df = pd.concat([training_df, feedback_df], ignore_index=True)
        combined_df = combined_df.dropna(subset=['complaint_text', 'category'])
        combined_df = combined_df[combined_df['complaint_text'].str.strip() != '']

        total_samples = len(combined_df)

        if total_samples < MIN_SAMPLES_REQUIRED:
            msg = f"Need at least {MIN_SAMPLES_REQUIRED} samples, got {total_samples}"
            logger.warning(msg)
            return {
                'status': 'insufficient_data',
                'accuracy': None,
                'total_samples': total_samples,
                'message': msg
            }

        # Check each category has at least 2 samples for stratify
        category_counts = combined_df['category'].value_counts()
        can_stratify = all(count >= 2 for count in category_counts)

        X = combined_df['complaint_text']
        y = combined_df['category']

        # Step 6: Split — safe stratify handling
        if can_stratify and total_samples >= 20:
            X_train, X_test, y_train, y_test = train_test_split(
                X, y,
                test_size=0.2,
                random_state=42,
                stratify=y
            )
        else:
            # Too few samples for stratified split
            logger.warning("Not enough samples per category for stratified split. Using random split.")
            X_train, X_test, y_train, y_test = train_test_split(
                X, y,
                test_size=0.2,
                random_state=42
            )

        # Step 7: Train TF-IDF
        vectorizer = TfidfVectorizer(
            max_features=5000,
            ngram_range=(1, 2),
            sublinear_tf=True    # better for short texts
        )
        X_train_vec = vectorizer.fit_transform(X_train)
        X_test_vec = vectorizer.transform(X_test)

        # Step 8: Train SVM
        classifier = SVC(
            kernel='linear',
            C=1.0,
            probability=True,
            random_state=42
        )
        classifier.fit(X_train_vec, y_train)

        # Step 9: Evaluate
        y_pred = classifier.predict(X_test_vec)
        accuracy = accuracy_score(y_test, y_pred)
        report = classification_report(y_test, y_pred, output_dict=True)

        logger.info(f"Accuracy: {accuracy:.3f}")
        logger.info(f"Classification report:\n{classification_report(y_test, y_pred)}")

        # Step 10: Save models
        os.makedirs(MODEL_DIR, exist_ok=True)
        with open(VECTORIZER_PATH, 'wb') as f:
            pickle.dump(vectorizer, f)
        with open(CLASSIFIER_PATH, 'wb') as f:
            pickle.dump(classifier, f)

        logger.info("Models saved successfully")

        # Step 11: Hot-reload in-memory models
        from app.core.models import reload_models
        reload_models()

        logger.info("=== Retraining pipeline complete ===")

        return {
            'status': 'retraining_completed',
            'accuracy': round(float(accuracy), 3),
            'total_samples': total_samples,
            'original_samples': len(training_df),
            'feedback_samples': len(feedback_df),
            'message': f'Retrained with {total_samples} samples. Accuracy: {accuracy:.3f}'
        }

    except Exception as e:
        logger.error(f"Retraining failed: {str(e)}", exc_info=True)
        return {
            'status': 'retraining_failed',
            'accuracy': None,
            'total_samples': 0,
            'message': str(e)
        }

# ==============================================================================
# app/utils/preprocessing.py
# ==============================================================================

"""
Preprocessing utilities.
Shared functions for text cleaning and normalization.
"""

import re


def normalize_whitespace(text: str) -> str:
    """Normalize whitespace in text."""
    return re.sub(r'\s+', ' ', text).strip()


def remove_urls(text: str) -> str:
    """Remove URLs from text."""
    return re.sub(r'http\S+', '', text)


def remove_special_chars(text: str) -> str:
    """Remove special characters, keeping letters and spaces."""
    return re.sub(r'[^a-zA-Z\s]', ' ', text)

