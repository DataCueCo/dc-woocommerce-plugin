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

        $item = [
            'order_id' => $order->get_id(),
            'user_id' => $order->get_user_id(),
            'cart' => [],
            'timestamp' => date('c', $order->get_date_created()->getTimestamp()),
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

        add_action('woocommerce_thankyou', [$this, 'onOrderCreated']);
        add_action('woocommerce_cancelled_order', [$this, 'onOrderCancelled']);
        add_action('before_delete_post', [$this, 'onOrderDeleted']);
    }

    /**
     * order created callback
     * @param $orderId
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onOrderCreated($orderId)
    {
        $this->log("onOrderCreated");

        $item = static::generateOrderItem($orderId);
        $res = $this->client->orders->create($item);
        $this->log('create order response: ', $res);
    }

    /**
     * order cancelled callback
     * @param $id
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onOrderCancelled($id)
    {
        $this->log('onOrderCancelled');
        $res = $this->client->orders->cancel($id);
        $this->log('cancel order response: ', $res);
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
            $res = $this->client->orders->delete($id);
            $this->log('delete order response: ', $res);
        }
    }
}
