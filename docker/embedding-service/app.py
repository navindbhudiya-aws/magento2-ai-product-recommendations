"""
Enhanced Embedding Service for Magento 2 AI Product Recommendations
Version: 2.0.0

Features:
- Uses all-mpnet-base-v2 (768 dims) for better accuracy
- Normalized embeddings for cosine similarity
- Multi-model support (fast/accurate)

Endpoints:
    POST /embed - Generate embeddings for texts
    GET /health - Health check
    GET /models - List available models
"""

from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer
import numpy as np
import logging
import os

app = Flask(__name__)

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Model registry - accurate is now default for better recommendations
MODELS = {
    'fast': {
        'name': 'all-MiniLM-L6-v2',
        'dimension': 384,
        'description': 'Fast, good for testing'
    },
    'accurate': {
        'name': 'all-mpnet-base-v2',
        'dimension': 768,
        'description': 'Best quality (RECOMMENDED)'
    }
}

# Default to accurate model
DEFAULT_MODEL = os.environ.get('EMBEDDING_MODEL', 'accurate')
_loaded_models = {}


def get_model(model_key=None):
    """Load and cache model"""
    global _loaded_models
    
    if model_key is None:
        model_key = DEFAULT_MODEL
    if model_key not in MODELS:
        model_key = DEFAULT_MODEL
    
    if model_key not in _loaded_models:
        model_name = MODELS[model_key]['name']
        logger.info(f"Loading model: {model_name}")
        _loaded_models[model_key] = SentenceTransformer(model_name)
        dim = _loaded_models[model_key].get_sentence_embedding_dimension()
        logger.info(f"Model loaded. Dimension: {dim}")
    
    return _loaded_models[model_key]


@app.route('/health', methods=['GET'])
def health():
    """Health check"""
    try:
        m = get_model()
        return jsonify({
            'status': 'ok',
            'model': MODELS[DEFAULT_MODEL]['name'],
            'dimension': m.get_sentence_embedding_dimension(),
            'default_model': DEFAULT_MODEL
        })
    except Exception as e:
        logger.error(f"Health check failed: {str(e)}")
        return jsonify({'status': 'error', 'error': str(e)}), 500


@app.route('/models', methods=['GET'])
def list_models():
    """List available models"""
    return jsonify({
        'models': MODELS,
        'default': DEFAULT_MODEL,
        'loaded': list(_loaded_models.keys())
    })


@app.route('/embed', methods=['POST'])
def embed():
    """Generate embeddings"""
    try:
        data = request.get_json()
        
        if not data or 'texts' not in data:
            return jsonify({'error': 'Missing "texts" field'}), 400
        
        texts = data['texts']
        model_key = data.get('model', DEFAULT_MODEL)
        
        if not isinstance(texts, list):
            return jsonify({'error': '"texts" must be a list'}), 400
        
        if len(texts) == 0:
            return jsonify({
                'embeddings': [],
                'model': model_key,
                'dimension': MODELS.get(model_key, MODELS[DEFAULT_MODEL])['dimension']
            })
        
        logger.info(f"Generating embeddings for {len(texts)} texts using {model_key}")
        
        m = get_model(model_key)
        
        # Generate normalized embeddings for cosine similarity
        embeddings = m.encode(
            texts,
            convert_to_numpy=True,
            show_progress_bar=False,
            normalize_embeddings=True
        )
        
        embeddings_list = embeddings.tolist()
        
        logger.info(f"Generated {len(embeddings_list)} embeddings (dim: {len(embeddings_list[0])})")
        
        return jsonify({
            'embeddings': embeddings_list,
            'model': model_key,
            'dimension': len(embeddings_list[0])
        })
        
    except Exception as e:
        logger.error(f"Error generating embeddings: {str(e)}")
        return jsonify({'error': str(e)}), 500


@app.route('/', methods=['GET'])
def index():
    """Root endpoint"""
    return jsonify({
        'service': 'Enhanced Embedding Service',
        'version': '2.0.0',
        'default_model': MODELS[DEFAULT_MODEL],
        'endpoints': {
            'POST /embed': 'Generate embeddings',
            'GET /health': 'Health check',
            'GET /models': 'List models'
        }
    })


# Pre-load default model
with app.app_context():
    try:
        get_model(DEFAULT_MODEL)
    except Exception as e:
        logger.error(f"Failed to load model: {str(e)}")


if __name__ == '__main__':
    port = int(os.environ.get('PORT', 8001))
    app.run(host='0.0.0.0', port=port, debug=False)
