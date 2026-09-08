# Specification.md
# FastAPI NLP Microservice — IMRAWS-NLP Backend

**Project:** Incident Management and Routing App and Web System for Water Services (IMRAWS-NLP)  
**Component:** Python FastAPI NLP Microservice  
**Version:** 1.0.0  
**Last Updated:** April 2026

---

## Table of Contents

1. [Overview](#1-overview)
2. [System Role in IMRAWS-NLP Architecture](#2-system-role-in-imraws-nlp-architecture)
3. [Functional Requirements](#3-functional-requirements)
4. [Non-Functional Requirements](#4-non-functional-requirements)
5. [API Endpoint Specification](#5-api-endpoint-specification)
6. [NLP Pipeline Specification](#6-nlp-pipeline-specification)
7. [Data Schemas](#7-data-schemas)
8. [Queue and Worker Specification](#8-queue-and-worker-specification)
9. [HITL Feedback and Retraining Specification](#9-hitl-feedback-and-retraining-specification)
10. [Severity Scoring Specification](#10-severity-scoring-specification)
11. [Configuration Specification](#11-configuration-specification)
12. [Error Handling Specification](#12-error-handling-specification)

---

## 1. Overview

The FastAPI NLP Microservice is the AI processing layer of the IMRAWS-NLP system. It receives plain-text customer complaints from the Laravel backend, processes them through a multi-stage NLP pipeline, and returns structured analysis results including:

- **Incident Category** — Billing / Water Quality / Metering / Operations
- **Sentiment Label** — POSITIVE / NEGATIVE / NEUTRAL
- **Severity Level** — High / Medium / Low

The microservice operates independently of the Laravel backend, communicating via HTTP REST API calls. It is responsible for all machine learning inference, feedback logging, and model retraining operations.

---

## 2. System Role in IMRAWS-NLP Architecture

```
Flutter Mobile App
       │
       ▼
Laravel Backend API  ◄──────────────────────────────┐
       │                                             │
       │  POST /api/v1/process-complaint             │
       ▼                                             │
FastAPI NLP Microservice                             │
  ├── SVM Classifier (TF-IDF)                        │
  ├── RoBERTa Sentiment Model                        │
  ├── Severity Engine                                │
  └── HITL Feedback Logger ─────── Retrain Pipeline ─┘
       │
       ▼
   PostgreSQL (via Laravel)
```

The Laravel backend is responsible for:
- User authentication and RBAC enforcement
- Routing decisions and assignment records
- Serving the mobile and web frontends

The FastAPI microservice is solely responsible for:
- NLP classification
- Sentiment analysis
- Severity scoring
- Feedback accumulation
- Model retraining

---

## 3. Functional Requirements

### FR-01: Complaint Classification
The system shall accept a complaint text string and classify it into one of four predefined categories:
- `Billing`
- `Water Quality`
- `Metering`
- `Operations`

Classification shall use a TF-IDF vectorizer paired with a Support Vector Machine (SVM) classifier. The system shall return the predicted category and a confidence score (0.0–1.0).

### FR-02: Sentiment Analysis
The system shall analyze the emotional tone of a complaint text and return:
- A sentiment label: `POSITIVE`, `NEGATIVE`, or `NEUTRAL`
- A confidence score (0.0–1.0)

Primary inference shall use the `dost-asti/RoBERTa-tl-sentiment-analysis` model. If the model is unavailable, the system shall fall back to a rule-based keyword sentiment engine.

### FR-03: Severity Scoring
The system shall compute a severity level of `High`, `Medium`, or `Low` based on a composite of:
- Sentiment label and score
- Days the complaint has been pending (`days_pending`)
- Presence of urgency keywords in the complaint text

### FR-04: Asynchronous Job Processing
The system shall accept complaints via a queued endpoint and process them asynchronously. The caller shall receive a `job_id` immediately and poll for results separately.

### FR-05: Result Retrieval
The system shall store completed analysis results in memory and make them retrievable by `job_id`.

### FR-06: HITL Feedback Logging
The system shall accept Engineer-adjudicated feedback containing the original and corrected category/severity and persist it to a CSV file for future retraining.

### FR-07: Duplicate Feedback Handling
If a feedback entry for a complaint that has not yet been used for training is resubmitted, the system shall update the existing record rather than append a duplicate.

### FR-08: Automatic Retraining Trigger
The system shall automatically trigger an SVM retraining job in the background when the number of category corrections in feedback reaches a multiple of the configured `RETRAIN_THRESHOLD` (default: 20).

### FR-09: Manual Retraining
The system shall expose an endpoint to manually trigger retraining at any time.

### FR-10: Severity Config Management
The system shall expose endpoints to retrieve and update severity scoring thresholds and urgency keyword lists at runtime without requiring a service restart.

### FR-11: Health Check
The system shall expose a health endpoint returning service status, current queue size, and results store count.

---

## 4. Non-Functional Requirements

| ID | Category | Requirement |
|----|----------|-------------|
| NFR-01 | Performance | NLP classification shall complete within 3 seconds under normal load. |
| NFR-02 | Performance | The async queue shall support up to 1,000 concurrent pending jobs (configurable). |
| NFR-03 | Availability | The service shall start successfully even if NLP models are not yet trained, logging warnings instead of crashing. |
| NFR-04 | Maintainability | Models shall be hot-reloadable after retraining without a service restart. |
| NFR-05 | Reliability | If RoBERTa inference fails at runtime, the system shall fall back to rule-based sentiment without returning an error to the caller. |
| NFR-06 | Portability | Configuration shall be managed entirely through environment variables and a `.env` file, with no hardcoded secrets. |
| NFR-07 | Security | CORS shall be configured before production deployment; the default `allow_origins=["*"]` is for development only. |
| NFR-08 | Data Integrity | Feedback records marked `used_for_training=True` shall never be modified by subsequent submissions. |

---

## 5. API Endpoint Specification

### Base URL
```
http://<host>:<port>/api/v1
```

---

### 5.1 POST `/process-complaint`
**Description:** Queue a complaint for async NLP processing.

**Request Body:**
```json
{
  "complaint_text": "Walang tubig sa amin since kahapon, grabe na!",
  "days_pending": 1
}
```

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `complaint_text` | string | Yes | — | Raw complaint text. Cannot be empty or whitespace-only. |
| `days_pending` | integer | No | `0` | Number of days since complaint was first submitted. |

**Response (HTTP 200):**
```json
{
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "queued",
  "message": "Complaint queued for processing"
}
```

**Error Responses:**
| Code | Condition |
|------|-----------|
| 400 | `complaint_text` is empty or whitespace. |
| 500 | Internal server error. |

---

### 5.2 GET `/result/{job_id}`
**Description:** Retrieve the NLP analysis result for a queued job.

**Path Parameter:** `job_id` — UUID string returned by `/process-complaint`

**Response (HTTP 200) — completed job:**
```json
{
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "completed",
  "category": "Water Quality",
  "category_confidence": 0.9231,
  "sentiment": "NEGATIVE",
  "sentiment_score": 0.9412,
  "severity": "High",
  "processed_at": "2026-04-28T10:23:45.123456"
}
```

**Response (HTTP 200) — failed job:**
```json
{
  "job_id": "...",
  "status": "failed",
  "error": "Error description",
  "processed_at": "..."
}
```

**Error Responses:**
| Code | Condition |
|------|-----------|
| 404 | `job_id` not found or still processing (not yet in results store). |

---

### 5.3 POST `/submit-feedback`
**Description:** Log an Engineer HITL correction for a processed complaint.

**Request Body:**
```json
{
  "complaint_text": "Walang tubig sa amin since kahapon!",
  "original_category": "Operations",
  "corrected_category": "Water Quality",
  "original_severity": "Medium",
  "corrected_severity": "High",
  "final_decision": "Category corrected by Engineer",
  "engineer_id": "eng_001",
  "incident_id": 42
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `complaint_text` | string | Yes | Original complaint text. Used as the unique key for deduplication. |
| `original_category` | string | Yes | Category assigned by NLP model. |
| `corrected_category` | string | Yes | Engineer-adjudicated correct category. |
| `original_severity` | string | Yes | Severity assigned by the severity engine. |
| `corrected_severity` | string | Yes | Engineer-adjudicated correct severity. |
| `final_decision` | string | No | Free-text note from Engineer. |
| `engineer_id` | string | Yes | Identifier of the Engineer making the correction. |
| `incident_id` | integer | No | Reference ID from the Laravel incidents table. |

**Response (HTTP 200):**
```json
{
  "status": "feedback_logged",
  "total_feedback": 15,
  "timestamp": "2026-04-28T10:30:00.000000",
  "auto_retrain_triggered": false
}
```

| `status` value | Meaning |
|---------------|---------|
| `feedback_logged` | New record appended. |
| `feedback_updated` | Existing pending record updated (Engineer resubmission). |

---

### 5.4 GET `/feedback-stats`
**Description:** Return statistics on accumulated feedback for the admin dashboard.

**Response (HTTP 200):**
```json
{
  "total_feedback": 25,
  "category_corrections": 18,
  "severity_corrections": 10,
  "category_patterns": [
    {"original_category": "Operations", "corrected_category": "Water Quality", "count": 7}
  ],
  "severity_patterns": [
    {"original_severity": "Low", "corrected_severity": "High", "count": 4}
  ],
  "ready_for_retrain": true,
  "retrain_recommendation": "Sufficient feedback for retraining."
}
```

---

### 5.5 POST `/retrain`
**Description:** Manually trigger SVM retraining in the background.

**Response (HTTP 200):**
```json
{
  "status": "retrain_queued",
  "accuracy": null,
  "total_samples": null,
  "message": "Retraining started in background"
}
```

---

### 5.6 GET `/severity-config`
**Description:** Retrieve current severity scoring configuration.

**Response (HTTP 200):**
```json
{
  "high_negative_threshold": 0.80,
  "medium_negative_threshold": 0.50,
  "high_days_pending": 3,
  "medium_days_pending": 1,
  "urgency_keywords": ["walang tubig", "emergency", "burst pipe"],
  "keyword_count": 35
}
```

---

### 5.7 POST `/update-severity-config`
**Description:** Update severity thresholds and urgency keywords at runtime.

**Request Body (all fields optional):**
```json
{
  "high_negative_threshold": 0.85,
  "medium_negative_threshold": 0.55,
  "high_days_pending": 2,
  "medium_days_pending": 1,
  "urgency_keywords": ["walang tubig", "grabe", "emergency"]
}
```

**Response (HTTP 200):** Same as `GET /severity-config`.

---

### 5.8 GET `/health`
**Description:** Service liveness and queue status check.

**Response (HTTP 200):**
```json
{
  "status": "online",
  "service": "FastAPI NLP Microservice",
  "message": "OK",
  "timestamp": "2026-04-28T10:00:00.000000",
  "queue_size": 3,
  "results_count": 142
}
```

---

## 6. NLP Pipeline Specification

### 6.1 Text Preprocessing — SVM Path

| Step | Operation | Notes |
|------|-----------|-------|
| 1 | Lowercase conversion | `text.lower()` |
| 2 | URL removal | Regex: `http\S+` |
| 3 | Special character removal | Keeps only `[a-zA-Z\s]` |
| 4 | Whitespace normalization | Collapses multiple spaces |

> Taglish words (Filipino-English mix) are intentionally preserved. Stopword removal is NOT applied to maintain SVM performance on short domain-specific text.

### 6.2 Text Preprocessing — RoBERTa Path

| Step | Operation | Notes |
|------|-----------|-------|
| 1 | Whitespace normalization | Collapses multiple spaces |
| 2 | Truncation | Max 1,800 characters (~512 RoBERTa tokens) |

> Stopwords and special characters are preserved. RoBERTa requires full natural language context.

### 6.3 SVM Classifier

| Parameter | Value |
|-----------|-------|
| Vectorizer | `TfidfVectorizer` |
| Max features | 5,000 |
| N-gram range | (1, 2) — unigrams and bigrams |
| Sublinear TF | `True` (better for short texts) |
| Kernel | Linear |
| C | 1.0 |
| Probability | `True` (enables `predict_proba`) |

**Output:** `(category: str, confidence: float)`

**Fallback:** If models are not loaded, returns `("Operations", 0.0)`.

### 6.4 RoBERTa Sentiment Model

| Parameter | Value |
|-----------|-------|
| Model | `dost-asti/RoBERTa-tl-sentiment-analysis` |
| Task | `sentiment-analysis` |
| Top-k | 1 |
| Token limit | 512 |

**Output:** `(label: str, score: float)` — label is one of `POSITIVE`, `NEGATIVE`, `NEUTRAL`.

**Fallback:** Rule-based keyword matching returns `(label, score)`. This is a last-resort fallback only and is not intended for production use.

---

## 7. Data Schemas

### 7.1 ComplaintRequest
| Field | Type | Required | Default |
|-------|------|----------|---------|
| `complaint_text` | string | Yes | — |
| `days_pending` | integer | No | 0 |

### 7.2 ComplaintResponse
| Field | Type |
|-------|------|
| `job_id` | string (UUID) |
| `status` | string |
| `message` | string |

### 7.3 AnalysisResult
| Field | Type | Nullable |
|-------|------|----------|
| `job_id` | string | No |
| `status` | string | No |
| `category` | string | Yes |
| `category_confidence` | float | Yes |
| `sentiment` | string | Yes |
| `sentiment_score` | float | Yes |
| `severity` | string | Yes |
| `processed_at` | string (ISO 8601) | Yes |
| `error` | string | Yes |

### 7.4 FeedbackRequest
| Field | Type | Required |
|-------|------|----------|
| `complaint_text` | string | Yes |
| `original_category` | string | Yes |
| `corrected_category` | string | Yes |
| `original_severity` | string | Yes |
| `corrected_severity` | string | Yes |
| `final_decision` | string | No |
| `engineer_id` | string | Yes |
| `incident_id` | integer | No |

### 7.5 feedback_data.csv Schema
| Column | Type | Description |
|--------|------|-------------|
| `timestamp` | ISO 8601 | Time of feedback submission |
| `complaint_text` | string | Original complaint |
| `original_category` | string | NLP-assigned category |
| `corrected_category` | string | Engineer-corrected category |
| `original_severity` | string | System-assigned severity |
| `corrected_severity` | string | Engineer-corrected severity |
| `final_decision` | string | Engineer note |
| `engineer_id` | string | Submitting engineer |
| `source` | string | Always `"hitl"` |
| `used_for_training` | boolean | Whether included in a retrain run |

---

## 8. Queue and Worker Specification

### 8.1 Queue Design

The system uses `asyncio.Queue` for in-process job queueing. Jobs are not persisted to disk — they exist only in memory. A service restart will clear all pending jobs.

| Property | Value |
|----------|-------|
| Queue type | `asyncio.Queue` |
| Max size | Configurable via `MAX_QUEUE_SIZE` (default: 1,000) |
| Persistence | None (in-memory only) |
| Worker count | 1 background coroutine |

### 8.2 Worker Behavior

- The worker coroutine (`process_jobs_worker`) starts at application startup via `asyncio.create_task`.
- It processes one job at a time, sequentially.
- On job failure, it stores an error result and continues to the next job.
- On worker-level exception (e.g., queue corruption), it sleeps 1 second and retries.

### 8.3 Results Store

Results are stored in a module-level Python dict (`results_store`). This is also in-memory only. Results are never evicted — the store grows indefinitely for the lifetime of the service process.

> **Production note:** For high-volume deployments, replace `results_store` with Redis and the asyncio queue with a Redis-backed queue (e.g., Celery or ARQ).

---

## 9. HITL Feedback and Retraining Specification

### 9.1 Retraining Pipeline Steps

| Step | Action |
|------|--------|
| 1 | Load `training_data.csv` (base seed data) |
| 2 | Load feedback rows where `used_for_training=False` AND `original_category ≠ corrected_category` |
| 3 | Deduplicate feedback against training data by `complaint_text` |
| 4 | Merge new feedback permanently into `training_data.csv` |
| 5 | Combine base + new feedback for this training run |
| 6 | Stratified train/test split (80/20); falls back to random split if any category has < 2 samples |
| 7 | Fit `TfidfVectorizer` on training set |
| 8 | Fit `SVC` on vectorized training set |
| 9 | Evaluate on test set; log accuracy and classification report |
| 10 | Save vectorizer and classifier as `.pkl` files |
| 11 | Hot-reload in-memory models via `reload_models()` |

### 9.2 Minimum Data Requirements

| Condition | Behavior |
|-----------|----------|
| Total samples < 10 | Retraining aborted; returns `insufficient_data` status |
| Any category < 2 samples | Falls back to non-stratified split |
| Total samples < 20 | Non-stratified split used |

### 9.3 Valid Categories for Training
Only rows with categories in `['Billing', 'Water Quality', 'Metering', 'Operations']` are used. All other values are filtered out silently.

### 9.4 Auto-Retrain Trigger
```
if (category_corrections > 0) and (category_corrections % RETRAIN_THRESHOLD == 0):
    → trigger retrain_classifier() as background task
```
Default `RETRAIN_THRESHOLD = 20`.

---

## 10. Severity Scoring Specification

### 10.1 Scoring Logic

```
HIGH if:
  (sentiment == NEGATIVE AND score >= high_negative_threshold)
  OR any urgency keyword found in text
  OR days_pending >= high_days_pending

MEDIUM if:
  (sentiment == NEGATIVE AND score >= medium_negative_threshold)
  OR sentiment == NEUTRAL
  OR days_pending >= medium_days_pending

LOW otherwise
```

### 10.2 Default Thresholds

| Parameter | Default Value |
|-----------|---------------|
| `high_negative_threshold` | 0.80 |
| `medium_negative_threshold` | 0.50 |
| `high_days_pending` | 3 |
| `medium_days_pending` | 1 |

### 10.3 Config Persistence

Severity config is loaded at startup in this priority order:
1. `data/severity_config.json` (file on disk)
2. Laravel config endpoint (`LARAVEL_CONFIG_URL` env var), if set
3. Hardcoded defaults

On update via `/update-severity-config`, the new config is saved to `data/severity_config.json` and applied immediately in memory.

---

## 11. Configuration Specification

All settings are managed via environment variables or a `.env` file.

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | `FastAPI NLP Microservice` | Application display name |
| `VERSION` | `1.0.0` | Application version |
| `DEBUG` | `False` | Enables reload and debug logging |
| `HOST` | `0.0.0.0` | Uvicorn bind address |
| `PORT` | `8000` | Uvicorn port |
| `MODEL_DIR` | `models` | Directory containing `.pkl` model files |
| `DATA_DIR` | `data` | Directory for CSV data and config files |
| `LOG_DIR` | `logs` | Directory for log file output |
| `LARAVEL_CONFIG_URL` | `None` | Optional URL to fetch severity config from Laravel |
| `MAX_QUEUE_SIZE` | `1000` | Max async job queue depth |
| `WORKER_TIMEOUT` | `30` | Reserved for future worker timeout enforcement |
| `LOG_LEVEL` | `INFO` | Python logging level |

---

## 12. Error Handling Specification

| Scenario | Behavior |
|----------|----------|
| Models not trained at startup | Logs warning; service starts normally; classification returns fallback `("Operations", 0.0)` |
| RoBERTa inference fails at runtime | Logs error; falls back to rule-based sentiment; does not surface error to caller |
| Job processing exception | Stores `status: failed` with error message in results store; worker continues |
| Feedback CSV missing | Creates new CSV with correct columns on first write |
| `training_data.csv` missing | Retraining aborted with `insufficient_data`; logs error with remediation instruction |
| Laravel config URL unreachable | Logs warning; uses file or default config instead |
| Severity config save fails | Logs error; in-memory config still updated for current session |