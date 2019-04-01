<?php

namespace DataCue\WooCommerce\Modules;

use DataCue\Exceptions\RetryCountReachedException;
use WC_Order_Item_Product;

/**
 * Class Order
 * @package DataCue\WooCommerce\Modules
 */
class Order extends Base
{
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

        add_action('woocommerce_process_shop_order_meta', [$this, 'onOrderSaved'], 10, 1);
        add_action('woocommerce_thankyou', [$this, 'onOrderSaved'], 10, 1);
        add_action('wc-cancelled_shop_order', [$this, 'onOrderCancelled'], 10, 2);
        add_action('before_delete_post', [$this, 'onOrderDeleted']);
    }

    /**
     * order created callback
     * @param $orderId
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onOrderSaved($id)
    {
        $this->log("onOrderSaved");

        try {
            $order = wc_get_order($id);
            $this->log($order->get_status());
            if ($order->get_status() === 'cancelled') {
                if (!$this->findTask('orders', 'cancel', $id)) {
                    $this->log('can cancel order');
                    $this->addTaskToQueue('orders', 'cancel', $id, ['orderId' => $id]);
                }
            } else {
                if (!$this->findTask('orders', 'create', $id)) {
                    $this->log('can create order');
                    $item = static::generateOrderItem($order);
                    if (!is_null($item)) {
                        $this->addTaskToQueue('orders', 'create', $id, ['item' => $item]);
                    }
                }
            }
        } catch (RetryCountReachedException $e) {
            $this->log($e->errorMessage());
        }
    }

    /**
     * order cancelled callback
     * @param $id
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onOrderCancelled($id, $post)
    {
        $this->log('onOrderCancelled');
        try {
            $this->addTaskToQueue('orders', 'cancel', $id, ['orderId' => $id]);
        } catch (RetryCountReachedException $e) {
            $this->log($e->errorMessage());
        }
    }

    /**
     * order deleted callback
     * @param $id
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onOrderDeleted($id)
    {
        global $post_type;

        if($post_type === 'shop_order') {
            $this->log('onOrderDeleted');
            try {
                $this->addTaskToQueue('orders', 'delete', $id, ['orderId' => $id]);
            } catch (RetryCountReachedException $e) {
                $this->log($e->errorMessage());
            }
        }
    }
}
