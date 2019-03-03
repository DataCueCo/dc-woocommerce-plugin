<?php
/**
 * Plugin Name: WooCommerce Datacue Plugin
 * Plugin URI: https://datacue.io/
 * Description: Datacue plugin for WooCommerce
 * Version: 1.0.0
 * Author: DataCue
 * Author URI: https://datacue.io/
 * Text Domain: woocommerce datacue plugin
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/vendor/autoload.php';

use DataCue\WooCommerce\Modules\User;
use DataCue\WooCommerce\Modules\Product;
use DataCue\WooCommerce\Modules\Order;
use DataCue\WooCommerce\Pages\SettingsPage;
use DataCue\WooCommerce\Widgets\Banner;
use DataCue\WooCommerce\Widgets\ProductCarousel;
use DataCue\WooCommerce\Events\BrowserEvents;

function onPluginActivated() {}

$dataCueOptions = get_option('datacue_options');

if ($dataCueOptions) {
    $client = new \DataCue\Client(
        $dataCueOptions['api_key'],
        $dataCueOptions['api_secret'],
        ['max_try_times' => 3],
        array_key_exists('server', $dataCueOptions) && $dataCueOptions['server'] === 'development' ? 'development' : 'production'
    );
    $options = ['debug' => false];

    // hooks
    User::registerHooks($client, $options);
    Product::registerHooks($client, $options);
    Order::registerHooks($client, $options);

    // TDO
    register_activation_hook(__FILE__, 'onPluginActivated');

    // widgets
    Banner::registerWidget();
    ProductCarousel::registerWidget();

    // events
    BrowserEvents::registerHooks($dataCueOptions);
}

// setting page
SettingsPage::registerPage();
