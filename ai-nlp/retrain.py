"""
SVM retraining script.
Combines original training data + feedback corrections.
Retrains and saves new TF-IDF + SVM models.
"""

import os
import pandas as pd
import pickle
import logging
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.svm import SVC
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score

from app.config import settings
from app.services.feedback_service import load_feedback_for_training

logger = logging.getLogger(__name__)

# Model paths
MODEL_DIR = settings.model_dir
VECTORIZER_PATH = os.path.join(MODEL_DIR, 'tfidf_vectorizer.pkl')
CLASSIFIER_PATH = os.path.join(MODEL_DIR, 'svm_classifier.pkl')

# Original training data (placeholder - replace with actual data)
ORIGINAL_DATA_PATH = os.path.join(settings.data_dir, 'training_data.csv')


def load_original_training_data() -> pd.DataFrame:
    """Load original labeled training data."""
    if os.path.exists(ORIGINAL_DATA_PATH):
        return pd.read_csv(ORIGINAL_DATA_PATH)
    else:
        # Placeholder data for demonstration
        logger.warning("No original training data found. Using placeholder.")
        return pd.DataFrame({
            'complaint_text': [
                'Billing issue with my water bill',
                'Water quality is poor and contaminated',
                'Meter is broken and not working',
                'No water supply in my area'
            ],
            'category': ['Billing', 'Water Quality', 'Metering', 'Operations']
        })


def retrain_classifier() -> dict:
    """
    Retrain SVM classifier using original data + feedback corrections.

    Returns:
        dict: Retraining results
    """
    try:
        # Load original training data
        original_df = load_original_training_data()

        # Load feedback corrections
        feedback_df = load_feedback_for_training()

        # Combine datasets
        combined_df = pd.concat([original_df, feedback_df], ignore_index=True)

        if len(combined_df) < 10:
            return {
                'status': 'insufficient_data',
                'message': f'Need at least 10 samples, got {len(combined_df)}'
            }

        # Prepare data
        X = combined_df['complaint_text']
        y = combined_df['category']

        # Split for validation
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42, stratify=y
        )

        # Train TF-IDF vectorizer
        vectorizer = TfidfVectorizer(max_features=5000, ngram_range=(1, 2))
        X_train_vec = vectorizer.fit_transform(X_train)
        X_test_vec = vectorizer.transform(X_test)

        # Train SVM classifier
        classifier = SVC(kernel='linear', C=1.0, probability=True, random_state=42)
        classifier.fit(X_train_vec, y_train)

        # Evaluate
        y_pred = classifier.predict(X_test_vec)
        accuracy = accuracy_score(y_test, y_pred)

        # Save models
        os.makedirs(MODEL_DIR, exist_ok=True)
        with open(VECTORIZER_PATH, 'wb') as f:
            pickle.dump(vectorizer, f)
        with open(CLASSIFIER_PATH, 'wb') as f:
            pickle.dump(classifier, f)

        # Reload models in the app
        from app.core.models import reload_models
        reload_models()

        logger.info(f"Retraining completed. Accuracy: {accuracy:.3f}")
        return {
            'status': 'retraining_completed',
            'accuracy': round(accuracy, 3),
            'total_samples': len(combined_df),
            'original_samples': len(original_df),
            'feedback_samples': len(feedback_df),
            'message': f'Retrained with {len(combined_df)} samples, accuracy: {accuracy:.3f}'
        }

    except Exception as e:
        logger.error(f"Retraining failed: {str(e)}")
        return {
            'status': 'retraining_failed',
            'message': str(e)
        }


if __name__ == "__main__":
    # Run retraining when script is executed directly
    result = retrain_classifier()
    print(result)