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