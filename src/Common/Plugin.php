<?php

namespace DataCue\WooCommerce\Common;

use DataCue\WooCommerce\Utils\Log;
use DataCue\WooCommerce\Modules\Product;
use DataCue\WooCommerce\Modules\Order;
use WP_Query;
use WP_User_Query;

/**
 * Class Plugin
 * @package DataCue\WooCommerce\Common
 */
class Plugin
{
    /**
     * chunk size of each package
     */
    const CHUNK_SIZE = 200;

    /**
     * @var \DataCue\Client
     */
    private $client;

    /**
     * @var Log
     */
    private $logger = null;

    /**
     * Generation
     *
     * @param $file
     * @param $client
     * @param $options
     * @return Plugin
     */
    public static function registerHooks($file, $client, $options)
    {
        return new static($file, $client, $options);
    }

    /**
     * Plugin constructor.
     * @param $file
     * @param $client
     * @param $options
     */
    public function __construct($file, $client, $options)
    {
        $this->client = $client;
        if (array_key_exists('debug', $options) && $options['debug']) {
            $this->logger = new Log();
        }

        register_activation_hook($file, [$this, 'onPluginActivated']);
    }

    /**
     * Activation hook
     */
    public function onPluginActivated()
    {
        $this->log('onPluginActivated');

        if (!get_option('datacue_sync')) {
            $this->batchCreateProducts();
            $this->batchCreateUsers();
            $this->batchCreateOrders();

            add_option('datacue_sync', '1');
        }
    }

    /**
     * Batch create products
     *
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    private function batchCreateProducts()
    {
        $this->log('batchCreateProducts');

        $args = [
            'post_type' => 'product',
            'fields' => 'ids',
            'posts_per_page' => -1,
        ];

        $postIdsList = array_chunk(get_posts($args), static::CHUNK_SIZE);

        foreach($postIdsList as $postIds) {
            $data = [];

            foreach ($postIds as $id) {
                $data[] = Product::generateProductItem($id, true);
            }

            $res = $this->client->products->batchCreate($data);
            $this->log('batch create products response: ' . $res);
        }
    }

    /**
     * Batch create users
     *
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    private function batchCreateUsers()
    {
        $this->log('batchCreateUsers');

        $args = [
            'fields' => 'ids',
        ];

        $userIdsList = array_chunk(get_users($args), static::CHUNK_SIZE);

        global $wpdb;
        foreach ($userIdsList as $userIds) {
            $data = [];

            foreach ($userIds as $id) {
                $user = $wpdb->get_row("SELECT `id` as `user_id`, `user_email` as `email`, DATE_FORMAT(`user_registered`, '%Y-%m-%dT%TZ') AS `timestamp` FROM `wp_users` where `id`=$id");
                $metaInfo = $wpdb->get_results("SELECT `meta_key`, `meta_value` FROM `wp_usermeta` where `user_id`=$id AND `meta_key` IN('first_name', 'last_name')");
                array_map(function ($item) use ($user) {
                    $user->{$item->meta_key} = $item->meta_value;
                }, $metaInfo);
                $data[] = $user;
            }

            $res = $this->client->users->batchCreate($data);
            $this->log('batch create users response: ' . $res);
        }
    }

    /**
     * Batch create orders
     *
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    private function batchCreateOrders()
    {
        $this->log('batchCreateOrders');

        $args = [
            'posts_per_page' => -1,
        ];

        $ordersList = array_chunk(wc_get_orders($args), static::CHUNK_SIZE);

        foreach($ordersList as $orders) {
            $data = [];

            foreach ($orders as $order) {
                if ($order->get_status !== 'cancelled') {
                    $data[] = Order::generateOrderItem($order);
                }
            }

            $res = $this->client->orders->batchCreate($data);
            $this->log('batch create orders response: ' . $res);
        }
    }

    /**
     * Log function
     * @param $message
     */
    private function log($message)
    {
        if (!is_null($this->logger)) {
            $this->logger->info($message);
        }
    }
}
