"""
Severity scoring engine.
Adapted from Flask version for FastAPI.
Combines RoBERTa sentiment + days_pending + urgency keywords.
Config loaded from JSON file, with fallback defaults.

Capstone DFD Process 2.9 — Calculate Composite Severity Score:
computes a numeric composite from sentiment, days elapsed, and keyword
frequency. Feeds Process 2.10 — Assign Severity Level.
"""

import logging
import requests
import os
import json

logger = logging.getLogger(__name__)

from app.config import settings

# Config file path
CONFIG_FILE = os.path.join(settings.data_dir, 'severity_config.json')

# ── Default Config ────────────────────────────────────────
# Overwritten at startup by load from file or Laravel/DB
_CONFIG = {
    'high_negative_threshold':   0.80,
    'medium_negative_threshold': 0.50,
    'high_days_pending':         3,
    'medium_days_pending':       1,
    'urgency_keywords': [
        # Tagalog
        'walang tubig', 'tumagas', 'amoy', 'mabaho',
        'hindi malinis', 'may kulay', 'may worm', 'may uod',
        'apurahan', 'delikado', 'agad', 'tulungan',
        'ilang araw na', 'matagal na', 'hindi pa rin',
        'hindi pa naaayos', 'wala pa ring tubig',
        'bumagsak na', 'baha',
        # Taglish
        'grabe na', 'sobrang tagal', 'days na',
        'hindi pa rin naaayos', 'wala na talaga',
        'emergency na ito', 'kailangan na agad',
        # English
        'no water', 'leak', 'burst pipe', 'busted pipe',
        'contaminated', 'emergency', 'urgent', 'flooding',
        'no supply', 'broken meter', 'hazardous',
        'health risk', 'days without water',
    ],
    # ── Capstone Process 2.9 — composite score weights ───────────
    # composite = sentiment_score + days_bonus + keyword_hits*0.1
    'days_pending_weight':      0.10,
    'keyword_hit_weight':       0.10,
    'keyword_hit_max':          3,    # cap keyword contribution
}


def _load_from_file():
    """
    Load severity config from JSON file on startup.
    Falls back to defaults if file doesn't exist.
    """
    global _CONFIG
    if os.path.exists(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, 'r') as f:
                data = json.load(f)
            _CONFIG.update(data)
            logger.info("Severity config loaded from file.")
        except Exception as e:
            logger.warning(f"Could not load config from file: {e}. Using defaults.")
    else:
        logger.info("No config file found. Using defaults.")


def _load_from_laravel():
    """
    Fetch latest severity config from Laravel API on startup.
    Laravel reads from tbl_severity_config (latest row).
    """
    global _CONFIG
    if settings.laravel_config_url:
        try:
            response = requests.get(settings.laravel_config_url, timeout=5)
            if response.status_code == 200:
                data = response.json()
                _CONFIG.update({
                    'high_negative_threshold':
                        float(data.get('high_negative_threshold', _CONFIG['high_negative_threshold'])),
                    'medium_negative_threshold':
                        float(data.get('medium_negative_threshold', _CONFIG['medium_negative_threshold'])),
                    'high_days_pending':
                        int(data.get('high_days_pending', _CONFIG['high_days_pending'])),
                    'medium_days_pending':
                        int(data.get('medium_days_pending', _CONFIG['medium_days_pending'])),
                    'urgency_keywords':
                        data.get('urgency_keywords', _CONFIG['urgency_keywords']),
                })
                logger.info("Severity config loaded from Laravel DB.")
        except Exception as e:
            logger.warning(f"Could not reach Laravel config endpoint: {e}. Using file or defaults.")


# Load from file first, then try Laravel
_load_from_file()
_load_from_laravel()


def get_severity(
    sentiment_label: str,
    sentiment_score: float,
    days_pending: int,
    text: str
) -> str:
    """
    Determine severity level: High / Medium / Low

    Logic:
    HIGH   → strongly NEGATIVE sentiment
              OR urgency keyword detected
              OR complaint pending too long
    MEDIUM → moderately NEGATIVE
              OR NEUTRAL sentiment
              OR pending 1-2 days
    LOW    → POSITIVE or mildly negative, fresh complaint
    """
    severity, _ = get_severity_with_score(
        sentiment_label, sentiment_score, days_pending, text
    )
    return severity


def compute_composite_score(
    sentiment_label: str,
    sentiment_score: float,
    days_pending: int,
    text: str
) -> float:
    """
    Capstone DFD Process 2.9 — Calculate Composite Severity Score.

    composite = sentiment_contribution
              + days_pending_bonus
              + frequency_of_urgency_keywords * weight

    Higher = more urgent. Caller persists this to tbl_incidents.
    """
    text_lower = text.lower()

    # 1. Sentiment contribution: 0 for POSITIVE, score for NEGATIVE,
    #    half for NEUTRAL.
    if sentiment_label == 'NEGATIVE':
        sentiment_contrib = float(sentiment_score)
    elif sentiment_label == 'NEUTRAL':
        sentiment_contrib = float(sentiment_score) * 0.5
    else:
        sentiment_contrib = 0.0

    # 2. Days-pending bonus: linear up to high_days_pending.
    days_bonus = min(days_pending, _CONFIG['high_days_pending']) * _CONFIG['days_pending_weight']

    # 3. Keyword-hit frequency (capped). Matches substring presence;
    #    each hit contributes _keyword_hit_weight.
    keyword_hits = sum(
        1 for kw in _CONFIG['urgency_keywords'] if kw in text_lower
    )
    keyword_bonus = min(keyword_hits, _CONFIG['keyword_hit_max']) * _CONFIG['keyword_hit_weight']

    composite = sentiment_contrib + days_bonus + keyword_bonus
    return round(composite, 4)


def get_severity_with_score(
    sentiment_label: str,
    sentiment_score: float,
    days_pending: int,
    text: str
) -> tuple[str, float]:
    """
    Like get_severity() but returns (severity_level, composite_score).
    Capstone Process 2.9 + 2.10 combined.
    """
    text_lower = text.lower()

    urgency_hit = any(
        kw in text_lower
        for kw in _CONFIG['urgency_keywords']
    )

    composite = compute_composite_score(sentiment_label, sentiment_score, days_pending, text)

    # ── HIGH ─────────────────────────────────────────────
    if (
        (
            sentiment_label == 'NEGATIVE'
            and sentiment_score >= _CONFIG['high_negative_threshold']
        )
        or urgency_hit
        or days_pending >= _CONFIG['high_days_pending']
    ):
        return "High", composite

    # ── MEDIUM ───────────────────────────────────────────
    elif (
        (
            sentiment_label == 'NEGATIVE'
            and sentiment_score >= _CONFIG['medium_negative_threshold']
        )
        or sentiment_label == 'NEUTRAL'
        or days_pending >= _CONFIG['medium_days_pending']
    ):
        return "Medium", composite

    # ── LOW ──────────────────────────────────────────────
    else:
        return "Low", composite


def reload_config(new_config: dict) -> dict:
    """
    Update config in-memory and save to file.
    Called by /update-severity-config endpoint.
    """
    global _CONFIG

    if 'high_negative_threshold' in new_config:
        _CONFIG['high_negative_threshold'] = float(new_config['high_negative_threshold'])

    if 'medium_negative_threshold' in new_config:
        _CONFIG['medium_negative_threshold'] = float(new_config['medium_negative_threshold'])

    if 'high_days_pending' in new_config:
        _CONFIG['high_days_pending'] = int(new_config['high_days_pending'])

    if 'medium_days_pending' in new_config:
        _CONFIG['medium_days_pending'] = int(new_config['medium_days_pending'])

    if 'urgency_keywords' in new_config:
        _CONFIG['urgency_keywords'] = list(new_config['urgency_keywords'])

    # Save to file
    try:
        os.makedirs(settings.data_dir, exist_ok=True)
        with open(CONFIG_FILE, 'w') as f:
            json.dump(_CONFIG, f, indent=2)
        logger.info("Config saved to file.")
    except Exception as e:
        logger.error(f"Could not save config to file: {e}")

    logger.info(
        f"Severity config updated. "
        f"HIGH threshold: {_CONFIG['high_negative_threshold']}, "
        f"Keywords count: {len(_CONFIG['urgency_keywords'])}"
    )

    return get_current_config()


def get_current_config() -> dict:
    """Return current config."""
    return {
        'high_negative_threshold':   _CONFIG['high_negative_threshold'],
        'medium_negative_threshold': _CONFIG['medium_negative_threshold'],
        'high_days_pending':         _CONFIG['high_days_pending'],
        'medium_days_pending':       _CONFIG['medium_days_pending'],
        'urgency_keywords':          _CONFIG['urgency_keywords'],
        'keyword_count':             len(_CONFIG['urgency_keywords']),
    }