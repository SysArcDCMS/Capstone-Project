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