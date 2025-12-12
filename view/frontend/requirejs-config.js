/**
 * NavinDBhudiya ProductRecommendation
 * RequireJS Configuration
 */
var config = {
    map: {
        '*': {
            'personalizedSlider': 'NavinDBhudiya_ProductRecommendation/js/personalized-slider',
            'NavinDBhudiya_ProductRecommendation/js/personalized-slider': 'NavinDBhudiya_ProductRecommendation/js/personalized-slider'
        }
    },
    shim: {
        'NavinDBhudiya_ProductRecommendation/js/personalized-slider': {
            deps: ['jquery', 'underscore']
        }
    }
};
