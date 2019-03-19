<?php

namespace DataCue\WooCommerce\Common;

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
    const INTERVAL = 60;

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
        // get job
        global $wpdb;
        $row = $wpdb->get_row("SELECT `id`,`job` FROM `{$wpdb->prefix}datacue_queue` WHERE `executed_at` IS NULL LIMIT 1");
        if (!is_null($row)) {
            // update executed_at field
            $sql = "UPDATE `{$wpdb->prefix}datacue_queue` SET `executed_at` = NOW() WHERE `id` = {$row->id}";
            $wpdb->get_results($sql);

            $job = json_decode($row->job);
            $this->log($job);

            if ($job->type === 'products') {
                // batch create products
                $data = [];
                foreach ($job->ids as $id) {
                    $data[] = Product::generateProductItem($id, true);
                }
                $res = $this->client->products->batchCreate($data);
                $this->log('batch create products response: ' . $res);
            } elseif ($job->type === 'users') {
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
            } elseif ($job->type === 'orders') {
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
