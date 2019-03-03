<?php

namespace DataCue\WooCommerce\Events;

use DataCue\WooCommerce\Modules\Product;

class BrowserEvents
{
    private $dataCueOptions;

    public static function registerHooks($dataCueOptions)
    {
        return new static($dataCueOptions);
    }

    public function __construct($dataCueOptions)
    {
        $this->dataCueOptions = $dataCueOptions;

        add_action('wp_head', [$this, 'onHead']);
    }

    public function onHead()
    {
        if (is_shop()) {
            $this->onHomePage();
        } else if (is_product()) {
            $this->onProductPage();
        } else if (is_product_category()) {
            $this->onCategoryPage();
        } else if (is_cart()) {
            $this->onCartPage();
        } else if (is_search()) {
            $this->onSearchPage();
        } else if (is_404()) {
            $this->on404Page();
        }

    }

    private function onHomePage()
    {
        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  page_type: 'home'
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    private function onCategoryPage()
    {
        // current category
        $category = $GLOBALS['wp_query']->get_queried_object();
        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  page_type: 'category',
  category_name: '{$category->name}'
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    private function onProductPage()
    {
        $productId = get_the_ID();
        $product = Product::generateProductItem($productId);
        $productUpdateStr = json_encode($product);

        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  page_type: 'product',
  product_id: $productId,
  variant_id: 'no-variants',
  product_update: JSON.parse('$productUpdateStr')
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    private function onCartPage()
    {
        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  page_type: 'cart'
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    private function onSearchPage()
    {
        $term = get_search_query();
        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  page_type: 'search',
  term: '$term'
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    private function on404Page()
    {
        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  page_type: '404'
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    private function getUserId()
    {
        return get_current_user_id() > 0 ? get_current_user_id() : 'null';
    }
}
