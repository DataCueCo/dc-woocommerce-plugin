<?php

namespace DataCue\WooCommerce\Common;

use DataCue\Exceptions\RetryCountReachedException;
use DataCue\WooCommerce\Utils\Log;
use DataCue\WooCommerce\Modules\Product;
use DataCue\WooCommerce\Modules\Order;

/**
 * Class Schedule
 * @package DataCue\WooCommerce\Common
 */
class Schedule
{
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
     *
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function workerCron()
    {
        $this->log('workerCron');
        // get job
        global $wpdb;
        $row = $wpdb->get_row("SELECT `id`,`model`, `model_id`,`action`,`job` FROM `{$wpdb->prefix}datacue_queue` WHERE `executed_at` IS NULL LIMIT 1");
        if (!is_null($row)) {
            // update executed_at field
            $sql = "UPDATE `{$wpdb->prefix}datacue_queue` SET `executed_at` = NOW(), status = " . static::STATUS_PENDING . " WHERE `id` = {$row->id}";
            dbDelta($sql);

            $job = json_decode($row->job);
            $this->log($job);

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
                $sql = "UPDATE `{$wpdb->prefix}datacue_queue` SET status = " . static::STATUS_SUCCESS . " WHERE `id` = {$row->id}";
                dbDelta( $sql );
            } catch (RetryCountReachedException $e) {
                $this->log($e->errorMessage());
                $sql = "UPDATE `{$wpdb->prefix}datacue_queue` SET status = " . static::STATUS_FAILURE . " WHERE `id` = {$row->id}";
                dbDelta( $sql );
            }
        }
    }

    /**
     * @param $model
     * @param $job
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    private function doInit($model, $job)
    {
        global $wpdb;
        if ($model === 'products') {
            // batch create products
            $data = [];
            foreach ($job->ids as $id) {
                $data[] = Product::generateProductItem($id, true);
            }
            $res = $this->client->products->batchCreate($data);
            $this->log('batch create products response: ' . $res);
        } elseif ($model === 'users') {
            // batch create users
            $data = [];
            foreach ($job->ids as $id) {
                $user = $wpdb->get_row("SELECT `id` as `user_id`, `user_email` as `email`, DATE_FORMAT(`user_registered`, '%Y-%m-%dT%TZ') AS `timestamp` FROM `wp_users` where `id`=$id");
                $metaInfo = $wpdb->get_results("SELECT `meta_key`, `meta_value` FROM `wp_usermeta` where `user_id`=$id AND `meta_key` IN('first_name', 'last_name')");
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
            foreach ($job->ids as $order) {
                if ($order->get_status !== 'cancelled') {
                    $data[] = Order::generateOrderItem($order);
                }
            }
            $res = $this->client->orders->batchCreate($data);
            $this->log('batch create orders response: ' . $res);
        }
    }

    /**
     * @param $action
     * @param $job
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
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
     * @param $action
     * @param $job
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
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
     * @param $action
     * @param $job
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
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
