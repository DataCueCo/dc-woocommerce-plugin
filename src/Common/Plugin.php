<?php

namespace DataCue\WooCommerce\Common;

use DataCue\WooCommerce\Utils\Log;

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
     * Plugin constructor.
     * @param $file
     * @param $client
     * @param $options
     */
    public function __construct($client, $options)
    {
        $this->client = $client;
        if (array_key_exists('debug', $options) && $options['debug']) {
            $this->logger = new Log();
        }
    }

    /**
     * Sync data to datacue server
     *
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    public function syncData()
    {
        $this->log('onPluginActivated');

        /* if (!get_option('datacue_sync')) {
            $this->batchCreateProducts();
            $this->batchCreateUsers();
            $this->batchCreateOrders();

            add_option('datacue_sync', '1');
        } */

        // Skip checking sync flag for now
        $this->batchCreateProducts();
        $this->batchCreateUsers();
        $this->batchCreateOrders();
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

        $res = $this->client->overview->products();
        $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];

        $postIdsList = array_chunk(array_diff(get_posts($args), $existingIds), static::CHUNK_SIZE);

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        global $wpdb;

        foreach($postIdsList as $postIds) {
            $this->log($postIds);
            $job = json_encode([
                'type' => 'products',
                'ids' => $postIds,
            ]);

            $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (job, executed_at, created_at) values ('$job', NULL, NOW())";
            dbDelta( $sql );
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

        $res = $this->client->overview->users();
        $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];

        $userIdsList = array_chunk(array_diff(get_posts($args), $existingIds), static::CHUNK_SIZE);

        global $wpdb;
        foreach ($userIdsList as $userIds) {
            $job = json_encode([
                'type' => 'users',
                'ids' => $userIds,
            ]);

            $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (job, executed_at, created_at) values ('$job', NULL, NOW())";
            dbDelta( $sql );
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
        $currentIds = array_map(function ($order) {
            return $order->get_id();
        }, wc_get_orders($args));

        $res = $this->client->overview->orders();
        $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];

        $ordersIdList = array_chunk(array_diff($currentIds, $existingIds), static::CHUNK_SIZE);

        global $wpdb;
        foreach($ordersIdList as $orderIds) {
            $job = json_encode([
                'type' => 'orders',
                'ids' => $orderIds,
            ]);

            $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (job, executed_at, created_at) values ('$job', NULL, NOW())";
            dbDelta( $sql );
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
