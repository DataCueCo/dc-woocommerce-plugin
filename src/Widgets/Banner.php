<?php

namespace DataCue\WooCommerce\Widgets;

use WP_Widget;

/**
 * Class Banner
 * @package DataCue\WooCommerce\Widgets
 */
class Banner extends WP_Widget
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
     * Banner constructor.
     */
    public function __construct()
    {
        $options = [
            'classname' => 'datacue_banner',
            'description' => 'Display a banner of DataCue',
        ];
        parent::__construct('datacue_banner', 'DataCue Banner', $options);
    }

    /**
     * Echoes the widget content.
     *
     * @param array $args
     * @param array $instance
     */
    public function widget($args, $instance)
    {
        echo <<<EOT
<div class="widget">
    <div
      data-dc-banners
      data-dc-static-img="{$instance['img']}"
      data-dc-static-link="{$instance['link']}"
    ></div>
</div>
EOT;
    }

    /**
     * Outputs the settings update form.
     *
     * @param array $instance
     * @return string|void
     */
    public function form($instance)
    {
        $img = !empty($instance['img']) ? $instance['img'] : '';
        $link = !empty($instance['link']) ? $instance['link'] : '';

        echo <<<EOT
<p>
    <label for="{$this->get_field_id('img')}">Image URL :</label>
    <input class="widefat" id="{$this->get_field_id('img')}" name="{$this->get_field_name('img')}" type="text" value="$img">
</p>
<p>
    <label for="{$this->get_field_id('link')}">Link :</label>
    <input class="widefat" id="{$this->get_field_id('link')}" name="{$this->get_field_name('link')}" type="text" value="$link">
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

        $instance['img'] = (!empty($newInstance['img'])) ? trim($newInstance['img']) : '';
        $instance['link'] = (!empty($newInstance['link'])) ? trim($newInstance['link']) : '';

        return $instance;
    }
}
