"""
Retrain service: Handles SVM retraining pipeline.
Combines training_data.csv + unused feedback corrections.
Deduplicates, retrains, saves models, hot-reloads.
"""

import os
import pandas as pd
import pickle
import logging
from datetime import datetime
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.svm import SVC
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, classification_report

from app.config import settings

logger = logging.getLogger(__name__)

MODEL_DIR = settings.model_dir
VECTORIZER_PATH = os.path.join(MODEL_DIR, 'tfidf_vectorizer.pkl')
CLASSIFIER_PATH = os.path.join(MODEL_DIR, 'svm_classifier.pkl')
TRAINING_DATA_PATH = os.path.join(settings.data_dir, 'training_data.csv')
FEEDBACK_DATA_PATH = os.path.join(settings.data_dir, 'feedback_data.csv')

VALID_CATEGORIES = ['Billing', 'Water Quality', 'Metering', 'Operations']
MIN_SAMPLES_REQUIRED = 10
RETRAIN_THRESHOLD = 20


def load_training_data() -> pd.DataFrame:
    """Load base training data."""
    if not os.path.exists(TRAINING_DATA_PATH):
        logger.error(f"training_data.csv not found at {TRAINING_DATA_PATH}")
        logger.error("Run: copy the seed CSV to data/training_data.csv first")
        return pd.DataFrame(columns=['complaint_text', 'category'])

    df = pd.read_csv(TRAINING_DATA_PATH)

    # Validate columns
    if 'complaint_text' not in df.columns or 'category' not in df.columns:
        logger.error("training_data.csv must have 'complaint_text' and 'category' columns")
        return pd.DataFrame(columns=['complaint_text', 'category'])

    # Drop invalid categories
    df = df[df['category'].isin(VALID_CATEGORIES)]
    df = df.dropna(subset=['complaint_text', 'category'])
    df['complaint_text'] = df['complaint_text'].str.strip()
    df = df[df['complaint_text'] != '']

    logger.info(f"Loaded {len(df)} training samples")
    return df


def load_new_feedback() -> pd.DataFrame:
    if not os.path.exists(FEEDBACK_DATA_PATH):
        return pd.DataFrame(columns=['complaint_text', 'category'])

    df = pd.read_csv(FEEDBACK_DATA_PATH)

    if 'used_for_training' not in df.columns:
        df['used_for_training'] = False

    unused = df[
        (df['used_for_training'] == False) &
        (df['original_category'] != df['corrected_category'])
    ].copy()

    if len(unused) == 0:
        logger.info("No new feedback corrections to add")
        return pd.DataFrame(columns=['complaint_text', 'category'])

    # Simple dedup — same complaint_text appearing twice means
    # Engineer resubmitted. The duplicate guard in log_feedback()
    # should have caught this already, but keep as safety net.
    # Latest timestamp wins since all rows are Engineer decisions.
    unused['timestamp'] = pd.to_datetime(unused['timestamp'])
    unused = unused.sort_values('timestamp', ascending=True)
    unused = unused.drop_duplicates(
        subset=['complaint_text'],
        keep='last'
    )

    # Mark as used
    df.loc[unused.index, 'used_for_training'] = True
    df.to_csv(FEEDBACK_DATA_PATH, index=False)

    result = unused[['complaint_text', 'corrected_category']].copy()
    result.rename(columns={'corrected_category': 'category'}, inplace=True)
    result = result[result['category'].isin(VALID_CATEGORIES)]

    logger.info(f"Loaded {len(result)} Engineer-adjudicated corrections")
    return result

def dedup_feedback_against_training(
    training_df: pd.DataFrame,
    feedback_df: pd.DataFrame
) -> pd.DataFrame:
    """
    Remove feedback rows where complaint_text already exists in training data.
    Prevents duplicate training samples.
    """
    if len(feedback_df) == 0:
        return feedback_df

    existing_texts = set(
        training_df['complaint_text'].str.strip().str.lower()
    )

    mask = ~feedback_df['complaint_text'].str.strip().str.lower().isin(existing_texts)
    deduped = feedback_df[mask]

    removed = len(feedback_df) - len(deduped)
    if removed > 0:
        logger.info(f"Dedup removed {removed} feedback rows already in training data")

    return deduped


def merge_into_training_data(new_feedback_df: pd.DataFrame):
    """
    Append new feedback rows into training_data.csv permanently.
    So future retrains include them as base data.
    """
    if len(new_feedback_df) == 0:
        return

    training_df = load_training_data()
    merged = pd.concat([training_df, new_feedback_df], ignore_index=True)
    merged.to_csv(TRAINING_DATA_PATH, index=False)
    logger.info(f"Merged {len(new_feedback_df)} feedback rows into training_data.csv")


def retrain_classifier() -> dict:
    """
    Full retraining pipeline:
    1. Load base training data
    2. Load unused feedback corrections
    3. Dedup feedback against training
    4. Merge feedback into training_data.csv
    5. Retrain TF-IDF + SVM
    6. Save models
    7. Hot-reload in-memory models
    """
    try:
        logger.info("=== Starting retraining pipeline ===")

        # Step 1: Load base data
        training_df = load_training_data()

        # Step 2: Load new feedback only
        feedback_df = load_new_feedback()

        # Step 3: Dedup
        feedback_df = dedup_feedback_against_training(training_df, feedback_df)

        # Step 4: Merge feedback into training_data.csv permanently
        merge_into_training_data(feedback_df)

        # Step 5: Combine for this training run
        combined_df = pd.concat([training_df, feedback_df], ignore_index=True)
        combined_df = combined_df.dropna(subset=['complaint_text', 'category'])
        combined_df = combined_df[combined_df['complaint_text'].str.strip() != '']

        total_samples = len(combined_df)

        if total_samples < MIN_SAMPLES_REQUIRED:
            msg = f"Need at least {MIN_SAMPLES_REQUIRED} samples, got {total_samples}"
            logger.warning(msg)
            return {
                'status': 'insufficient_data',
                'accuracy': None,
                'total_samples': total_samples,
                'message': msg
            }

        # Check each category has at least 2 samples for stratify
        category_counts = combined_df['category'].value_counts()
        can_stratify = all(count >= 2 for count in category_counts)

        X = combined_df['complaint_text']
        y = combined_df['category']

        # Step 6: Split — safe stratify handling
        if can_stratify and total_samples >= 20:
            X_train, X_test, y_train, y_test = train_test_split(
                X, y,
                test_size=0.2,
                random_state=42,
                stratify=y
            )
        else:
            # Too few samples for stratified split
            logger.warning("Not enough samples per category for stratified split. Using random split.")
            X_train, X_test, y_train, y_test = train_test_split(
                X, y,
                test_size=0.2,
                random_state=42
            )

        # Step 7: Train TF-IDF
        vectorizer = TfidfVectorizer(
            max_features=5000,
            ngram_range=(1, 2),
            sublinear_tf=True    # better for short texts
        )
        X_train_vec = vectorizer.fit_transform(X_train)
        X_test_vec = vectorizer.transform(X_test)

        # Step 8: Train SVM
        classifier = SVC(
            kernel='linear',
            C=1.0,
            probability=True,
            random_state=42
        )
        classifier.fit(X_train_vec, y_train)

        # Step 9: Evaluate
        y_pred = classifier.predict(X_test_vec)
        accuracy = accuracy_score(y_test, y_pred)
        report = classification_report(y_test, y_pred, output_dict=True)

        logger.info(f"Accuracy: {accuracy:.3f}")
        logger.info(f"Classification report:\n{classification_report(y_test, y_pred)}")

        # Step 10: Save models
        os.makedirs(MODEL_DIR, exist_ok=True)
        with open(VECTORIZER_PATH, 'wb') as f:
            pickle.dump(vectorizer, f)
        with open(CLASSIFIER_PATH, 'wb') as f:
            pickle.dump(classifier, f)

        logger.info("Models saved successfully")

        # Step 11: Hot-reload in-memory models
        from app.core.models import reload_models
        reload_models()

        logger.info("=== Retraining pipeline complete ===")

        return {
            'status': 'retraining_completed',
            'accuracy': round(float(accuracy), 3),
            'total_samples': total_samples,
            'original_samples': len(training_df),
            'feedback_samples': len(feedback_df),
            'message': f'Retrained with {total_samples} samples. Accuracy: {accuracy:.3f}'
        }

    except Exception as e:
        logger.error(f"Retraining failed: {str(e)}", exc_info=True)
        return {
            'status': 'retraining_failed',
            'accuracy': None,
            'total_samples': 0,
            'message': str(e)
        }