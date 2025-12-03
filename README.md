# Magento 2 AI-Powered Product Recommendation Module

[![Magento 2](https://img.shields.io/badge/Magento-2.4.6+-orange.svg)](https://magento.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

An advanced AI-powered product recommendation system for Magento 2 that uses ChromaDB vector database and Large Language Models (LLMs) to provide intelligent, personalized product suggestions.

---

## 📋 Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Module Architecture](#module-architecture)
- [Usage](#usage)
- [CLI Commands](#cli-commands)
- [API Endpoints](#api-endpoints)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

---

## ✨ Features

### Core Capabilities
- **AI-Powered Recommendations**: Uses vector embeddings and semantic similarity for intelligent product matching
- **Multi-LLM Support**: Integrates with Claude (Anthropic) and OpenAI for advanced ranking
- **ChromaDB Integration**: Leverages vector database for fast, scalable similarity searches
- **Personalization**: Tracks customer behavior (views, purchases, wishlist) for tailored recommendations
- **Real-time Updates**: Automatic indexing on product save/delete with event-driven architecture
- **Multiple Recommendation Types**:
    - Related Products (semantic similarity)
    - Cross-sell (complementary products)
    - Up-sell (premium alternatives)
    - Personalized recommendations (behavior-based)
    - Trending products (popularity-based)

### Technical Features
- **Caching Layer**: Intelligent caching with configurable TTL
- **Circuit Breaker**: Fault-tolerant external API calls
- **Diversity Filtering**: Prevents redundant recommendations
- **GraphQL Support**: Complete GraphQL API for headless commerce
- **REST API**: Full REST API with WebAPI support
- **CLI Management**: Comprehensive command-line tools
- **Widget Support**: Easy CMS page integration
- **Hyva Theme Compatible**: Ready for Hyva-based stores
- **Admin Interface**: Full configuration UI in Magento Admin
- **Indexer Integration**: Magento indexer support for embeddings
- **Cron Jobs**: Automated data sync and refresh

---

## 📦 Requirements

### System Requirements
- **Magento**: 2.4.6+ (2.4.7 or 2.4.8 recommended)
- **PHP**: 8.1+ (8.2 or 8.3 recommended)
- **MySQL/MariaDB**: 8.0+ / 10.4+
- **Elasticsearch/OpenSearch**: 7.x+ / 1.x+
- **ChromaDB Server**: Latest version
- **Memory**: Minimum 2GB PHP memory_limit

### PHP Extensions
- `json`
- `curl`
- `openssl`
- `gd` or `imagick`

### External Services
- **ChromaDB**: Vector database (can be self-hosted or cloud)
- **LLM Provider** (optional): Claude AI or OpenAI API access

---

## 🚀 Installation

### Method 1: Composer (Recommended)

```bash
# Navigate to Magento root
cd /path/to/magento2

# Require the module
composer require navindbhudiya/module-product-recommendation

# Enable the module
php bin/magento module:enable NavinDBhudiya_ProductRecommendation

# Run setup upgrade
php bin/magento setup:upgrade

# Compile DI
php bin/magento setup:di:compile

# Deploy static content (production mode)
php bin/magento setup:static-content:deploy -f

# Reindex
php bin/magento indexer:reindex

# Clear cache
php bin/magento cache:flush
```

### Method 2: Manual Installation

```bash
# Create module directory
mkdir -p app/code/NavinDBhudiya/ProductRecommendation

# Extract/copy module files to:
# app/code/NavinDBhudiya/ProductRecommendation/

# Enable and setup (same as above)
php bin/magento module:enable NavinDBhudiya_ProductRecommendation
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

### Method 3: Docker Setup (Development)

```bash
# Clone the repository
git clone https://github.com/navindbhudiya/magento2-product-recommendation.git

# Navigate to docker directory
cd magento2-product-recommendation/docker

# Start ChromaDB
docker-compose -f docker-compose.chromadb.yml up -d

# Optional: Start embedding service
docker-compose up -d
```

---

## ⚙️ Configuration

### 1. ChromaDB Setup

```bash
# Install ChromaDB (if self-hosting)
pip install chromadb

# Start ChromaDB server
chroma run --host 0.0.0.0 --port 8000

# Or use Docker
docker run -d -p 8000:8000 chromadb/chroma
```

### 2. Admin Configuration

Navigate to: **Stores → Configuration → NavinDBhudiya → Product Recommendation**

#### ChromaDB Settings
- **Enable Module**: Yes
- **ChromaDB Host**: http://localhost:8000
- **Collection Name**: magento_products
- **Batch Size**: 100 (for indexing)

#### Embedding Provider
- **Provider**: ChromaDB (built-in) or Custom Service
- **Embedding Service URL**: http://localhost:5000 (if using custom)
- **Embedding Model**: all-MiniLM-L6-v2 (default)

#### LLM Provider (Optional)
- **Enable LLM Ranking**: Yes
- **Provider**: Claude or OpenAI
- **API Key**: your-api-key-here
- **Model**: claude-3-sonnet-20240229 or gpt-4

#### Product Attributes
- **Attributes for Embeddings**:
    - name
    - description
    - short_description
    - category_names
    - brand (if available)

#### Recommendation Settings
- **Max Recommendations**: 10
- **Similarity Threshold**: 0.7
- **Enable Personalization**: Yes
- **Tracking Cookie Name**: pr_tracking
- **Cache TTL**: 3600 seconds

#### Behavior Tracking
- **Track Product Views**: Yes
- **Track Purchases**: Yes
- **Track Wishlist Adds**: Yes
- **Behavior Weight**:
    - View: 1.0
    - Cart Add: 2.0
    - Purchase: 3.0
    - Wishlist: 2.5

---

## 🏗️ Module Architecture

### Data Flow

```
┌─────────────────────────────────────────────────────────────┐
│                     MAGENTO 2 FRONTEND                       │
│  (Product Pages, CMS Widgets, API Calls)                    │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                   RECOMMENDATION LAYER                       │
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Plugins    │  │   Blocks     │  │     API      │     │
│  │ (Related/    │  │ (Widgets/    │  │ (REST/       │     │
│  │  Cross/Up)   │  │  Templates)  │  │  GraphQL)    │     │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘     │
│         └──────────────────┼──────────────────┘             │
│                            ▼                                 │
│           ┌────────────────────────────┐                    │
│           │  RecommendationService     │                    │
│           │  - Get Similar Products    │                    │
│           │  - Get Personalized        │                    │
│           │  - Apply Filters           │                    │
│           └──────────┬─────────────────┘                    │
└──────────────────────┼──────────────────────────────────────┘
                       │
         ┌─────────────┼─────────────┐
         ▼             ▼             ▼
┌────────────┐ ┌────────────┐ ┌────────────┐
│  ChromaDB  │ │    LLM     │ │   Cache    │
│   Client   │ │  Re-Ranker │ │   Layer    │
│ (Vector DB)│ │ (Optional) │ │            │
└─────┬──────┘ └────────────┘ └────────────┘
      │
      ▼
┌────────────────────────────────────────┐
│         ChromaDB Server                │
│  - Product Embeddings                  │
│  - Similarity Search                   │
│  - Vector Collections                  │
└────────────────────────────────────────┘
```

### Component Flow

#### 1. Product Indexing
```
Product Save → Observer → Indexer → Embedding Generation → ChromaDB Storage
```

#### 2. Recommendation Request
```
User Request → Plugin/Block → Service → Cache Check → ChromaDB Query → 
LLM Ranking (optional) → Diversity Filter → Response
```

#### 3. Personalization
```
User Action → Behavior Collector → Customer Profile → Weight Calculation → 
Personalized Recommendations
```

### Key Components

#### Service Layer
- **RecommendationService**: Core recommendation engine
- **ChromaClient**: ChromaDB integration
- **EmbeddingProvider**: Generates product embeddings
- **LlmReRanker**: Re-ranks with LLM intelligence
- **DiversityFilter**: Removes redundant suggestions
- **ContextBuilder**: Builds user context
- **BehaviorCollector**: Tracks user behavior

#### Model Layer
- **CustomerProfile**: Stores user preferences
- **LlmRanking**: Caches LLM results
- **ProductEmbedding**: Vector representations

#### API Layer
- **REST API**: `/rest/V1/recommendation/`
- **GraphQL**: `personalizedRecommendations` query
- **WebAPI**: Service contracts

---

## 📖 Usage

### Frontend Implementation

#### 1. Using Plugins (Automatic)
The module automatically enhances:
- Related Products block
- Cross-sell Products (cart page)
- Up-sell Products (product page)

No additional code needed!

#### 2. Using Widgets

Add to any CMS page:
```
{{widget type="NavinDBhudiya\ProductRecommendation\Block\Widget\PersonalizedProducts" 
  title="Recommended For You" 
  max_products="8" 
  template="NavinDBhudiya_ProductRecommendation::personalized/recommendations.phtml"}}
```

#### 3. Using Layout XML

```xml
<block class="NavinDBhudiya\ProductRecommendation\Block\Personalized\Recommendations"
       name="product.recommendations"
       template="NavinDBhudiya_ProductRecommendation::personalized/recommendations.phtml">
    <arguments>
        <argument name="max_products" xsi:type="number">10</argument>
        <argument name="title" xsi:type="string">Recommended For You</argument>
    </arguments>
</block>
```

#### 4. Using AJAX (Dynamic Loading)

```javascript
require(['jquery'], function($) {
    $.ajax({
        url: '/productrecommendation/ajax/personalized',
        type: 'GET',
        data: {
            product_id: 123, // optional
            max_results: 10
        },
        success: function(response) {
            console.log('Recommendations:', response);
            // Render products
        }
    });
});
```

### GraphQL Query

```graphql
query {
  personalizedRecommendations(
    customerId: 123
    currentProductId: 456
    maxResults: 10
  ) {
    product_id
    sku
    name
    price
    score
    reason
  }
}
```

### REST API Call

```bash
# Get personalized recommendations
curl -X GET "https://your-store.com/rest/V1/recommendation/personalized?customerId=123&maxResults=10" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"

# Get similar products
curl -X GET "https://your-store.com/rest/V1/recommendation/similar/123?maxResults=10" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

---

## 💻 CLI Commands

### Indexing Commands

```bash
# Index all products to ChromaDB
php bin/magento productrecommendation:index:products

# Index specific products by IDs
php bin/magento productrecommendation:index:products --product-ids=1,2,3

# Re-index specific store
php bin/magento productrecommendation:index:products --store-id=2

# Clear collection and re-index
php bin/magento productrecommendation:index:products --clear-collection
```

### Recommendation Commands

```bash
# Get similar products for a product
php bin/magento productrecommendation:similar:get 123

# Get personalized recommendations for customer
php bin/magento productrecommendation:personalized:get 456

# Test with specific store view
php bin/magento productrecommendation:personalized:get 456 --store-id=2
```

### Maintenance Commands

```bash
# Refresh customer profiles
php bin/magento productrecommendation:profile:refresh

# Refresh all profiles
php bin/magento productrecommendation:profile:refresh --all

# Refresh trending products data
php bin/magento productrecommendation:trending:refresh

# Clear ChromaDB collection
php bin/magento productrecommendation:collection:clear

# Test ChromaDB connection
php bin/magento productrecommendation:connection:test
```

### Magento Indexer

```bash
# Reindex product embeddings
php bin/magento indexer:reindex navindbhudiya_product_embedding

# Check indexer status
php bin/magento indexer:status navindbhudiya_product_embedding
```

---

## 🔧 Cron Jobs

The module includes several cron jobs:

| Job | Schedule | Description |
|-----|----------|-------------|
| `productrecommendation_sync_embeddings` | Every 6 hours | Syncs product changes to ChromaDB |
| `productrecommendation_refresh_profiles` | Daily at 2 AM | Refreshes customer profiles |
| `productrecommendation_refresh_trending` | Every hour | Updates trending products data |
| `productrecommendation_clean_cache` | Daily at 3 AM | Cleans expired cache entries |

---

## 📊 Database Tables

The module creates the following tables:

### `navindbhudiya_customer_profile`
Stores customer behavior and preferences:
- `profile_id` (primary)
- `customer_id`
- `viewed_products` (JSON)
- `purchased_products` (JSON)
- `wishlist_products` (JSON)
- `category_preferences` (JSON)
- `updated_at`

### `navindbhudiya_llm_ranking`
Caches LLM ranking results:
- `ranking_id` (primary)
- `product_id`
- `context_hash`
- `ranking_data` (JSON)
- `model_name`
- `created_at`

---

## 🐛 Troubleshooting

### ChromaDB Connection Issues

```bash
# Test connection
php bin/magento productrecommendation:connection:test

# Check ChromaDB is running
curl http://localhost:8000/api/v1/heartbeat

# Check ChromaDB logs
docker logs chromadb  # if using Docker
```

### Indexing Problems

```bash
# Clear and re-index
php bin/magento productrecommendation:collection:clear
php bin/magento productrecommendation:index:products

# Check indexer status
php bin/magento indexer:status

# Reset indexer
php bin/magento indexer:reset navindbhudiya_product_embedding
php bin/magento indexer:reindex navindbhudiya_product_embedding
```

### No Recommendations Showing

1. **Check module is enabled**:
   ```bash
   php bin/magento module:status NavinDBhudiya_ProductRecommendation
   ```

2. **Clear cache**:
   ```bash
   php bin/magento cache:clean
   ```

3. **Check configuration**:
    - Stores → Configuration → NavinDBhudiya → Product Recommendation
    - Ensure "Enable Module" is set to Yes

4. **Check product indexing**:
   ```bash
   php bin/magento productrecommendation:index:products
   ```

5. **Check logs**:
   ```bash
   tail -f var/log/system.log | grep -i recommendation
   tail -f var/log/exception.log
   ```

### Performance Issues

1. **Enable caching**:
    - Increase Cache TTL in admin config
    - Enable Magento Full Page Cache

2. **Optimize batch size**:
    - Reduce batch size in admin config if memory issues
    - Default: 100, try 50 for large catalogs

3. **Use circuit breaker**:
    - Configured automatically for LLM calls
    - Prevents cascading failures

---

## 🔒 Security

### API Authentication

REST API endpoints require:
- OAuth 1.0a or 2.0 authentication
- Customer token for personalized recommendations
- Admin token for management operations

### Data Privacy

- Customer behavior data is pseudonymized
- Tracking uses secure cookies
- Complies with GDPR requirements
- Data retention configurable via admin

---

## 🧪 Testing

### Unit Tests

```bash
# Run unit tests
php vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/NavinDBhudiya/ProductRecommendation/Test/Unit

# Run specific test
php vendor/bin/phpunit app/code/NavinDBhudiya/ProductRecommendation/Test/Unit/Helper/ConfigTest.php
```

### Integration Testing

```bash
# Test recommendation flow
php bin/magento productrecommendation:similar:get 123

# Test with different parameters
php bin/magento productrecommendation:personalized:get 456 --max-results=20
```

---

## 📈 Performance Tips

1. **Index regularly**: Schedule cron jobs for automated indexing
2. **Use caching**: Enable recommendation caching with appropriate TTL
3. **Optimize embeddings**: Select only relevant product attributes
4. **Monitor ChromaDB**: Ensure adequate resources for vector searches
5. **Batch operations**: Use CLI commands for bulk operations
6. **Circuit breaker**: Enabled by default for external API stability

---

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'Add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

### Coding Standards

- Follow Magento 2 Coding Standards
- Use PHP_CodeSniffer with Magento rules
- Add unit tests for new features
- Update documentation

---

## 📝 Changelog

### Version 2.1.0 (Current)
- Added LLM re-ranking support
- Improved personalization engine
- GraphQL API support
- Hyva theme compatibility
- Enhanced caching layer
- Circuit breaker pattern
- Diversity filtering

### Version 2.0.0
- ChromaDB integration
- Vector similarity search
- Custom embedding providers
- Admin configuration UI

### Version 1.0.0
- Initial release
- Basic recommendation engine
- CLI commands

---

## 📄 License

This module is licensed under the MIT License. See [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Author

**Navin Bhudiya**
- Email: navindbhudiya@gmail.com
- GitHub: [@navindbhudiya](https://github.com/navindbhudiya)
- Magento Certified: 4x
- AWS Certified: 2x

---

## 🙏 Acknowledgments

- Magento 2 Community
- ChromaDB Team
- Anthropic (Claude AI)
- OpenAI

---

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/navindbhudiya/magento2-product-recommendation/issues)
- **Documentation**: [Wiki](https://github.com/navindbhudiya/magento2-product-recommendation/wiki)
- **Email**: navindbhudiya@gmail.com

---

## 🌟 Star History

If you find this module useful, please consider giving it a star on GitHub!

[![Star History Chart](https://api.star-history.com/svg?repos=navindbhudiya/magento2-product-recommendation&type=Date)](https://star-history.com/#navindbhudiya/magento2-product-recommendation&Date)

---

**Made with ❤️ for the Magento Community**
