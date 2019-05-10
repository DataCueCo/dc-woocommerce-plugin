<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'datacue_worker_cron' );

global $wpdb;
$sql = "DROP TABLE IF EXISTS {$wpdb->prefix}datacue_queue;";
$wpdb->query($sql);

require_once __DIR__ . '/vendor/autoload.php';

// clear client
$env = file_exists(__DIR__ . '/staging') ? 'development' : 'production'; // development or production
$dataCueOptions = get_option('datacue_options');
if ($dataCueOptions) {
    try {
        $client = new \DataCue\Client(
            $dataCueOptions['api_key'],
            $dataCueOptions['api_secret'],
            ['max_try_times' => 3],
            $env
        );
        $client->client->clear();
    } catch (\Exception $e) {
    }
}
