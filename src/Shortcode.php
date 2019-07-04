<?php

namespace DataCue\WooCommerce;

class Shortcode
{
    public static function registerShortcodes()
    {
        return new static();
    }

    public function __construct()
    {
        add_shortcode('datacue-banners', [$this, 'banners']);
        add_shortcode('datacue-products', [$this, 'products']);
    }

    public function banners($attrs)
    {
        return <<<EOT
<div class="widget">
    <div
      data-dc-banners
      data-dc-static-img="{$attrs['static-img']}"
      data-dc-static-link="{$attrs['static-link']}"
    ></div>
</div>
EOT;
    }

    public function products($attrs)
    {
        return '<div class="widget"><div data-dc-products="' . $attrs['type'] . '"></div></div>';
    }
}
