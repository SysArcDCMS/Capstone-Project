# Documentation.md
# FastAPI NLP Microservice — Developer & Operations Guide

**Project:** IMRAWS-NLP  
**Component:** Python FastAPI NLP Microservice  
**Version:** 1.0.0  
**Last Updated:** April 2026

---

## Table of Contents

1. [Project Structure](#1-project-structure)
2. [Setup and Installation](#2-setup-and-installation)
3. [Environment Configuration](#3-environment-configuration)
4. [Running the Service](#4-running-the-service)
5. [Module Reference](#5-module-reference)
6. [NLP Models](#6-nlp-models)
7. [Training the SVM Classifier](#7-training-the-svm-classifier)
8. [API Usage Examples](#8-api-usage-examples)
9. [HITL Feedback Workflow](#9-hitl-feedback-workflow)
10. [Retraining Workflow](#10-retraining-workflow)
11. [Severity Configuration](#11-severity-configuration)
12. [Integration with Laravel](#12-integration-with-laravel)
13. [Logging](#13-logging)
14. [Known Limitations and Production Notes](#14-known-limitations-and-production-notes)
15. [Glossary](#15-glossary)

---

## 1. Project Structure

```
fastapi-nlp-microservice/
│
├── app/
│   ├── __init__.py
│   ├── main.py                    # FastAPI app entry point, startup/shutdown events
│   ├── config.py                  # Pydantic settings, env var binding
│   ├── dependencies.py            # Dependency injection helpers
│   │
│   ├── core/
│   │   ├── __init__.py
│   │   ├── config/
│   │   │   └── __init__.py
│   │   └── models/
│   │       ├── __init__.py        # Re-exports all model functions
│   │       ├── classifier.py      # TF-IDF + SVM complaint classification
│   │       ├── sentiment.py       # RoBERTa sentiment analysis + fallback
│   │       └── severity.py        # Composite severity scoring engine
│   │
│   ├── routers/
│   │   ├── __init__.py
│   │   ├── complaints.py          # /process-complaint, /result/{job_id}
│   │   ├── feedback.py            # /submit-feedback, /feedback-stats
│   │   ├── admin.py               # /retrain, /update-severity-config, /severity-config
│   │   └── health.py              # /health
│   │
│   ├── schemas/
│   │   ├── __init__.py            # Re-exports all schemas
│   │   ├── complaints.py          # ComplaintRequest, ComplaintResponse, AnalysisResult
│   │   ├── feedback.py            # FeedbackRequest, FeedbackResponse, FeedbackStats
│   │   ├── admin.py               # SeverityConfigUpdate, SeverityConfigResponse, RetrainResponse
│   │   └── health.py              # HealthResponse
│   │
│   ├── services/
│   │   ├── __init__.py
│   │   ├── nlp_service.py         # Orchestrates classifier + sentiment + severity
│   │   ├── queue_service.py       # asyncio.Queue job management and worker
│   │   ├── feedback_service.py    # CSV-based feedback persistence and stats
│   │   └── retrain_service.py     # Full SVM retraining pipeline
│   │
│   └── utils/
│       └── preprocessing.py       # Shared text cleaning utilities
│
├── models/                        # Saved .pkl model files (git-ignored)
│   ├── tfidf_vectorizer.pkl
│   └── svm_classifier.pkl
│
├── data/                          # Training data and feedback (git-ignore sensitive files)
│   ├── training_data.csv
│   ├── feedback_data.csv
│   └── severity_config.json
│
├── logs/                          # Log output directory (git-ignored)
│   └── fastapi_app.log
│
├── .env                           # Environment variables (git-ignored)
├── requirements.txt
└── README.md
```

---

## 2. Setup and Installation

### 2.1 Prerequisites

| Requirement | Version |
|-------------|---------|
| Python | 3.10+ |
| pip | Latest |
| Virtual environment | Recommended |

### 2.2 Create Virtual Environment

```bash
python -m venv venv
source venv/bin/activate        # Linux / macOS
venv\Scripts\activate           # Windows
```

### 2.3 Install Dependencies

```bash
pip install -r requirements.txt
```

**Core dependencies:**

```
fastapi
uvicorn[standard]
pydantic
pydantic-settings
scikit-learn
transformers
torch
pandas
requests
```

> The `transformers` and `torch` packages are required for RoBERTa sentiment analysis. On first run, the model (~500MB) will be downloaded from HuggingFace and cached locally. If these packages are not installed, the service will fall back to rule-based sentiment and log a warning.

### 2.4 Create Required Directories

```bash
mkdir -p models data logs
```

### 2.5 Add Training Data

Place your seed training data at `data/training_data.csv`. The file must have these exact columns:

```
complaint_text,category
"Ang taas ng bill ko ngayong buwan","Billing"
"May amoy ang tubig namin","Water Quality"
```

Valid `category` values: `Billing`, `Water Quality`, `Metering`, `Operations`.

---

## 3. Environment Configuration

Create a `.env` file in the project root:

```env
# App
APP_NAME=FastAPI NLP Microservice
VERSION=1.0.0
DEBUG=False

# Server
HOST=0.0.0.0
PORT=8000

# Paths
MODEL_DIR=models
DATA_DIR=data
LOG_DIR=logs

# Laravel integration (optional)
LARAVEL_CONFIG_URL=http://your-laravel-app.com/api/severity-config

# Queue
MAX_QUEUE_SIZE=1000
WORKER_TIMEOUT=30

# Logging
LOG_LEVEL=INFO
```

All variables are optional — the service uses the defaults shown above if a `.env` file is absent.

---

## 4. Running the Service

### 4.1 Development

```bash
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

### 4.2 Production

```bash
uvicorn app.main:app --host 0.0.0.0 --port 8000 --workers 1
```

> Use `--workers 1` only. The asyncio queue and in-memory results store are not shared across processes. For multi-worker deployments, replace the queue with Redis (see [Section 14](#14-known-limitations-and-production-notes)).

### 4.3 Via `__main__`

```bash
python -m app.main
```

### 4.4 Startup Sequence

When the service starts, it executes the following in order:

1. Bind settings from `.env`
2. Configure logging (file + stdout)
3. Register routers under `/api/v1`
4. Apply CORS middleware
5. **`startup_event`:**
   - Call `load_classifier()` — loads `tfidf_vectorizer.pkl` and `svm_classifier.pkl`
   - Call `load_sentiment_model()` — loads RoBERTa from cache or HuggingFace
   - Launch `process_jobs_worker()` as a background asyncio task

If either model file is missing, a warning is logged and the service continues. Classification will return fallback values until models are trained.

---

## 5. Module Reference

### 5.1 `app/config.py` — Settings

Singleton `Settings` object built with `pydantic-settings`. Reads from `.env` and environment variables. Accessed throughout the codebase as:

```python
from app.config import settings
settings.model_dir   # → "models"
settings.log_level   # → "INFO"
```

The `protected_namespaces=('settings_',)` config suppresses Pydantic's warning about field names starting with `model_`.

---

### 5.2 `app/core/models/classifier.py` — SVM Classifier

#### `load_classifier()`
Loads `tfidf_vectorizer.pkl` and `svm_classifier.pkl` from `MODEL_DIR` into module-level globals `_vectorizer` and `_classifier`. Called once at startup. Safe to call if files don't exist — logs warning and returns.

#### `classify_complaint(text: str) → (category: str, confidence: float)`
Preprocesses text, vectorizes with TF-IDF, runs SVM prediction. Returns the top predicted class and its probability score.

```python
category, confidence = classify_complaint("Ang tubig ay may amoy na pormal")
# → ("Water Quality", 0.8912)
```

#### `reload_models()`
Re-reads `.pkl` files from disk into memory. Called by `retrain_service.py` after saving new models to enable hot-reload without a service restart.

#### `preprocess_for_svm(text: str) → str`
Lowercase → strip URLs → remove non-alpha characters → normalize spaces.

---

### 5.3 `app/core/models/sentiment.py` — Sentiment Analysis

#### `load_sentiment_model()`
Loads the RoBERTa pipeline from HuggingFace. Sets `_sentiment_pipeline` global. Falls back gracefully if `transformers`/`torch` are not installed.

#### `analyze_sentiment(text: str) → (label: str, score: float)`
Preprocesses and runs the RoBERTa pipeline. Falls back to `_rule_based_sentiment()` if the pipeline is unavailable or raises an exception.

```python
label, score = analyze_sentiment("Grabe na walang tubig kami!")
# → ("NEGATIVE", 0.9412)
```

#### `preprocess_for_roberta(text: str) → str`
Normalizes whitespace and truncates to 1,800 characters.

#### `_rule_based_sentiment(text: str) → (label: str, score: float)` *(private)*
Keyword counting fallback. Counts occurrences of hardcoded negative/positive word lists. Not for production use.

---

### 5.4 `app/core/models/severity.py` — Severity Engine

#### `get_severity(sentiment_label, sentiment_score, days_pending, text) → str`
Core scoring function. Returns `"High"`, `"Medium"`, or `"Low"`. See logic in [Specification Section 10](#10-severity-scoring-specification).

#### `reload_config(new_config: dict) → dict`
Merges `new_config` into `_CONFIG`, saves to `data/severity_config.json`, and returns the updated config.

#### `get_current_config() → dict`
Returns a copy of the current `_CONFIG` dict including `keyword_count`.

**Module-level initialization (runs at import time):**
1. `_load_from_file()` — reads `data/severity_config.json`
2. `_load_from_laravel()` — calls `LARAVEL_CONFIG_URL` if set

---

### 5.5 `app/services/nlp_service.py` — NLP Orchestrator

#### `process_complaint(text: str, days_pending: int) → dict`
Runs the full pipeline: `classify_complaint` → `analyze_sentiment` → `get_severity`. Returns:

```python
{
    "category": "Water Quality",
    "category_confidence": 0.8912,
    "sentiment": "NEGATIVE",
    "sentiment_score": 0.9412,
    "severity": "High"
}
```

This is the only function the queue worker calls. All NLP logic is encapsulated here.

---

### 5.6 `app/services/queue_service.py` — Job Queue

#### `add_job(job_id, text, days_pending)` *(async)*
Puts a job dict onto `job_queue`. Raises `asyncio.QueueFull` if the queue is at capacity.

#### `get_result(job_id)` *(async)*
Returns the result dict from `results_store`, or `{"status": "not_found"}` if absent.

#### `process_jobs_worker()` *(async coroutine)*
Infinite loop. Dequeues one job, calls `process_complaint()`, stores result in `results_store`. On job failure, stores error result. On loop-level exception, sleeps 1 second and retries.

#### `get_queue_status() → dict`
Returns `queue_size`, `results_count`, and `max_queue_size`. Used by the health endpoint.

---

### 5.7 `app/services/feedback_service.py` — Feedback Persistence

#### `log_feedback(data: dict, background_tasks=None) → dict`
Main feedback entry point. Handles duplicate detection, appends or updates `feedback_data.csv`, checks auto-retrain threshold.

**Duplicate detection logic:**
```
IF complaint_text exists in CSV AND used_for_training == False:
    UPDATE existing row (Engineer resubmission)
ELSE:
    APPEND new row
```

#### `get_feedback_stats() → dict`
Reads `feedback_data.csv` and returns aggregated correction counts, top-5 misclassification patterns, and retrain readiness flag.

#### `load_feedback_for_training() → pd.DataFrame`
Returns all feedback rows formatted as `[complaint_text, category]` using `corrected_category` as the label. Used by the retrain pipeline.

---

### 5.8 `app/services/retrain_service.py` — Retraining Pipeline

#### `retrain_classifier() → dict`
Full pipeline function. See [Section 10](#10-retraining-workflow) for step-by-step walkthrough.

#### `load_training_data() → pd.DataFrame`
Reads and validates `training_data.csv`. Filters invalid categories and empty rows.

#### `load_new_feedback() → pd.DataFrame`
Reads unused category corrections from `feedback_data.csv`. Marks used rows with `used_for_training=True` and saves the file.

#### `dedup_feedback_against_training(training_df, feedback_df) → pd.DataFrame`
Removes feedback rows whose `complaint_text` already exists in `training_data.csv` (case-insensitive).

#### `merge_into_training_data(new_feedback_df)`
Appends new feedback rows into `training_data.csv` permanently so they are included as base data in all future retraining runs.

---

### 5.9 `app/utils/preprocessing.py` — Shared Utilities

Standalone stateless helper functions:

| Function | Description |
|----------|-------------|
| `normalize_whitespace(text)` | Collapses multiple spaces |
| `remove_urls(text)` | Strips `http...` patterns |
| `remove_special_chars(text)` | Keeps only `[a-zA-Z\s]` |

These mirror logic inside `classifier.py` and are available for reuse in other modules.

---

## 6. NLP Models

### 6.1 SVM Classifier Files

| File | Description |
|------|-------------|
| `models/tfidf_vectorizer.pkl` | Fitted `TfidfVectorizer` — vocabulary and IDF weights |
| `models/svm_classifier.pkl` | Fitted `SVC` — decision boundary weights |

Both files are Python `pickle` objects. They must be generated by running the training script before the classifier can produce real results.

### 6.2 RoBERTa Sentiment Model

The model is downloaded automatically from HuggingFace on first use:

```
dost-asti/RoBERTa-tl-sentiment-analysis
```

It is cached at the default HuggingFace cache location (`~/.cache/huggingface/`). Subsequent runs load from cache. No manual download is required.

**First-run note:** The download is approximately 500MB. Ensure the deployment environment has internet access and sufficient disk space during first startup.

---

## 7. Training the SVM Classifier

Before the classifier can produce real results, you must train it once using your seed data.

### 7.1 Prepare Training Data

Ensure `data/training_data.csv` exists with at minimum 10 rows:

```csv
complaint_text,category
"Sobrang taas ng bill ko ngayong buwan kahit parehong konsumo lang","Billing"
"May amoy ang tubig namin, parang yung kalawang","Water Quality"
"Hindi gumagalaw ang metro namin since last month","Metering"
"Walang tubig sa amin simula kahapon ng hapon","Operations"
```

### 7.2 Trigger Training

**Option A — Via API endpoint (service must be running):**
```bash
curl -X POST http://localhost:8000/api/v1/retrain
```

**Option B — Via direct Python call:**
```python
from app.services.retrain_service import retrain_classifier
result = retrain_classifier()
print(result)
```

### 7.3 Verify Training Succeeded

```bash
ls -lh models/
# tfidf_vectorizer.pkl
# svm_classifier.pkl
```

Check logs for:
```
INFO: SVM classifier loaded successfully.
INFO: Accuracy: 0.XXX
```

---

## 8. API Usage Examples

All examples use `curl`. Base URL: `http://localhost:8000/api/v1`

### 8.1 Submit a Complaint

```bash
curl -X POST http://localhost:8000/api/v1/process-complaint \
  -H "Content-Type: application/json" \
  -d '{
    "complaint_text": "Grabe na walang tubig kami simula kahapon, emergency na ito!",
    "days_pending": 1
  }'
```

**Response:**
```json
{
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "queued",
  "message": "Complaint queued for processing"
}
```

### 8.2 Poll for Result

```bash
curl http://localhost:8000/api/v1/result/550e8400-e29b-41d4-a716-446655440000
```

**Response:**
```json
{
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "completed",
  "category": "Operations",
  "category_confidence": 0.7821,
  "sentiment": "NEGATIVE",
  "sentiment_score": 0.9634,
  "severity": "High",
  "processed_at": "2026-04-28T10:23:45.123456"
}
```

> If the job hasn't been processed yet, the endpoint returns HTTP 404. Poll with a short delay (e.g., 500ms) until the result is available.

### 8.3 Submit Engineer Feedback

```bash
curl -X POST http://localhost:8000/api/v1/submit-feedback \
  -H "Content-Type: application/json" \
  -d '{
    "complaint_text": "Grabe na walang tubig kami simula kahapon, emergency na ito!",
    "original_category": "Operations",
    "corrected_category": "Water Quality",
    "original_severity": "High",
    "corrected_severity": "High",
    "final_decision": "Corrected category - water supply issue not operations",
    "engineer_id": "eng_007",
    "incident_id": 101
  }'
```

### 8.4 Check Health

```bash
curl http://localhost:8000/api/v1/health
```

### 8.5 Update Severity Config

```bash
curl -X POST http://localhost:8000/api/v1/update-severity-config \
  -H "Content-Type: application/json" \
  -d '{
    "high_negative_threshold": 0.85,
    "high_days_pending": 2
  }'
```

---

## 9. HITL Feedback Workflow

The HITL (Human-in-the-Loop) system allows Engineers to correct NLP decisions and feed those corrections back into the model.

### 9.1 Standard Flow

```
1. Laravel receives Team Leader rejection/correction
2. Engineer reviews in web portal and submits final decision
3. Laravel calls POST /api/v1/submit-feedback with the correction
4. Microservice appends to feedback_data.csv
5. If category_corrections % 20 == 0 → auto-retrain triggered
6. Next retrain run uses correction as training sample
```

### 9.2 Resubmission Behavior

If an Engineer submits feedback for the same complaint text a second time (before it has been used in training), the system **updates** the existing row rather than appending a duplicate. This handles the case where an Engineer corrects their own previous decision.

Only the following fields are updated on resubmission:
- `corrected_category`
- `corrected_severity`
- `original_severity`
- `final_decision`
- `engineer_id`
- `timestamp`

### 9.3 Feedback File Location

```
data/feedback_data.csv
```

This file is the single source of truth for all HITL corrections. Back it up before running any retrain operations.

---

## 10. Retraining Workflow

### 10.1 When Retraining Happens

| Trigger | How |
|---------|-----|
| Auto-trigger | Every 20th category correction in feedback |
| Manual API call | `POST /api/v1/retrain` |
| Direct Python call | `retrain_classifier()` |

### 10.2 What Happens to feedback_data.csv

When a retrain run reads feedback rows, it marks them `used_for_training=True` immediately. This prevents double-counting in future runs. The rows remain in the file for audit purposes.

### 10.3 What Happens to training_data.csv

New feedback rows are **permanently merged** into `training_data.csv` after each retrain. This means:
- Future retrain runs do not need the original feedback rows to still be "unused"
- The base training set grows over time as more corrections are validated

### 10.4 Hot Reload

After saving new `.pkl` files, `retrain_classifier()` calls `reload_models()` which replaces the in-memory `_vectorizer` and `_classifier` globals. Subsequent requests immediately use the new model. **No service restart required.**

### 10.5 Retrain Result Object

```json
{
  "status": "retraining_completed",
  "accuracy": 0.874,
  "total_samples": 320,
  "original_samples": 300,
  "feedback_samples": 20,
  "message": "Retrained with 320 samples. Accuracy: 0.874"
}
```

---

## 11. Severity Configuration

### 11.1 Runtime Update

Severity thresholds and urgency keywords can be updated without restarting the service:

```bash
curl -X POST http://localhost:8000/api/v1/update-severity-config \
  -H "Content-Type: application/json" \
  -d '{
    "urgency_keywords": [
      "walang tubig", "emergency", "burst pipe", "baha", "delikado"
    ]
  }'
```

Changes take effect immediately for all subsequent complaints.

### 11.2 Config File

Changes are persisted to `data/severity_config.json`. This file is loaded on the next service startup, so config survives restarts.

### 11.3 Laravel Sync

If `LARAVEL_CONFIG_URL` is set, the service fetches config from Laravel on startup (after loading from file). This allows the Administrator to configure severity settings via the Laravel admin portal and have them apply to the microservice on restart.

---

## 12. Integration with Laravel

### 12.1 Laravel → Microservice Calls

The Laravel backend is expected to make the following calls:

| Event | Laravel Action | Microservice Call |
|-------|---------------|-------------------|
| Customer submits complaint | Complaint created in DB | `POST /api/v1/process-complaint` |
| Poll for NLP result | Cron job or webhook | `GET /api/v1/result/{job_id}` |
| Engineer submits adjudication | Feedback saved to `tbl_feedback` | `POST /api/v1/submit-feedback` |
| Admin requests retrain | Admin action in web portal | `POST /api/v1/retrain` |
| Admin updates severity config | Config form submitted | `POST /api/v1/update-severity-config` |
| Health monitoring | Scheduled check | `GET /api/v1/health` |

### 12.2 Storing the job_id

When Laravel calls `/process-complaint`, it should store the returned `job_id` alongside the incident record in `tbl_incidents`. This `job_id` is needed to retrieve the NLP result.

### 12.3 Recommended Polling Strategy

```
1. POST /process-complaint → store job_id in tbl_incidents
2. Wait 500ms
3. GET /result/{job_id}
4. If 404: wait 500ms, retry (max 10 attempts)
5. If completed: update tbl_incidents with category/severity/sentiment
6. If failed: log error, flag incident for manual review
```

### 12.4 CORS

The service currently allows all origins (`allow_origins=["*"]`). For production, restrict to the Laravel application's domain:

```python
# app/main.py
app.add_middleware(
    CORSMiddleware,
    allow_origins=["https://your-laravel-domain.com"],
    ...
)
```

---

## 13. Logging

### 13.1 Log Format

```
2026-04-28 10:23:45,123 [INFO] app.services.nlp_service: Processed complaint: category=Water Quality, sentiment=NEGATIVE(0.94), severity=High
```

### 13.2 Log Output

Logs are written to both:
- **Console (stdout)** — for container/systemd visibility
- **File:** `logs/fastapi_app.log` — for persistent storage

### 13.3 Log Level

Set via `LOG_LEVEL` env var. Default: `INFO`.

For debugging NLP pipeline issues, use `DEBUG`:
```env
LOG_LEVEL=DEBUG
```

### 13.4 Key Log Messages

| Message | Meaning |
|---------|---------|
| `SVM classifier loaded successfully.` | Models loaded from disk on startup |
| `RoBERTa sentiment model loaded successfully.` | RoBERTa ready |
| `Classifier not loaded. Returning default category.` | Models missing; using fallback |
| `Falling back to rule-based sentiment.` | RoBERTa unavailable or failed |
| `Job {id} completed successfully` | Async job finished |
| `Auto-retrain triggered at N corrections` | Threshold hit |
| `Retraining pipeline complete` | Full retrain finished |
| `Engineer resubmission detected` | Duplicate feedback updated |

---

## 14. Known Limitations and Production Notes

### 14.1 In-Memory Queue and Results Store

The `asyncio.Queue` and `results_store` dict are in-memory only. A service restart clears all pending jobs and cached results.

**Impact:** If the service restarts while jobs are queued, those jobs are lost. Laravel will receive a 404 on the next poll and should handle it by resubmitting the complaint.

**Recommendation for production:** Replace with Redis-backed queue (e.g., [ARQ](https://arq-docs.helpmanual.io/) or Celery with Redis broker).

### 14.2 Single Worker

Only one background coroutine processes jobs. Under high load, jobs queue up rather than being parallelized.

**Recommendation:** For high throughput, migrate to Celery with multiple worker processes.

### 14.3 CORS

`allow_origins=["*"]` must be replaced with the specific Laravel domain before production deployment.

### 14.4 Pickle Security

Model files are loaded with `pickle`. Only load `.pkl` files from trusted sources. Never accept model files via API uploads.

### 14.5 RoBERTa First-Run Latency

The first sentiment analysis request after a fresh deployment may take 10–30 seconds while the model loads. Subsequent requests use the cached pipeline.

### 14.6 Language Coverage

The SVM classifier is trained on your `training_data.csv`. Its accuracy on Taglish (Filipino-English mixed text) depends entirely on the quality and coverage of your training data. The RoBERTa model (`dost-asti/RoBERTa-tl-sentiment-analysis`) is specifically trained for Tagalog/Taglish sentiment.

### 14.7 feedback_data.csv Growth

The feedback CSV is never truncated. Over time it may grow large. Rows with `used_for_training=True` can be archived periodically without affecting system behavior.

---

## 15. Glossary

| Term | Definition |
|------|------------|
| **IMRAWS-NLP** | Incident Management and Routing App and Web System for Water Services using NLP — the full capstone project this microservice belongs to. |
| **HITL** | Human-in-the-Loop. The feedback mechanism where Engineers review and correct AI decisions. |
| **SVM** | Support Vector Machine. The classification algorithm used to categorize complaints. |
| **TF-IDF** | Term Frequency–Inverse Document Frequency. The text vectorization method used to convert complaint text into numerical features for the SVM. |
| **RoBERTa** | A transformer-based language model used for sentiment analysis. |
| **Taglish** | Filipino-English code-switched text commonly used in Philippine complaint messages. |
| **Severity** | A computed priority level (High / Medium / Low) assigned to each complaint based on sentiment, days pending, and urgency keywords. |
| **job_id** | A UUID string returned when a complaint is queued. Used to retrieve the analysis result asynchronously. |
| **RETRAIN_THRESHOLD** | The number of category corrections that triggers an automatic model retraining. Default: 20. |
| **Hot Reload** | Replacing in-memory model objects with newly retrained versions without restarting the service. |
| **pickle** | Python's object serialization format. Used to save and load trained ML models. |
| **RBAC** | Role-Based Access Control. Enforced by the Laravel backend — not by this microservice. |