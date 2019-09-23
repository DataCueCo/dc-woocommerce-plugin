<?php

namespace DataCue\WooCommerce\Common;

use DataCue\WooCommerce\Utils\Queue;
use DataCue\WooCommerce\Modules\User;
use DataCue\WooCommerce\Modules\Product;
use DataCue\WooCommerce\Modules\Order;
use DataCue\WooCommerce\Utils\Log;

class ReSync
{
    /**
     * Interval between two cron job.
     */
    const INTERVAL = 3600;

    /**
     * @var \DataCue\Client
     */
    private $client;

    /**
     * @var array
     */
    private $options;

    /**
     * @var Log
     */
    private $logger = null;

    /**
     * Generate ReSync
     * @param $client
     * @param array $options
     * @return ReSync
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
        $this->options = $options;
        if (array_key_exists('debug', $options) && $options['debug']) {
            $this->logger = new Log();
        }

        add_action('datacue_resync_cron', [$this, 'workerCron']);
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
        $schedules['datacue_resync_cron_interval'] = array(
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
        if (!wp_next_scheduled('datacue_resync_cron')) {
            $this->log('wp_next_scheduled passed for datacue_resync_cron');
            // Schedule health check
            wp_schedule_event(time(), 'datacue_resync_cron_interval', 'datacue_resync_cron');
        }
    }

    /**
     * cron handler
     */
    public function workerCron()
    {
        $this->log('workerCron for datacue_resync_cron');

        try {
            $res = $this->client->client->sync();
            $this->log('get resync info: ' . $res);
            $data = $res->getData();
            if (property_exists($data, 'users')) {
                $this->executeUsers($data->users);
            }
            if (property_exists($data, 'products')) {
                $this->executeProducts($data->products);
            }
            if (property_exists($data, 'orders')) {
                $this->executeOrders($data->orders);
            }
        } catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }

    private function executeUsers($data)
    {
        if (is_null($data)) {
            return;
        }

        if ($data === 'full') {
            Queue::addTaskWithModelId('users', 'delete_all', []);
            $this->getInitializer()->batchCreateUsers('reinit');
        } elseif (is_array($data)) {
            foreach ($data as $userId) {
                Queue::addTask('users', 'delete', $userId, ['userId' => $userId]);
                $user = User::generateUserItem($userId, false);
                if (empty($user)) {
                    continue;
                }
                Queue::addTask(
                    'users',
                    'update',
                    $userId,
                    [
                        'userId' => $userId,
                        'item' => $user,
                    ]
                );
            }
        }
    }

    private function executeProducts($data)
    {
        if (is_null($data)) {
            return;
        }

        if ($data === 'full') {
            Queue::addTaskWithModelId('products', 'delete_all', []);
            $this->getInitializer()->batchCreateProducts('reinit');
        } elseif (is_array($data)) {
            foreach ($data as $productId) {
                Queue::addTask('products', 'delete', $productId, ['productId' => $productId, 'variantId' => null]);
                $product = wc_get_product($productId);
                if (empty($product) || $product->get_status() !== 'publish') {
                    continue;
                }
                if ($product->get_type() === 'variable') {
                    $variants = $product->get_children();
                    foreach ($variants as $variantId) {
                        $item = Product::generateProductItem($variantId, true, true);
                        Queue::addTask(
                            'products',
                            'create',
                            $variantId,
                            ['productId' => Product::getParentProductId($variantId), 'variantId' => $variantId, 'item' => $item]
                        );
                    }
                } else {
                    $item = Product::generateProductItem($product, true, false);
                    Queue::addTask(
                        'products',
                        'create',
                        $variantId,
                        ['productId' => $productId, 'variantId' => 'no-variants', 'item' => $item]
                    );
                }
            }
        }
    }

    private function executeOrders($data)
    {
        if (is_null($data)) {
            return;
        }

        if ($data === 'full') {
            Queue::addTaskWithModelId('orders', 'delete_all', []);
            $this->getInitializer()->batchCreateOrders('reinit');
        } elseif (is_array($data)) {
            foreach ($data as $orderId) {
                Queue::addTask('orders', 'delete', $orderId, ['orderId' => $orderId]);
                $order = wc_get_order($orderId);
                if (empty($order) || empty($order->get_id())) {
                    continue;
                }
                $item = Order::generateOrderItem($order);
                if (!is_null($item)) {
                    if ($order->get_customer_id() === 0) {
                        Queue::addTask('guest_users', 'create', $orderId, ['item' => Order::generateGuestUserItem($order)]);
                    }
                    Queue::addTask('orders', 'create', $orderId, ['item' => $item]);
                }
            }
        }
    }

    /**
     * @return Initializer
     */
    private function getInitializer()
    {
        return new Initializer($this->client, $this->options);
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