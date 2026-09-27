"""
Preprocessing utilities.
Shared functions for text cleaning and normalization.
"""

import re


def normalize_whitespace(text: str) -> str:
    """Normalize whitespace in text."""
    return re.sub(r'\s+', ' ', text).strip()


def remove_urls(text: str) -> str:
    """Remove URLs from text."""
    return re.sub(r'http\S+', '', text)


def remove_special_chars(text: str) -> str:
    """Remove special characters, keeping letters and spaces."""
    return re.sub(r'[^a-zA-Z\s]', ' ', text)