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
     * @param $isVariant bool
     * @return array
     */
    public static function generateProductItem($id, $withId = false, $isVariant = false)
    {
        if (is_string($id) || is_int($id)) {
            $product = wc_get_product($id);
        } else {
            $product = $id;
        }

        if ($isVariant) {
            $parentProduct = wc_get_product($product->get_parent_id());
        } else {
            $parentProduct = null;
        }

        // generate product item for DataCue
        $item = [
            'name' => $isVariant ? $parentProduct->get_name() : $product->get_name(),
            'price' => $product->get_sale_price() ? (float)$product->get_sale_price() : (float)$product->get_regular_price(),
            'full_price' => (float)$product->get_regular_price(),
            'link' => get_permalink($product->get_id()),
            'available' => $product->get_status() === 'publish',
            'description' => $isVariant ? $parentProduct->get_description() : $product->get_description(),
            'brand' => $isVariant ? static::getFirstBrandNameByProductId($product->get_parent_id()) : static::getFirstBrandNameByProductId($product->get_id()),
        ];

        // get photo url
        $imageId = $isVariant ? $parentProduct->get_image_id() : $product->get_image_id();
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

        if ($isVariant) {
            $categoryIds = $parentProduct->get_category_ids();
        } else {
            $categoryIds = $product->get_category_ids();
        }
        if (count($categoryIds) > 0) {
            for ($i = 0; $i < count($categoryIds); $i++) {
                $category = get_term($categoryIds[$i], 'product_cat');
                $item['categories'][] = $category->name;
                if ($i === 0) {
                    $item['main_category'] = $category->name;
                }
            }
        }

        if ($withId) {
            if ($isVariant) {
                $item['product_id'] = $product->get_parent_id();
                $item['variant_id'] = $product->get_id();
            } else {
                $item['product_id'] = $product->get_id();
                $item['variant_id'] = 'no-variants';
            }
        }

        return $item;
    }

    /**
     * Get parent id of product
     * @param $id
     * @return int
     */
    public static function getParentProductId($id)
    {
        $product = wc_get_product($id);
        return $product->get_parent_id();
    }

    /**
     * @param $id
     * @return null|string
     */
    public static function getFirstBrandNameByProductId($id)
    {
        global $wpdb;
        $sql = "SELECT c.`name` FROM `wp_term_relationships` a 
                LEFT JOIN `wp_term_taxonomy` b ON a.`term_taxonomy_id` = b.`term_taxonomy_id` 
                LEFT JOIN `wp_terms` c ON c.`term_id` = b.`term_id` 
                WHERE a.`object_id` = %d AND b.`taxonomy` = %s";
        $item = $wpdb->get_row(
            $wpdb->prepare($sql, intval($id), 'product_brand')
        );

        if (is_null($item)) {
            return null;
        }

        return $item->name;
    }

    /**
     * Product constructor.
     * @param $client
     * @param array $options
     */
    public function __construct($client, array $options = [])
    {
        parent::__construct($client, $options);

        add_action('transition_post_status', [$this, 'onProductStatusChanged'], 10, 3);
        add_action('woocommerce_update_product', [$this, 'onProductUpdated']);
        add_action('woocommerce_update_product_variation', [$this, 'onVariantUpdated']);
        add_action('before_delete_post', [$this, 'onVariantDeleted']);
    }

    /**
     * Product status changed callback
     * @param $newStatus
     * @param $oldStatus
     * @param \WP_Post $post
     */
    public function onProductStatusChanged($newStatus, $oldStatus, $post) {
        if ($post->post_type !== 'product' && $post->post_type !== 'product_variation') {
            return;
        }

        $this->log('onProductStatusChanged new_status=' . $newStatus . ' && old_status=' . $oldStatus);

        $id = $post->ID;

        if ($newStatus === 'publish' && $oldStatus !== 'publish') {
            if ($post->post_type === 'product') {
                $this->log('Create product');
                $this->log("product_id=$id");
                $item = static::generateProductItem($id, true);
                $this->addTaskToQueue('products', 'create', $id, ['item' => $item]);
            } else {
                $this->log('Create variant');
                $this->log("variant_id=$id");
                $item = static::generateProductItem($id, true, true);
                $this->addTaskToQueue('products', 'create', $id, ['item' => $item]);
            }
            return;
        }

        if ($oldStatus === 'publish' && $newStatus !== 'publish') {
            if ($post->post_type === 'product') {
                $this->log('Delete product');
                $this->log("product_id=$id");
                $this->addTaskToQueue('products', 'delete', $id, ['productId' => $id, 'variantId' => 'no-variants']);
            } else {
                $this->log('Delete variant');
                $this->log("variant_id=$id");
                $product = wc_get_product($id);
                $this->addTaskToQueue('products', 'delete', $id, ['productId' => $product->get_parent_id(), 'variantId' => $id]);
            }
            return;
        }
    }

    /**
     * Product updated callback
     * @param $id
     */
    public function onProductUpdated($id)
    {
        $product = wc_get_product($id);
        if ($product->get_status() !== 'publish') {
            return;
        }

        $this->log('Update product');
        $this->log("product_id=$id");

        if ($task = $this->findAliveTask('products', 'create', $id)) {
            $item = static::generateProductItem($id, true);
            $this->updateTask($task->id, ['item' => $item]);
        } elseif ($task = $this->findAliveTask('products', 'update', $id)) {
            $item = static::generateProductItem($id);
            $this->updateTask($task->id, ['productId' => $id, 'variantId' => 'no-variants', 'item' => $item]);
        } else {
            $item = static::generateProductItem($id);
            $this->addTaskToQueue('products', 'update', $id, ['productId' => $id, 'variantId' => 'no-variants', 'item' => $item]);
        }

        // update variants belonging the current product
        $variants = $product->get_children();
        foreach ($variants as $variantId) {
            $this->onVariantUpdated($variantId);
        }
    }

    /**
     * Variant updated callback
     * @param $id
     */
    public function onVariantUpdated($id)
    {
        $product = wc_get_product($id);
        if ($product->get_status() !== 'publish') {
            return;
        }

        $this->log('Update variant');
        $this->log("variant_id=$id");

        if ($task = $this->findAliveTask('products', 'create', $id)) {
            $item = static::generateProductItem($id, true, true);
            $this->updateTask($task->id, ['item' => $item]);
        } elseif ($task = $this->findAliveTask('products', 'update', $id)) {
            $item = static::generateProductItem($id, false, true);
            $this->updateTask($task->id, ['productId' => static::getParentProductId($id), 'variantId' => $id, 'item' => $item]);
        } else {
            $item = static::generateProductItem($id, false, true);
            $this->addTaskToQueue('products', 'update', $id, ['productId' => static::getParentProductId($id), 'variantId' => $id, 'item' => $item]);
        }
    }

    /**
     * Variant deleted callback
     * @param $id
     */
    public function onVariantDeleted($id)
    {
        $post = get_post($id);
        if ($post->post_type === 'product_variation') {
            $this->log('Delete variant');
            $this->log("variant_id=$id");
            $product = wc_get_product($id);
            $this->addTaskToQueue('products', 'delete', $id, ['productId' => $product->get_parent_id(), 'variantId' => $id]);
        }
    }
}
