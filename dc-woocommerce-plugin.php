<?php
/**
 * Plugin Name: DataCue for WooCommerce
 * Plugin URI: https://help.datacue.co/install/woocommerce.html
 * Description: Improve sales by showing relevant content to your WooCommerce visitors with DataCue
 * Version: 1.1.6
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
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

use DataCue\Client;
use DataCue\WooCommerce\Modules\User;
use DataCue\WooCommerce\Modules\Product;
use DataCue\WooCommerce\Modules\Order;
use DataCue\WooCommerce\Modules\Category;
use DataCue\WooCommerce\Modules\Event;
use DataCue\WooCommerce\Pages\SettingsPage;
use DataCue\WooCommerce\Widgets\Banner;
use DataCue\WooCommerce\Widgets\Products;
use DataCue\WooCommerce\Events\BrowserEvents;
use DataCue\WooCommerce\Common\Plugin;
use DataCue\WooCommerce\Common\Schedule;
use DataCue\WooCommerce\Common\ReSync;
use DataCue\WooCommerce\Shortcode;

$env = file_exists(__DIR__ . '/staging') ? 'development' : 'production'; // development or production
const MAX_TRY_TIMES = 3;
const DEBUG = true;

$options = ['debug' => DEBUG];

// activation hooks
Plugin::registerHooks(__FILE__, $options);

if (is_plugin_active('dc-woocommerce-plugin/dc-woocommerce-plugin.php')) {
    // set integration and version info to client
    $pluginData = get_plugin_data(__FILE__);
    Client::setIntegrationAndVersion('WooCommerce', $pluginData['Version']);

    $dataCueOptions = get_option('datacue_options');
    if ($dataCueOptions) {

        $client = new Client(
            $dataCueOptions['api_key'],
            $dataCueOptions['api_secret'],
            ['max_try_times' => MAX_TRY_TIMES],
            $env
        );

        // hooks
        User::registerHooks($client, $options);
        Product::registerHooks($client, $options);
        Order::registerHooks($client, $options);
        Category::registerHooks($client, $options);
        Event::registerHooks($client, $options);

        // schedule hooks
        ReSync::registerHooks($client, $options);
        Schedule::registerHooks($client, $options);

        // widgets
        Banner::registerWidget();
        Products::registerWidget();

        // events
        BrowserEvents::registerHooks($dataCueOptions, $env);

        // shortcodes
        Shortcode::registerShortcodes();
    }

    // setting page
    SettingsPage::registerPage([
        'max_try_times' => MAX_TRY_TIMES,
        'env' => $env,
        'debug' => DEBUG,
    ]);
}
