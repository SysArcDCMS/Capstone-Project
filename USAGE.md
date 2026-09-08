# IMRAWS-NLP Usage Guide

## Quick Start

### 1. Setup
```bash
cd fastapi_nlp
python -m venv fastapi_nlp_env
source fastapi_nlp_env/bin/activate
pip install -r requirements.txt
```

### 2. Train Models (First Time)
```bash
# Ensure data/training_data.csv exists with your training data
python retrain.py
```

### 3. Start Service
```bash
python -m app.main
# Service runs at http://localhost:8000
```

## API Usage

### Process a Complaint
```bash
# Submit complaint
curl -X POST http://localhost:8000/api/v1/process-complaint \
  -H "Content-Type: application/json" \
  -d '{"complaint_text": "Walang tubig sa amin since kahapon"}'

# Response: {"job_id": "abc123", "status": "queued"}

# Get result
curl http://localhost:8000/api/v1/result/abc123
```

### Health Check
```bash
curl http://localhost:8000/api/v1/health
```

### Submit Feedback (Engineer Correction)
```bash
curl -X POST http://localhost:8000/api/v1/submit-feedback \
  -H "Content-Type: application/json" \
  -d '{
    "complaint_text": "...",
    "original_category": "Billing",
    "corrected_category": "Operations",
    "original_severity": "Low",
    "corrected_severity": "High",
    "engineer_id": "eng_001"
  }'
```

### Get Feedback Stats
```bash
curl http://localhost:8000/api/v1/feedback-stats
```

### Retrain Models
```bash
curl -X POST http://localhost:8000/api/v1/retrain
```

### Update Severity Config
```bash
curl -X POST http://localhost:8000/api/v1/update-severity-config \
  -H "Content-Type: application/json" \
  -d '{"high_negative_threshold": 0.75}'
```

### Get Severity Config
```bash
curl http://localhost:8000/api/v1/severity-config
```

## Docker

```bash
docker build -t fastapi-nlp .
docker run -p 8000:8000 fastapi-nlp
```

## Configuration

Environment variables (create `.env` file):
- `DEBUG` - Enable debug mode (default: false)
- `HOST` - Server host (default: 0.0.0.0)
- `PORT` - Server port (default: 8000)
- `MODEL_DIR` - Directory for model files (default: models)
- `DATA_DIR` - Directory for data files (default: data)
- `LOG_DIR` - Directory for logs (default: logs)

## Categories

- **Billing** - Payment and billing issues
- **Water Quality** - Water quality concerns
- **Metering** - Meter-related issues
- **Operations** - General operational issues

## Languages

- English
- Tagalog
- Taglish (Filipino-English mix)
