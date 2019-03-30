<?php

namespace DataCue\WooCommerce\Modules;

use DataCue\Exceptions\RetryCountReachedException;

/**
 * Class Event
 * @package DataCue\WooCommerce\Modules
 */
class Event extends Base
{
    /**
     * Event constructor.
     * @param $client
     * @param array $options
     */
    public function __construct($client, array $options = [])
    {
        parent::__construct($client, $options);

        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'onCartUpdated']);
        add_action('woocommerce_cart_item_removed', [$this, 'onCartUpdated']);
        add_action('woocommerce_cart_item_restored', [$this, 'onCartUpdated']);
        add_action('woocommerce_before_cart_item_quantity_zero', [$this, 'onCartUpdated']);
        add_action('woocommerce_add_to_cart', [$this, 'onCartUpdated']);
    }

    /**
     * Cart updated hook
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onCartUpdated()
    {
        $currency = get_woocommerce_currency();
        $cart = [];
        $items = WC()->cart->get_cart();
        foreach($items as $key => $values) {
            $item = [
                'product_id' => $values['product_id'],
                'variant_id' => $values['variation_id'] === 0 ? 'no-variants' : $values['variation_id'],
                'quantity' => $values['quantity'],
                'currency' => $currency,
            ];

            if ($values['variation_id'] > 0) {
                $variant = wc_get_product($values['variation_id']);
                $item['unit_price'] = $variant->get_price();
            } else {
                $product = wc_get_product($values['product_id']);
                $item['unit_price'] = $product->get_price();
            }

            $cart[] = $item;
        }

        try {
            $this->log('track cart updated event');
            $res = $this->client->events->track(
                [
                    'user_id' => get_current_user_id(),
                ],
                [
                    'type' => 'cart',
                    'subtype' => 'update',
                    'cart' => $cart,
                    'cart_link' => wc_get_cart_url(),
                ]
            );
            $this->log($res);
        } catch (RetryCountReachedException $e) {
            $this->log($e);
        }
    }

}
