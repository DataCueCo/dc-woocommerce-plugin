<?php

namespace DataCue\WooCommerce\Events;

use DataCue\WooCommerce\Modules\Product;

/**
 * Class BrowserEvents
 * @package DataCue\WooCommerce\Events
 */
class BrowserEvents
{
    /**
     * @var array includes api_key/api_secret
     */
    private $dataCueOptions;


    private $dataCueConfigOptions = '{}';

    /**
     * Register hooks
     * @param $dataCueOptions
     * @return BrowserEvents
     */
    public static function registerHooks($dataCueOptions, $env)
    {
        return new static($dataCueOptions, $env);
    }

    /**
     * BrowserEvents constructor.
     * @param $dataCueOptions
     */
    public function __construct($dataCueOptions, $env)
    {
        $this->dataCueOptions = $dataCueOptions;
        if ($env === 'development') {
            $this->dataCueConfigOptions = '{_staging: true}';
        }

        add_action('wp_head', [$this, 'onHead']);
    }

    /**
     * The callback of Page Head hook
     */
    public function onHead()
    {
        if (is_checkout()) {
            $this->onCheckoutPage();
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
        } else if (is_shop()) {
            $this->onHomePage();
        } else if (is_front_page()) {
            $this->onHomePage();
        }
    }

    /**
     * For home page of the shop
     */
    private function onHomePage()
    {
        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  options: {$this->dataCueConfigOptions},
  page_type: 'home'
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    /**
     * For category page
     */
    private function onCategoryPage()
    {
        // current category
        $category = $GLOBALS['wp_query']->get_queried_object();
        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  options: {$this->dataCueConfigOptions},
  page_type: 'category',
  category_name: '{$category->name}'
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    /**
     * For product page
     */
    private function onProductPage()
    {
        $productId = get_the_ID();

        // check if there're variants
        $product = wc_get_product($productId);
        $variants = $product->get_children();
        if (count($variants) > 0) {
            $item = Product::generateProductItem($variants[0], false, true);
            $productUpdateStr = json_encode($item);
            $variantId = $variants[0];
        } else {
            $item = Product::generateProductItem($productId);
            $productUpdateStr = json_encode($item);
            $variantId = '\'no-variants\'';
        }

        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  options: {$this->dataCueConfigOptions},
  page_type: 'product',
  product_id: '$productId',
  variant_id: '$variantId',
  product_update: $productUpdateStr
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    /**
     * For shopping cart page
     */
    private function onCartPage()
    {
        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  options: {$this->dataCueConfigOptions},
  page_type: 'cart'
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    /**
     * For search page
     */
    private function onSearchPage()
    {
        $term = get_search_query();
        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  options: {$this->dataCueConfigOptions},
  page_type: 'search',
  term: '$term'
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    /**
     * For 404 page
     */
    private function on404Page()
    {
        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  options: {$this->dataCueConfigOptions},
  page_type: '404'
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
EOT;
    }

    /**
     * For checkout page
     */
    private function onCheckoutPage()
    {
        $currency = get_woocommerce_currency();
        $cart = [];
        $items = WC()->cart->get_cart();
        foreach($items as $key => $values) {
            $item = [
                'product_id' => $values['product_id'],
                'variant_id' => $values['variation_id'] === 0 ? 'no-variants' : $values['variation_id'],
                'quantity' => $values['quantity'],
                'currency' => $currency,
            ];

            if ($values['variation_id'] > 0) {
                $variant = wc_get_product($values['variation_id']);
                $item['unit_price'] = $variant->get_price();
            } else {
                $product = wc_get_product($values['product_id']);
                $item['unit_price'] = $product->get_price();
            }

            $cart[] = $item;
        }
        $cartStr = json_encode($cart);
        $cartLink = wc_get_cart_url();

        echo <<<EOT
<script>
window.datacueConfig = {
  api_key: '{$this->dataCueOptions['api_key']}',
  user_id: {$this->getUserId()},
  options: {$this->dataCueConfigOptions},
  page_type: 'checkout'
};
</script>
<script src="https://cdn.datacue.co/js/datacue.js"></script>
<script src="https://cdn.datacue.co/js/datacue-storefront.js"></script>
<script>
window.datacue.identify({$this->getUserId()});

// track the event
window.datacue.track({
  type: 'checkout',
  subtype: 'started',
  cart: $cartStr,
  cart_link:'$cartLink'
});
</script>
EOT;
    }

    /**
     * Get current user id
     * @return int|string
     */
    private function getUserId()
    {
        return get_current_user_id() > 0 ? get_current_user_id() : 'null';
    }
}
