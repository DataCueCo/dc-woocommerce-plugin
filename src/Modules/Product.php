<?php

namespace DataCue\WooCommerce\Modules;

/**
 * Class Product
 * @package DataCue\WooCommerce\Modules
 */
class Product extends Base
{
    /**
     * Generate product item for DataCue
     * @param $id int Product ID
     * @param $withId bool
     * @return array
     */
    public static function generateProductItem($id, $withId = false)
    {
        $product = wc_get_product($id);

        // generate product item for DataCue
        $item = [
            'name' => $product->get_name(),
            'price' => $product->get_sale_price() ? (float)$product->get_sale_price() : (float)$product->get_regular_price(),
            'full_price' => (float)$product->get_regular_price(),
            'link' => get_permalink($product->get_id()),
            'available' => $product->get_status() === 'publish',
        ];

        // get photo url
        $imageId = $product->get_image_id();
        if ($imageId) {
            $item['photo_url'] = wp_get_attachment_image_url($imageId);
        } else {
            $item['photo_url'] = wc_placeholder_img_src();
        }

        // get stock
        $stock = $product->get_stock_quantity();
        if (!is_null($stock)) {
            $item['stock'] = $stock;
        }

        // get categories
        $item['categories'] = [];
        $item['main_category'] = '';
        $categorieIds = $product->get_category_ids();
        if (count($categorieIds) > 0) {
            $parentCategoryIds = get_ancestors($categorieIds[0], 'product_cat');
            if (count($parentCategoryIds) > 0) {
                $category = get_term($parentCategoryIds[0], 'product_cat');
                $item['categories'][] = $category->name;
            }
            $category = get_term($categorieIds[0], 'product_cat');
            $item['categories'][] = $category->name;
            $item['main_category'] = $category->name;
        }

        if ($withId) {
            $item['product_id'] = $product->get_id();
            $item['variant_id'] = 'no-variants';
        }

        return $item;
    }

    /**
     * Product constructor.
     * @param $client
     * @param array $options
     */
    public function __construct($client, array $options = [])
    {
        parent::__construct($client, $options);

        add_action('save_post_product', [$this, 'onProductSaved'], 10, 3);
        add_action('woocommerce_update_product', [$this, 'onProductUpdated']);
        add_action('delete_post', [$this, 'onProductDeleted']);
    }

    /**
     * Product created or updated callback
     * @param $id
     * @param \WP_Post $post
     * @param $update
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onProductSaved($id, $post, $update)
    {
        if (!$update) {
            $this->log('onProductSaved create');
            $this->log("product_id=$id");

            $item = static::generateProductItem($id, true);
            $this->log($item);
            $res = $this->client->products->create($item);
            $this->log('create product response: ' . $res);
        }
    }

    /**
     * Product updated callback
     * @param $id
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onProductUpdated($id)
    {
        $this->log('onProductUpdated');
        $this->log("product_id=$id");

        $item = static::generateProductItem($id);
        $this->log($item);
        $res = $this->client->products->update($id, 'no-variants', $item);
        $this->log('update product response: ' . $res);
    }

    /**
     * Product deleted callback
     * @param $id
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onProductDeleted($id)
    {
        if (wc_get_product($id)) {
            $this->log('onProductDeleted');
            $res = $this->client->products->delete($id, 'no-variants');
            $this->log('delete product response: ' . $res);
        }
    }
}
