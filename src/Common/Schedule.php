<?php

namespace DataCue\WooCommerce\Common;

use DataCue\WooCommerce\Utils\Log;
use DataCue\WooCommerce\Modules\Product;
use DataCue\WooCommerce\Modules\Order;
use Exception;

/**
 * Class Schedule
 * @package DataCue\WooCommerce\Common
 */
class Schedule
{
    /**
     * chunk size of each package
     */
    const CHUNK_SIZE = 200;

    /**
     * Interval between two cron job.
     */
    const INTERVAL = 20;

    /**
     * Task status after initial
     */
    const STATUS_NONE = 0;

    /**
     * Task status for pending
     */
    const STATUS_PENDING = 1;

    /**
     * Task status for success
     */
    const STATUS_SUCCESS = 2;

    /**
     * Task status for failure
     */
    const STATUS_FAILURE = 3;

    /**
     * @var \DataCue\Client
     */
    private $client;

    /**
     * @var Log
     */
    private $logger = null;

    /**
     * Generate Schedule
     * @param $client
     * @param array $options
     * @return Schedule
     */
    public static function registerHooks($client, $options = [])
    {
        return new static($client, $options);
    }

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

        add_action('datacue_worker_cron', [$this, 'workerCron']);
        add_filter('cron_schedules', [$this, 'scheduleCron']);

        add_action('wp_ajax_load_sync_status_action', [$this, 'loadSyncStatus']);

        $this->maybeScheduleCron();
    }

    /**
     * Cron schedules
     *
     * @param $schedules
     *
     * @return mixed
     */
    public function scheduleCron($schedules)
    {
        $schedules['datacue_worker_cron_interval'] = array(
            'interval' => static::INTERVAL,
            'display' => sprintf(__('Every %d Seconds'), static::INTERVAL),
        );

        return $schedules;
    }

    /**
     * Maybe schedule cron
     *
     * Schedule health check cron if not disabled. Remove schedule if
     * disabled and already scheduled.
     */
    public function maybeScheduleCron()
    {
        if (!wp_next_scheduled('datacue_worker_cron')) {
            $this->log('wp_next_scheduled passed');
            // Schedule health check
            wp_schedule_event(time(), 'datacue_worker_cron_interval', 'datacue_worker_cron');
        }
    }

    /**
     * cron handler
     */
    public function workerCron()
    {
        $this->log('workerCron');
        // get job
        global $wpdb;
        $row = $wpdb->get_row("SELECT `id`,`model`, `model_id`,`action`,`job` FROM `{$wpdb->prefix}datacue_queue` WHERE `executed_at` IS NULL LIMIT 1");
        if (!is_null($row)) {
            // update executed_at field
            $sql = "UPDATE `{$wpdb->prefix}datacue_queue` SET `executed_at` = NOW(), status = " . static::STATUS_PENDING . " WHERE `id` = %d";
            $wpdb->query(
                $wpdb->prepare($sql, $row->id)
            );

            $job = json_decode($row->job);

            try {
                if ($row->action === 'init') {
                    $this->doInit($row->model, $job);
                } else {
                    switch ($row->model) {
                        case 'products':
                            $this->doProductsJob($row->action, $job);
                            break;
                        case 'users':
                            $this->doUsersJob($row->action, $job);
                            break;
                        case 'orders':
                            $this->doOrdersJob($row->action, $job);
                            break;
                        default:
                            break;
                    }
                }
                $sql = "UPDATE `{$wpdb->prefix}datacue_queue` SET status = " . static::STATUS_SUCCESS . " WHERE `id` = %d";
                $wpdb->query(
                    $wpdb->prepare($sql, $row->id)
                );
            } catch (Exception $e) {
                $this->log($e->getMessage());
                $sql = "UPDATE `{$wpdb->prefix}datacue_queue` SET status = " . static::STATUS_FAILURE . " WHERE `id` = %d";
                $wpdb->query(
                    $wpdb->prepare($sql, $row->id)
                );
            }
        }
    }

    /**
     * Initialize data
     *
     * @param $model
     * @param $job
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    private function doInit($model, $job)
    {
        global $wpdb;
        if ($model === 'products') {
            // batch create products
            $data = [];
            foreach ($job->ids as $id) {
                $product = wc_get_product($id);
                $data[] = Product::generateProductItem($product, true);
            }
            $res = $this->client->products->batchCreate($data);
            $this->log('batch create products response: ' . $res);

            // get variants belonging to the products
            $this->addVariantsSyncTask($job->ids);
        } elseif ($model === 'variants') {
            // batch create variants
            $data = [];
            foreach ($job->ids as $id) {
                $product = wc_get_product($id);
                if ($product->get_parent_id() > 0) {
                    $data[] = Product::generateProductItem($product, true, true);
                }
            }
            $res = $this->client->products->batchCreate($data);
            $this->log('batch create products response: ' . $res);
        } elseif ($model === 'users') {
            // batch create users
            $data = [];
            foreach ($job->ids as $id) {
                $sql = "SELECT `id` as `user_id`, `user_email` as `email`, DATE_FORMAT(`user_registered`, '%%Y-%%m-%%dT%%TZ') AS `timestamp` FROM `wp_users` where `id`=%d";
                $user = $wpdb->get_row(
                    $wpdb->prepare($sql, $id)
                );
                $sql = "SELECT `meta_key`, `meta_value` FROM `wp_usermeta` where `user_id`=%d AND `meta_key` IN('first_name', 'last_name')";
                $metaInfo = $wpdb->get_results(
                    $wpdb->prepare($sql, $id)
                );
                array_map(function ($item) use ($user) {
                    $user->{$item->meta_key} = $item->meta_value;
                }, $metaInfo);
                $data[] = $user;
            }
            $res = $this->client->users->batchCreate($data);
            $this->log('batch create users response: ' . $res);
        } elseif ($model === 'orders') {
            // batch create orders
            $data = [];
            foreach ($job->ids as $id) {
                $order = wc_get_order($id);
                if ($order->get_status() !== 'cancelled') {
                    if ($item = Order::generateOrderItem($order)) {
                        $data[] = $item;
                    }
                }
            }
            $res = $this->client->orders->batchCreate($data);
            $this->log('batch create orders response: ' . $res);
        }
    }

    /**
     * Do products job
     *
     * @param $action
     * @param $job
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    private function doProductsJob($action, $job)
    {
        switch ($action) {
            case 'create':
                $res = $this->client->products->create($job->item);
                $this->log('create variant response: ' . $res);
                break;
            case 'update':
                $res = $this->client->products->update($job->productId, $job->variantId, $job->item);
                $this->log('update product response: ' . $res);
                break;
            case 'delete':
                if ($job->variantId) {
                    $res = $this->client->products->delete($job->productId, $job->variantId);
                    $this->log('delete variant response: ' . $res);
                } else {
                    $res = $this->client->products->delete($job->productId);
                    $this->log('delete product response: ' . $res);
                }
                break;
            default:
                break;
        }
    }

    /**
     * Do users job
     *
     * @param $action
     * @param $job
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    private function doUsersJob($action, $job)
    {
        switch ($action) {
            case 'create':
                $res = $this->client->users->create($job->item);
                $this->log('create user response: ' . $res);
                break;
            case 'update':
                $res = $this->client->users->update($job->userId, $job->item);
                $this->log('update user response: ' . $res);
                break;
            case 'delete':
                $res = $this->client->users->delete($job->userId);
                $this->log('delete user response: ' . $res);
                break;
            default:
                break;
        }
    }

    /**
     * Do orders job
     *
     * @param $action
     * @param $job
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    private function doOrdersJob($action, $job)
    {
        switch ($action) {
            case 'create':
                $res = $this->client->orders->create($job->item);
                $this->log('create order response: ', $res);
                break;
            case 'cancel':
                $res = $this->client->orders->cancel($job->orderId);
                $this->log('cancel order response: ', $res);
                break;
            case 'delete':
                $res = $this->client->orders->delete($job->orderId);
                $this->log('delete order response: ', $res);
                break;
            default:
                break;
        }
    }


    private function addVariantsSyncTask($productIds)
    {
        $this->log('addVariantsSyncTask');

        global $wpdb;
        $productIdsStr = $wpdb->_escape(join(',', $productIds));
        $variants = $wpdb->get_results("SELECT `id` FROM `{$wpdb->prefix}posts` WHERE `post_type` = 'product_variation' AND `post_status` = 'publish' AND `post_parent` IN ($productIdsStr)");
        $variantIds = array_map(function ($item) {
            return $item->id;
        }, $variants);

        $postIdsList = array_chunk($variantIds, static::CHUNK_SIZE);

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        foreach($postIdsList as $postIds) {
            $this->log($postIds);
            $job = json_encode([
                'ids' => $postIds,
            ]);

            $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (model, `action`, job, executed_at, created_at) values ('variants', 'init', %s, NULL, NOW())";
            $wpdb->query(
                $wpdb->prepare($sql, $job)
            );
        }
    }

    public function loadSyncStatus()
    {
        $this->workerCron();

        global $wpdb;
        $res = [
            'products' => [
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
            ],
            'variants' => [
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
            ],
            'users' => [
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
            ],
            'orders' => [
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
            ],
        ];
        $rows = $wpdb->get_results("SELECT `id`,`model`, `job`, `status` FROM `{$wpdb->prefix}datacue_queue` WHERE `action` = 'init'");
        foreach($rows as $item) {
            $count = count(json_decode($item->job)->ids);
            $res[$item->model]['total'] += $count;
            if (intval($item->status) === static::STATUS_SUCCESS) {
                $res[$item->model]['completed'] += $count;
            } elseif (intval($item->status) === static::STATUS_FAILURE) {
                $res[$item->model]['failed'] += $count;
            }
        }

        wp_send_json($res);
        wp_die();
    }

    /**
     * Log function
     *
     * @param $message
     */
    private function log($message)
    {
        if (!is_null($this->logger)) {
            $this->logger->info($message);
        }
    }
}
