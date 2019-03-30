<?php
/**
 * Plugin Name: DataCue for WooCommerce
 * Plugin URI: https://datacue.co/
 * Description: Improve sales by showing relevant content to your WooCommerce visitors with DataCue
 * Version: 1.0.0
 * Author: DataCue
 * Author URI: https://datacue.co/
 * Text Domain: woocommerce datacue plugin
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/vendor/autoload.php';
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

use DataCue\Client;
use DataCue\WooCommerce\Modules\User;
use DataCue\WooCommerce\Modules\Product;
use DataCue\WooCommerce\Modules\Order;
use DataCue\WooCommerce\Pages\SettingsPage;
use DataCue\WooCommerce\Widgets\Banner;
use DataCue\WooCommerce\Widgets\ProductCarousel;
use DataCue\WooCommerce\Events\BrowserEvents;
use DataCue\WooCommerce\Common\Activator;
use DataCue\WooCommerce\Common\Schedule;

const ENV = 'development'; // development or production
const MAX_TRY_TIMES = 1;
const DEBUG = true;

$dataCueOptions = get_option('datacue_options');

if ($dataCueOptions) {
    $client = new Client(
        $dataCueOptions['api_key'],
        $dataCueOptions['api_secret'],
        ['max_try_times' => MAX_TRY_TIMES],
        ENV
    );
    $options = ['debug' => DEBUG];

    // hooks
    User::registerHooks($client, $options);
    Product::registerHooks($client, $options);
    Order::registerHooks($client, $options);

    // activation hooks
    Activator::registerHooks(__FILE__, $client, $options);

    // schedule hooks
    Schedule::registerHooks($client, $options);

    // widgets
    Banner::registerWidget();
    ProductCarousel::registerWidget();

    // events
    BrowserEvents::registerHooks($dataCueOptions);
}

// setting page
SettingsPage::registerPage([
    'max_try_times' => MAX_TRY_TIMES,
    'env' => ENV,
    'debug' => DEBUG,
]);
