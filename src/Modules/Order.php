<?php

namespace DataCue\WooCommerce\Modules;

use WC_Order_Item_Product;

/**
 * Class Order
 * @package DataCue\WooCommerce\Modules
 */
class Order extends Base
{
    /**
     * @var null
     */
    private $orderId = null;

    /**
     * Generate order item for DataCue
     * @param $order int|\WC_Order Order ID or Order object
     * @return array
     */
    public static function generateOrderItem($order)
    {
        $currency = get_woocommerce_currency();

        if (is_int($order) || is_string($order)) {
            $order = wc_get_order($order);
        }

        if (count($order->get_items()) === 0) {
            return null;
        }

        $item = [
            'order_id' => $order->get_id(),
            'user_id' => $order->get_customer_id(),
            'cart' => [],
            'timestamp' => date('c', is_null($order->get_date_created()) ? time() : $order->get_date_created()->getTimestamp()),
        ];

        foreach ($order->get_items() as $one) {
            $product = new WC_Order_Item_Product($one->get_id());
            $item['cart'][] = [
                'product_id' => $product->get_product_id(),
                'variant_id' => 'no-variants',
                'quantity' => $one->get_quantity(),
                'unit_price' => $product->get_total() / $one->get_quantity(),
                'currency' => $currency,
            ];
        }

        return $item;
    }

    /**
     * Order constructor.
     * @param $client
     * @param array $options
     */
    public function __construct($client, array $options = [])
    {
        parent::__construct($client, $options);

        add_action('transition_post_status', [$this, 'onOrderStatusChanged'], 10, 3);
        add_action('shutdown', [$this, 'onShutdown']);
        add_action('woocommerce_process_shop_order_meta', [$this, 'onOrderSaved'], 10, 1);
        add_action('woocommerce_thankyou', [$this, 'onOrderSaved'], 10, 1);
    }

    /**
     * Order status changed hook
     * @param $newStatus
     * @param $oldStatus
     * @param $post
     */
    public function onOrderStatusChanged($newStatus, $oldStatus, $post)
    {
        if ($post->post_type !== 'shop_order') {
            return;
        }

        $this->log('onOrderStatusChanged new_status=' . $newStatus . ' && old_status=' . $oldStatus);
        $id = $post->ID;

        if ($oldStatus === 'trash') {
            $item = static::generateOrderItem($id);
            if (!is_null($item)) {
                $this->log('Create order');
                $this->addTaskToQueue('orders', 'create', $id, ['item' => $item]);
            }
            return;
        }

        if ($newStatus === 'trash') {
            $this->log('Delete order');
            $this->addTaskToQueue('orders', 'delete', $id, ['orderId' => $id]);
            return;
        }

        if ($newStatus === 'wc-cancelled' && $oldStatus !== 'wc-cancelled') {
            $this->log('Cancel order');
            $this->addTaskToQueue('orders', 'cancel', $id, ['orderId' => $id]);
            return;
        }
    }

    /**
     * Shutdown hook
     */
    public function onShutdown()
    {
        if (!is_null($this->orderId)) {
            $this->log('onOrderSaved');
            $id = $this->orderId;
            $order = wc_get_order($id);
            if ($order->get_status() !== 'cancelled') {
                if (!$this->findTask('orders', 'create', $id)) {
                    $this->log('Create order id=' . $id);
                    $item = static::generateOrderItem($order);
                    if (!is_null($item)) {
                        $this->addTaskToQueue('orders', 'create', $id, ['item' => $item]);
                    }
                }
            } else {
                if (!$this->findTask('orders', 'cancel', $id)) {
                    $this->log('Cancel order');
                    $this->addTaskToQueue('orders', 'cancel', $id, ['orderId' => $id]);
                }
            }
        }
    }

    /**
     * order created callback
     * @param $id
     */
    public function onOrderSaved($id)
    {
        $this->orderId = $id;
    }
}
