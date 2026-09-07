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