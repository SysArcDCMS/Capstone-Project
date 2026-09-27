# FastAPI NLP Microservice - Agent Instructions

## Environment Setup
- ALWAYS use the virtualenv `ai_nlp_env` for all Python operations
- NEVER install packages globally or modify system Python
- Activate virtualenv: `source ai_nlp_env/bin/activate` (Linux/Mac)
- All testing, running, and installations must happen in the virtualenv

## Development Guidelines
- Follow FastAPI best practices and async patterns
- Use Pydantic for all data validation
- Implement proper error handling and logging
- Maintain clean architecture with separation of concerns

## Testing
- Run tests in virtualenv only
- Ensure all dependencies are installed via virtualenv
- Use pytest for unit and integration tests

## Deployment
- Containerize with Docker using virtualenv base
- Ensure production builds use isolated environment