<?php

namespace DataCue\WooCommerce\Common;

use DataCue\WooCommerce\Utils\Log;

/**
 * Class Initializer
 * @package DataCue\WooCommerce\Common
 */
class Initializer
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
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    public function syncData()
    {
        $this->log('syncData');

        // Check api_key&api_secret
        $this->client->overview->all();

        // Check if it has been initialized
        global $wpdb;
        $row = $wpdb->get_row("SELECT * FROM `{$wpdb->prefix}datacue_queue` WHERE `action` = 'init' LIMIT 1");
        if ($row) {
            return;
        }

        $this->batchCreateProducts();
        $this->batchCreateCategories();
        // sync variants once the tasks about product are fired
        // $this->batchCreateVariants();
        $this->batchCreateUsers();
        $this->batchCreateOrders();
    }

    /**
     * Batch create categories
     *
     * @param string $type
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    public function batchCreateCategories($type = 'init')
    {
        $this->log('batchCreateCategories');

        global $wpdb;
        $terms = $wpdb->get_results("SELECT t.term_id FROM `{$wpdb->prefix}terms` t LEFT JOIN `{$wpdb->prefix}term_taxonomy` tt ON t.term_id = tt.term_id WHERE tt.taxonomy = 'product_cat'");
        $categoryIds = array_map(function ($item) {
            return $item->term_id;
        }, $terms);

        if ($type === 'init') {
            $res = $this->client->overview->categories();
            $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];
            $categoryIdsList = array_chunk(array_diff($categoryIds, $existingIds), static::CHUNK_SIZE);
        } else {
            $categoryIdsList = array_chunk($categoryIds, static::CHUNK_SIZE);
        }

        foreach($categoryIdsList as $categoryIds) {
            $this->log($categoryIds);
            $job = json_encode([
                'ids' => $categoryIds,
            ]);

            $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (model, `action`, job, executed_at, created_at) values ('categories', '$type', %s, NULL, NOW())";
            $wpdb->query(
                $wpdb->prepare($sql, $job)
            );
        }
    }

    /**
     * Batch create products
     *
     * @param $type
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    public function batchCreateProducts($type = 'init')
    {
        $this->log('batchCreateProducts');

        global $wpdb;
        $products = $wpdb->get_results("SELECT `id` FROM `{$wpdb->prefix}posts` WHERE `post_type` = 'product' AND `post_status` = 'publish'");
        $productIds = array_map(function ($item) {
            return $item->id;
        }, $products);

        if ($type === 'init') {
            $res = $this->client->overview->products();
            $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];
            $postIdsList = array_chunk(array_diff($productIds, $existingIds), static::CHUNK_SIZE);
        } else {
            $postIdsList = array_chunk($productIds, static::CHUNK_SIZE);
        }

        foreach($postIdsList as $postIds) {
            $this->log($postIds);
            $job = json_encode([
                'ids' => $postIds,
            ]);

            $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (model, `action`, job, executed_at, created_at) values ('products', '$type', %s, NULL, NOW())";
            $wpdb->query(
                $wpdb->prepare($sql, $job)
            );
        }
    }

    /**
     * Batch create variants
     */
    public function batchCreateVariants($type = 'init')
    {
        $this->log('batchCreateVariants');

        global $wpdb;
        $variants = $wpdb->get_results("SELECT `id` FROM `{$wpdb->prefix}posts` WHERE `post_type` = 'product_variation' AND `post_status` = 'publish'");
        $variantIds = array_map(function ($item) {
            return $item->id;
        }, $variants);

        $postIdsList = array_chunk($variantIds, static::CHUNK_SIZE);

        foreach($postIdsList as $postIds) {
            $this->log($postIds);
            $job = json_encode([
                'ids' => $postIds,
            ]);

            $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (model, `action`, job, executed_at, created_at) values ('variants', '$type', %s, NULL, NOW())";
            $wpdb->query(
                $wpdb->prepare($sql, $job)
            );
        }
    }

    /**
     * Batch create users
     *
     * @param $type
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    public function batchCreateUsers($type = 'init')
    {
        $this->log('batchCreateUsers');

        global $wpdb;
        $users = $wpdb->get_results("SELECT `id` FROM `{$wpdb->prefix}users` WHERE `user_status` = 0");
        $userIds = array_map(function ($item) {
            return $item->id;
        }, $users);

        if ($type === 'init') {
            $res = $this->client->overview->users();
            $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];
            $userIdsList = array_chunk(array_diff($userIds, $existingIds), static::CHUNK_SIZE);
        } else {
            $userIdsList = array_chunk($userIds, static::CHUNK_SIZE);
        }

        foreach ($userIdsList as $userIds) {
            $job = json_encode([
                'ids' => $userIds,
            ]);

            $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (model, `action`, job, executed_at, created_at) values ('users', '$type', %s, NULL, NOW())";
            $wpdb->query(
                $wpdb->prepare($sql, $job)
            );
        }
    }

    /**
     * Batch create orders
     *
     * @param $type
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    public function batchCreateOrders($type = 'init')
    {
        $this->log('batchCreateOrders');

        global $wpdb;
        $orders = $wpdb->get_results("SELECT `id` FROM `{$wpdb->prefix}posts` WHERE `post_type` = 'shop_order'");
        $orderIds = array_map(function ($item) {
            return $item->id;
        }, $orders);

        if ($type === 'init') {
            $res = $this->client->overview->orders();
            $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];
            $ordersIdList = array_chunk(array_diff($orderIds, $existingIds), static::CHUNK_SIZE);
        } else {
            $ordersIdList = array_chunk($orderIds, static::CHUNK_SIZE);
        }

        foreach($ordersIdList as $orderIds) {
            $job = json_encode([
                'ids' => $orderIds,
            ]);

            $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (model, `action`, job, executed_at, created_at) values ('orders', '$type', %s, NULL, NOW())";
            $wpdb->query(
                $wpdb->prepare($sql, $job)
            );
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
