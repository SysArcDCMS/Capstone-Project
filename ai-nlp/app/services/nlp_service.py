"""
NLP service: Orchestrates classification, sentiment, and severity analysis.

Capstone DFD Level 2 (Process 2.0):
  2.7 NLP Classification         -> category
  2.8 Sentiment Analysis         -> sentiment + polarity score
  2.9 Calculate Composite Score  -> composite_score  (NEW)
  2.10 Assign Severity Level     -> severity
"""

import logging
from typing import Tuple

from app.core.models import (
    classify_complaint,
    analyze_sentiment,
    get_severity_with_score,
)

logger = logging.getLogger(__name__)


def process_complaint(text: str, days_pending: int) -> dict:
    """
    Process a complaint through the full NLP pipeline.

    Args:
        text: Complaint text
        days_pending: Days the complaint has been pending

    Returns:
        dict: Analysis results including category, sentiment, severity,
              and composite_score (capstone Process 2.9).
    """
    try:
        # Task 1: Classification (DFD 2.7)
        category, confidence = classify_complaint(text)

        # Task 2: Sentiment (DFD 2.8)
        sentiment_label, sentiment_score = analyze_sentiment(text)

        # Tasks 3+4: Composite Score + Severity (DFD 2.9 + 2.10)
        severity, composite_score = get_severity_with_score(
            sentiment_label, sentiment_score, days_pending, text
        )

        logger.info(
            f"Processed complaint: category={category}, "
            f"sentiment={sentiment_label}({sentiment_score:.2f}), "
            f"composite={composite_score:.4f}, severity={severity}"
        )

        return {
            'category': category,
            'category_confidence': round(float(confidence), 4),
            'sentiment': sentiment_label,
            'sentiment_score': round(float(sentiment_score), 4),
            'composite_score': composite_score,
            'severity': severity,
        }

    except Exception as e:
        logger.error(f"Error processing complaint: {str(e)}")
        raise