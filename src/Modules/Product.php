<?php

namespace DataCue\WooCommerce\Modules;

use DataCue\Exceptions\RetryCountReachedException;

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
        $product = wc_get_product($id);

        // generate product item for DataCue
        $item = [
            'name' => $product->get_name(),
            'price' => $product->get_sale_price() ? (float)$product->get_sale_price() : (float)$product->get_regular_price(),
            'full_price' => (float)$product->get_regular_price(),
            'link' => get_permalink($product->get_id()),
            'available' => $product->get_status() === 'publish',
            'description' => $product->get_description(),
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

        if ($isVariant) {
            $parentProduct = wc_get_product($product->get_parent_id());
            $categoryIds = $parentProduct->get_category_ids();
            if (count($categoryIds) > 0) {
                for ($i = 0; $i < count($categoryIds); $i++) {
                    $category = get_term($categoryIds[$i], 'product_cat');
                    $item['categories'][] = $category->name;
                    if ($i === 0) {
                        $item['main_category'] = $category->name;
                    }
                }
            }
        } else {
            $categoryIds = $product->get_category_ids();
            if (count($categoryIds) > 0) {
                for ($i = 0; $i < count($categoryIds); $i++) {
                    $category = get_term($categoryIds[$i], 'product_cat');
                    $item['categories'][] = $category->name;
                    if ($i === 0) {
                        $item['main_category'] = $category->name;
                    }
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

    public static function getParentProductId($id)
    {
        $product = wc_get_product($id);
        return $product->get_parent_id();
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
        add_action('woocommerce_new_product_variation', [$this, 'onVariantCreated']);
        add_action('woocommerce_update_product_variation', [$this, 'onVariantUpdated']);
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
            try {
                $this->addTaskToQueue('products', 'create', $id, ['item' => $item]);
            } catch (RetryCountReachedException $e) {
                $this->log($e->errorMessage());
            }
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

        try {
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
        } catch (RetryCountReachedException $e) {
            $this->log($e->errorMessage());
        }
    }

    /**
     * Variant created callback
     * @param $id
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onVariantCreated($id)
    {
        $this->log('onVariantCreated');
        $this->log("variant_id=$id");

        $item = static::generateProductItem($id, true, true);
        $this->log($item);
        try {
            $this->addTaskToQueue('products', 'create', $id, ['item' => $item]);
        } catch (RetryCountReachedException $e) {
            $this->log($e->errorMessage());
        }
    }

    /**
     * Variant updated callback
     * @param $id
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onVariantUpdated($id)
    {
        $this->log('onVariantUpdated');
        $this->log("variant_id=$id");

        $item = static::generateProductItem($id, false, true);
        $this->log($item);
        try {
            $res = $this->client->products->update(static::getParentProductId($id), $id, $item);
            $this->log('update product response: ' . $res);

            if ($task = $this->findAliveTask('products', 'create', $id)) {
                $item = static::generateProductItem($id, true, true);
                $this->updateTask($task->id, ['item' => $item]);
            } elseif ($task = $this->findAliveTask('products', 'update', $id)) {
                $item = static::generateProductItem($id);
                $this->updateTask($task->id, ['productId' => static::getParentProductId($id), 'variantId' => $id, 'item' => $item]);
            } else {
                $item = static::generateProductItem($id);
                $this->addTaskToQueue('products', 'update', $id, ['productId' => static::getParentProductId($id), 'variantId' => $id, 'item' => $item]);
            }
        } catch (RetryCountReachedException $e) {
            $this->log($e->errorMessage());
        }
    }

    /**
     * Product/Variant deleted callback
     * @param $id
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onProductDeleted($id)
    {
        $product = wc_get_product($id);
        if ($product) {
            $this->log('onProductDeleted');
            if ($product->get_parent_id() === 0) {
                $this->log('is product');
                try {
                    $this->addTaskToQueue('products', 'delete', $id, ['productId' => $id, 'variantId' => 'no-variants']);
                } catch (RetryCountReachedException $e) {
                    $this->log($e->errorMessage());
                }
            } else {
                $this->log('is variant, and parent id = ' . $product->get_parent_id());
                try {
                    $this->addTaskToQueue('products', 'delete', $id, ['productId' => $product->get_parent_id(), 'variantId' => $id]);
                } catch (RetryCountReachedException $e) {
                    $this->log($e->errorMessage());
                }
            }
        }
    }
}
