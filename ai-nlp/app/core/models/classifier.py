"""
TF-IDF + SVM complaint classifier.
Adapted from Flask version for FastAPI.
"""

import pickle
import re
import os
import logging

logger = logging.getLogger(__name__)

# Module-level model references
_vectorizer = None
_classifier = None

# Use settings for paths
from app.config import settings

MODEL_DIR = settings.model_dir
VECTORIZER_PATH = os.path.join(MODEL_DIR, 'tfidf_vectorizer.pkl')
CLASSIFIER_PATH = os.path.join(MODEL_DIR, 'svm_classifier.pkl')


def preprocess_for_svm(text: str) -> str:
    """
    Minimal cleaning for TF-IDF vectorizer.
    Keeps Taglish words intact — just normalizes formatting.
    """
    text = text.lower()
    # Remove URLs
    text = re.sub(r'http\S+', '', text)
    # Remove special characters except spaces
    text = re.sub(r'[^a-zA-Z\s]', ' ', text)
    # Collapse multiple spaces
    text = re.sub(r'\s+', ' ', text).strip()
    return text


def load_classifier():
    """
    Load saved TF-IDF vectorizer and SVM model from disk.
    Called once at FastAPI startup.
    If no model exists yet, logs a warning —
    run train_classifier.py first.
    """
    global _vectorizer, _classifier

    if not os.path.exists(VECTORIZER_PATH):
        logger.warning(
            f"No trained vectorizer found at {VECTORIZER_PATH}. "
            "Run train_classifier.py to generate models."
        )
        return

    if not os.path.exists(CLASSIFIER_PATH):
        logger.warning(
            f"No trained classifier found at {CLASSIFIER_PATH}. "
            "Run train_classifier.py to generate models."
        )
        return

    try:
        with open(VECTORIZER_PATH, 'rb') as f:
            _vectorizer = pickle.load(f)

        with open(CLASSIFIER_PATH, 'rb') as f:
            _classifier = pickle.load(f)

        logger.info("SVM classifier loaded successfully.")
    except Exception as e:
        logger.error(f"Error loading classifier: {e}")


def classify_complaint(text: str):
    """
    Classify complaint text into one of 4 categories.

    Returns:
        category   (str)   - Billing / Water Quality / Metering / Operations
        confidence (float) - highest class probability
    """
    if _vectorizer is None or _classifier is None:
        # Fallback if model not trained yet
        logger.warning("Classifier not loaded. Returning default category.")
        return "Operations", 0.0

    try:
        cleaned = preprocess_for_svm(text)
        vector = _vectorizer.transform([cleaned])

        category = _classifier.predict(vector)[0]
        probabilities = _classifier.predict_proba(vector)[0]
        confidence = probabilities.max()

        return category, confidence
    except Exception as e:
        logger.error(f"Error in classification: {e}")
        return "Operations", 0.0


def reload_models():
    """
    Reload models from disk after retraining.
    Called by retrain.py after saving new models.
    """
    global _vectorizer, _classifier

    try:
        with open(VECTORIZER_PATH, 'rb') as f:
            _vectorizer = pickle.load(f)

        with open(CLASSIFIER_PATH, 'rb') as f:
            _classifier = pickle.load(f)

        logger.info("Classifier reloaded after retraining.")
    except Exception as e:
        logger.error(f"Error reloading models: {e}")