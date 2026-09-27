"""
Run this ONCE before starting the FastAPI server.
Generates initial tfidf_vectorizer.pkl and svm_classifier.pkl
"""

import os
import sys

# Add project root to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from app.services.retrain_service import retrain_classifier
import logging

logging.basicConfig(level=logging.INFO)

if __name__ == "__main__":
    print("Starting initial model training...")
    result = retrain_classifier()
    print(f"Result: {result}")
    
    if result['status'] == 'retraining_completed':
        print(f"Model trained successfully!")
        print(f"Accuracy: {result['accuracy']}")
        print(f"Total samples: {result['total_samples']}")
    else:
        print(f"Training failed: {result['message']}")