<?php

namespace DataCue\WooCommerce\Widgets;

use WP_Widget;

/**
 * Class Products
 * @package DataCue\WooCommerce\Widgets
 */
class Products extends WP_Widget
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
            'classname' => 'datacue_product',
            'description' => 'Display recommended products of DataCue',
        ];
        parent::__construct('datacue_product', 'DataCue Recommended Products', $options);
    }

    /**
     * Echoes the widget content.
     *
     * @param array $args
     * @param array $instance
     */
    public function widget($args, $instance)
    {
        echo '<div class="widget"><div data-dc-products="' . $instance['type'] . '"></div></div>';
    }

    /**
     * Outputs the settings update form.
     *
     * @param array $instance
     * @return string|void
     */
    public function form($instance)
    {
        $img = !empty($instance['type']) ? $instance['type'] : 'recent';

        echo <<<EOT
<p>
    <label for="{$this->get_field_id('type')}">Type :</label>
    <select class="widefat" id="{$this->get_field_id('type')}" name="{$this->get_field_name('type')}">
        <option value="recent"{$this->getSelected($instance['type'], 'recent')}>Recent</option>
        <option value="similar"{$this->getSelected($instance['type'], 'similar')}>Similar</option>
        <option value="related"{$this->getSelected($instance['type'], 'related')}>Related</option>
    </select>
</p>
EOT;
    }

    /**
     * Updates a particular instance of a widget.
     *
     * @param array $newInstance
     * @param array $oldInstance
     * @return array
     */
    public function update($newInstance, $oldInstance)
    {
        $instance = [];

        $instance['type'] = (!empty($newInstance['type'])) ? trim($newInstance['type']) : '';

        return $instance;
    }

    private function getSelected($current, $option)
    {
        return $current === $option ? ' selected' : '';
    }
}
