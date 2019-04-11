<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'datacue_worker_cron' );

global $wpdb;
$sql = "DROP TABLE IF EXISTS {$wpdb->prefix}datacue_queue;";
$wpdb->query($sql);
