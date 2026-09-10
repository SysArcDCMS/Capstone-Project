# FastAPI NLP Microservice - Setup

Short setup guide for the FastAPI NLP microservice that powers text
classification + sentiment analysis + severity scoring. This service is
consumed by the Laravel backend (see `../imraws-backend-website/SETUP.md`).

The full Laravel setup guide already covers prerequisites (PHP, XAMPP,
PostgreSQL). This doc only covers the Python side.

---

## Prerequisites

- **Python 3.11 or 3.13** (tested on 3.13; 3.11 also works)
- About 500 MB free disk for the RoBERTa model cache
- Internet access for first-time model download from huggingface.co

> **The original `requirements.txt` pinned versions from 2023
> (`fastapi==0.104.1`, `scikit-learn==1.3.2`) which do not compile on
> Python 3.13.** We updated the pins to lower bounds that install cleanly
> on both 3.11 and 3.13:

```txt
fastapi>=0.110
uvicorn[standard]>=0.27
pydantic>=2.7
pydantic-settings>=2.3
transformers>=4.40
torch>=2.2
scikit-learn>=1.5
pandas>=2.2
numpy>=1.26
requests>=2.31
python-multipart>=0.0.9
```

---

## Quick Install

From the repo root:

```powershell
cd ai-nlp

# 1. Create the virtualenv (renamed from "fastapi_nlp_env" -> "ai_nlp_env")
python -m venv ai_nlp_env

# 2. Upgrade pip
ai_nlp_env\Scripts\python -m pip install --upgrade pip

# 3. Install dependencies in 3 groups (the torch install is large)
ai_nlp_env\Scripts\python -m pip install fastapi "uvicorn[standard]" "pydantic>=2.7" "pydantic-settings>=2.3" requests python-multipart
ai_nlp_env\Scripts\python -m pip install scikit-learn pandas numpy
ai_nlp_env\Scripts\python -m pip install transformers torch
```

---

## Configure

```powershell
copy .env.example .env
```

Edit `.env`:

```ini
PORT=8001                # NB: different from Laravel's 8000
HOST=0.0.0.0
LARAVEL_CONFIG_URL=      # leave blank unless using remote config endpoint
MAX_QUEUE_SIZE=1000
WORKER_TIMEOUT=30
LOG_LEVEL=INFO
```

---

## Train the Model (one-time)

The 509-sample training set ships at `data/training_data.csv`. To (re)train:

```powershell
ai_nlp_env\Scripts\python train_classifier.py
```

Expected output:

```
Accuracy: 0.961
Total samples: 508
Models saved successfully
```

Models land at:

- `models/tfidf_vectorizer.pkl`
- `models/svm_classifier.pkl`

---

## Run

```powershell
ai_nlp_env\Scripts\python -m app.main
```

> **First startup takes 30-60 seconds** because the RoBERTa sentiment
> model is downloaded from Hugging Face (~500 MB). Subsequent restarts
> take ~5 seconds (the cache is reused).

Verify:

```powershell
curl http://127.0.0.1:8001/api/v1/health
```

Expected:

```json
{"status":"online","service":"FastAPI NLP Microservice","timestamp":"..."}
```

---

## API Quick Reference

All endpoints are mounted under `/api/v1`.

| Method | URL | Notes |
|---|---|---|
| POST | `/process-complaint` | Submit complaint text; returns `job_id` |
| GET | `/result/{job_id}` | Poll for classification result |
| POST | `/submit-feedback` | HITL correction log |
| GET | `/feedback-stats` | Correction patterns |
| POST | `/retrain` | Trigger retraining with accumulated feedback |
| POST | `/update-severity-config` | Tune severity thresholds + urgency keywords |
| GET | `/severity-config` | Get current severity config |
| GET | `/health` | Service health check |

### Quick test

```powershell
$body = @{
    complaint_text = "Walang tubig sa amin since kahapon pa, grabe na."
    days_pending   = 0
} | ConvertTo-Json

curl -Method POST -Uri http://127.0.0.1:8001/api/v1/process-complaint `
    -ContentType "application/json" `
    -Body $body
```

You get back:

```json
{"job_id":"...", "status":"queued", "message":"..."}
```

Then poll:

```powershell
curl http://127.0.0.1:8001/api/v1/result/{job_id}
```

Returns:

```json
{
  "job_id": "...",
  "status": "completed",
  "category": "Operations",
  "category_confidence": 0.97,
  "sentiment": "NEGATIVE",
  "sentiment_score": 0.8,
  "composite_score": 1.0,
  "severity": "High"
}
```

---

## Docker (alternative)

A `Dockerfile` is included for production deployment.

```powershell
cd ai-nlp
docker build -t imraws-nlp:latest .
docker run -d -p 8001:8001 --name imraws-nlp imraws-nlp:latest
```

> **Docker is not required for local dev.** We only recommend it for the
> Oracle Cloud VPS production deployment.

---

## Troubleshooting

For Laravel-related gotchas (port conflicts, firewall, etc.), see
`../imraws-backend-website/SETUP.md` §16. NLP-specific issues:

### RoBERTa download times out

Cause: Hugging Face download interrupted.

Fix:
```powershell
# Pre-download with explicit timeout
$env:HUGGINGFACE_HUB_DOWNLOAD_TIMEOUT = "600"
ai_nlp_env\Scripts\python -m app.main
```

### `ModuleNotFoundError: No module named 'app'`

Cause: Running from the wrong directory.

Fix: `cd ai-nlp` before running.

### Port 8001 in use

Fix: Edit `.env` to use a different port; also update
`NLP_SERVICE_URL=http://127.0.0.1:<new-port>` in
`../imraws-backend-website/.env` so Laravel can find it.

---

## What this service outputs (key field)

Every complaint classification returns **`composite_score`** alongside
the categorical `severity`. This is the **DFD 2.9 Composite Severity
Score** from the capstone document:

```
composite_score = sentiment_score
                 + days_pending * 0.10
                 + min(keyword_hits, 3) * 0.10
```

The Laravel backend stores this on `tbl_incidents.composite_score` so
the panel can verify the calculation. See
`app/core/models/severity.py:99-122` in this service and
`imraws-backend-website/SETUP.md` Appendix A for the variance log.
