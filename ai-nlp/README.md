# FastAPI NLP Microservice

A FastAPI-based microservice for processing water service complaints using NLP. Performs classification, sentiment analysis, and severity assessment with async background processing and HITL feedback loop.

## Features

- **Async Processing**: FastAPI with asyncio for concurrent requests
- **Background Queue**: Job queuing for scalable complaint processing
- **NLP Pipeline**:
  - TF-IDF + SVM classification (Billing, Water Quality, Metering, Operations)
  - RoBERTa sentiment analysis (Positive, Negative, Neutral)
  - Configurable severity scoring
- **HITL Feedback**: Log corrections and retrain models
- **Clean Architecture**: Modular design with separation of concerns

## Quick Start

1. Create virtual environment:
```bash
python -m venv ai_nlp_env
source ai_nlp_env/bin/activate  # Linux/Mac
```

2. Install dependencies:
```bash
pip install -r requirements.txt
```

3. Run the service:
```bash
python -m app.main
```

The service will be available at `http://localhost:8000`

## API Endpoints

### Complaints
- `POST /api/v1/process-complaint`: Queue complaint for processing
- `GET /api/v1/result/{job_id}`: Get processing result

### Feedback
- `POST /api/v1/submit-feedback`: Log HITL corrections
- `GET /api/v1/feedback-stats`: Get feedback statistics

### Admin
- `POST /api/v1/retrain`: Trigger model retraining
- `POST /api/v1/update-severity-config`: Update severity rules
- `GET /api/v1/severity-config`: Get current config

### Health
- `GET /api/v1/health`: Service health check

## Configuration

Environment variables:
- `DEBUG`: Enable debug mode (default: false)
- `HOST`: Server host (default: 0.0.0.0)
- `PORT`: Server port (default: 8000)
- `MODEL_DIR`: Directory for model files (default: models)
- `DATA_DIR`: Directory for data files (default: data)
- `LOG_DIR`: Directory for logs (default: logs)

## Development

Follow the instructions in `AGENTS.md` for consistent development practices.

## License

[Add license information]