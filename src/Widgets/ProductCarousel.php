<?php

namespace DataCue\WooCommerce\Widgets;

use WP_Widget;

/**
 * Class ProductCarousel
 * @package DataCue\WooCommerce\Widgets
 */
class ProductCarousel extends WP_Widget
{
    /**
     * Register widget
     */
    public static function registerWidget()
    {
        add_action('widgets_init', function () {
            register_widget(new static());
        });
    }

    /**
     * ProductCarousel constructor.
     */
    public function __construct()
    {
        $options = [
            'classname' => 'datacue_product_carousel',
            'description' => 'Display a product carousel of DataCue',
        ];
        parent::__construct('datacue_product_carousel', 'DataCue Product Carousel', $options);
    }

    /**
     * Echoes the widget content.
     *
     * @param array $args
     * @param array $instance
     */
    public function widget($args, $instance)
    {
        echo '<div class="widget"><div data-dc-product-carousels></div></div>';
    }
}
