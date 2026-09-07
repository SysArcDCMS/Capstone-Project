"""
Sentiment analysis using dost-asti/RoBERTa-tl-sentiment-analysis.
Adapted from Flask version for FastAPI.
"""

import logging
import re

logger = logging.getLogger(__name__)

_sentiment_pipeline = None

MODEL_NAME = "dost-asti/RoBERTa-tl-sentiment-analysis"
MAX_TOKENS = 512  # RoBERTa hard limit


def preprocess_for_roberta(text: str) -> str:
    """
    Minimal cleaning for RoBERTa.
    DO NOT remove stopwords — transformer needs full context.
    Just normalize whitespace and truncate.
    """
    # Normalize whitespace
    text = re.sub(r'\s+', ' ', text).strip()
    # Truncate to safe character length
    # (512 tokens ≈ ~1800 characters for Taglish)
    return text[:1800]


def load_sentiment_model():
    """
    Load RoBERTa model from HuggingFace.
    Downloads on first run (~500MB), cached locally after.
    Called once at FastAPI startup.
    """
    global _sentiment_pipeline

    try:
        from transformers import pipeline
        logger.info(f"Loading sentiment model: {MODEL_NAME}")
        logger.info("This may take a moment on first run...")

        _sentiment_pipeline = pipeline(
            task="sentiment-analysis",
            model=MODEL_NAME,
            top_k=1  # Return only highest scoring label
        )
        logger.info("RoBERTa sentiment model loaded successfully.")

    except Exception as e:
        logger.error(f"Failed to load sentiment model: {e}")
        logger.warning(
            "Falling back to rule-based sentiment. "
            "Install transformers and torch to use RoBERTa."
        )
        _sentiment_pipeline = None


def analyze_sentiment(text: str):
    """
    Run sentiment analysis on complaint text.

    Returns:
        label (str)   - POSITIVE / NEGATIVE / NEUTRAL
        score (float) - confidence score 0.0 to 1.0

    Example:
        "Walang tubig sa amin since kahapon!"
        → ("NEGATIVE", 0.9412)
    """
    cleaned = preprocess_for_roberta(text)

    if _sentiment_pipeline is not None:
        try:
            result = _sentiment_pipeline(cleaned)[0]
            label = result['label'].upper()
            score = result['score']
            return label, score

        except Exception as e:
            logger.error(f"RoBERTa inference error: {e}")
            logger.warning("Falling back to rule-based sentiment.")

    # ── Fallback: Rule-based sentiment ───────────────────
    return _rule_based_sentiment(text)


def _rule_based_sentiment(text: str):
    """
    Simple fallback sentiment if RoBERTa unavailable.
    Not for production — only emergency fallback.
    """
    text_lower = text.lower()

    negative_words = [
        'walang', 'wala', 'hindi', 'ayaw', 'grabe', 'matagal',
        'broken', 'leak', 'contaminated', 'mabaho', 'amoy',
        'no water', 'problema', 'mali', 'sobrang', 'galit',
        'frustrated', 'unfair', 'bad', 'poor', 'terrible',
        'worst', 'never', 'always', 'still', 'already'
    ]

    positive_words = [
        'salamat', 'thank', 'okay', 'ok', 'good', 'great',
        'naresolba', 'fixed', 'ayos na', 'sige', 'fine'
    ]

    neg_count = sum(1 for w in negative_words if w in text_lower)
    pos_count = sum(1 for w in positive_words if w in text_lower)

    if neg_count > pos_count:
        score = min(0.5 + (neg_count * 0.1), 0.95)
        return "NEGATIVE", score
    elif pos_count > neg_count:
        score = min(0.5 + (pos_count * 0.1), 0.95)
        return "POSITIVE", score
    else:
        return "NEUTRAL", 0.60