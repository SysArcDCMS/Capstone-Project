from pydantic import BaseModel

class HealthResponse(BaseModel):
    status: str
    service: str
    message: str = "OK"
    timestamp: str
    queue_size: int
    results_count: int    