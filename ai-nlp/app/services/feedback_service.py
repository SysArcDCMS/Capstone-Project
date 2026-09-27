"""
Feedback service: Handles logging and statistics for HITL corrections.
Adapted from Flask version for FastAPI.
"""

import os
import pandas as pd
import logging
from datetime import datetime
from typing import Dict, Any, Optional

from app.config import settings

logger = logging.getLogger(__name__)

FEEDBACK_PATH = os.path.join(settings.data_dir, 'feedback_data.csv')

FEEDBACK_COLUMNS = [
    'timestamp',
    'complaint_text',
    'original_category',
    'corrected_category',
    'original_severity',
    'corrected_severity',
    'final_decision',
    'engineer_id',
    'source',
    'used_for_training' 
]


def log_feedback(data: dict, background_tasks=None) -> dict:

    os.makedirs(settings.data_dir, exist_ok=True)

    if os.path.exists(FEEDBACK_PATH):
        df = pd.read_csv(FEEDBACK_PATH)
        if 'used_for_training' not in df.columns:
            df['used_for_training'] = False
    else:
        df = pd.DataFrame(columns=FEEDBACK_COLUMNS)

    complaint_text = data.get('complaint_text', '').strip()

    # ── Duplicate guard ──────────────────────────────────────
    # Only Engineer decisions reach here.
    # If same complaint_text is pending (not yet trained),
    # UPDATE it — this means Engineer corrected their own submission
    pending_mask = (
        (df['complaint_text'].str.strip() == complaint_text) &
        (df['used_for_training'] == False)
    )

    if pending_mask.any():
        idx = df[pending_mask].index[-1]
        df.loc[idx, 'corrected_category'] = data.get('corrected_category', '')
        df.loc[idx, 'corrected_severity']  = data.get('corrected_severity', '')
        df.loc[idx, 'original_severity']   = data.get('original_severity', '')
        df.loc[idx, 'final_decision']      = data.get('final_decision', '')
        df.loc[idx, 'engineer_id']         = data.get('engineer_id', '')
        df.loc[idx, 'timestamp']           = datetime.now().isoformat()
        df.to_csv(FEEDBACK_PATH, index=False)

        logger.info(
            f"Engineer resubmission detected — "
            f"updated pending correction for: '{complaint_text[:50]}'"
        )

        return {
            'status':                 'feedback_updated',
            'total_feedback':         len(df),
            'timestamp':              df.loc[idx, 'timestamp'],
            'auto_retrain_triggered': False,
            'note':                   'Engineer correction updated'
        }
    # ── End duplicate guard ───────────────────────────────────

    # Normal append — fresh complaint correction
    new_row = {
        'timestamp':          datetime.now().isoformat(),
        'complaint_text':     complaint_text,
        'original_category':  data.get('original_category', ''),
        'corrected_category': data.get('corrected_category', ''),
        'original_severity':  data.get('original_severity', ''),
        'corrected_severity':  data.get('corrected_severity', ''),
        'final_decision':     data.get('final_decision', ''),
        'engineer_id':        data.get('engineer_id', ''),
        'source':             'hitl',
        'used_for_training':  False
    }

    df = pd.concat([df, pd.DataFrame([new_row])], ignore_index=True)
    df.to_csv(FEEDBACK_PATH, index=False)

    total = len(df)

    if new_row['original_category'] != new_row['corrected_category']:
        logger.info(
            f"CATEGORY CORRECTION by Engineer {new_row['engineer_id']}: "
            f"{new_row['original_category']} → {new_row['corrected_category']}"
        )

    if new_row['original_severity'] != new_row['corrected_severity']:
        logger.info(
            f"SEVERITY CORRECTION by Engineer {new_row['engineer_id']}: "
            f"{new_row['original_severity']} → {new_row['corrected_severity']}"
        )

    # Auto-retrain check
    from app.services.retrain_service import RETRAIN_THRESHOLD

    category_corrections = len(
        df[df['original_category'] != df['corrected_category']]
    )

    auto_retrain_triggered = False
    if category_corrections > 0 and category_corrections % RETRAIN_THRESHOLD == 0:
        logger.info(
            f"Auto-retrain triggered at {category_corrections} corrections"
        )
        if background_tasks is not None:
            from app.services.retrain_service import retrain_classifier
            background_tasks.add_task(retrain_classifier)
            auto_retrain_triggered = True

    return {
        'status':                 'feedback_logged',
        'total_feedback':         total,
        'timestamp':              new_row['timestamp'],
        'auto_retrain_triggered': auto_retrain_triggered
    }


def get_feedback_stats() -> Dict[str, Any]:
    """
    Return summary statistics about logged feedback.
    Used by Admin dashboard.
    """
    if not os.path.exists(FEEDBACK_PATH):
        return {
            'total_feedback': 0,
            'message': 'No feedback logged yet.'
        }

    df = pd.read_csv(FEEDBACK_PATH)
    total = len(df)

    # Category corrections
    cat_corrections = df[df['original_category'] != df['corrected_category']]

    # Severity corrections
    sev_corrections = df[df['original_severity'] != df['corrected_severity']]

    # Most common category corrections
    cat_pattern = (
        cat_corrections
        .groupby(['original_category', 'corrected_category'])
        .size()
        .reset_index(name='count')
        .sort_values('count', ascending=False)
        .head(5)
        .to_dict('records')
    ) if len(cat_corrections) > 0 else []

    # Most common severity corrections
    sev_pattern = (
        sev_corrections
        .groupby(['original_severity', 'corrected_severity'])
        .size()
        .reset_index(name='count')
        .sort_values('count', ascending=False)
        .head(5)
        .to_dict('records')
    ) if len(sev_corrections) > 0 else []

    return {
        'total_feedback':          total,
        'category_corrections':    len(cat_corrections),
        'severity_corrections':    len(sev_corrections),
        'category_patterns':       cat_pattern,
        'severity_patterns':       sev_pattern,
        'ready_for_retrain':       total >= 20,
        'retrain_recommendation':  (
            'Sufficient feedback for retraining.'
            if total >= 20 else
            f'Need {20 - total} more corrections before retraining.'
        )
    }


def load_feedback_for_training() -> pd.DataFrame:
    """
    Load feedback data formatted for SVM retraining.
    Only returns rows where category was corrected.
    """
    if not os.path.exists(FEEDBACK_PATH):
        return pd.DataFrame(columns=['complaint_text', 'category'])

    df = pd.read_csv(FEEDBACK_PATH)

    # Use corrected_category as the ground truth label
    training_rows = df[['complaint_text', 'corrected_category']].copy()
    training_rows.rename(
        columns={'corrected_category': 'category'},
        inplace=True
    )

    return training_rows.dropna()