<?php

namespace DataCue\WooCommerce\Common;

use DataCue\WooCommerce\Utils\Log;

/**
 * Class Plugin
 * @package DataCue\WooCommerce\Common
 */
class Plugin
{

    /**
     * @var Log
     */
    private $logger = null;

    /**
     * @var null
     */
    private $baseFile = null;

    /**
     * Generation
     *
     * @param $file
     * @param $options
     * @return Initializer
     */
    public static function registerHooks($file, $options)
    {
        return new static($file, $options);
    }
    /**
     * Plugin constructor.
     * @param $file
     * @param $options
     */
    public function __construct($file, $options)
    {
        $this->baseFile = $file;
        if (array_key_exists('debug', $options) && $options['debug']) {
            $this->logger = new Log();
        }
        register_activation_hook($file, [$this, 'onPluginActivated']);
        register_deactivation_hook($file, [$this, 'onPluginDeactivated']);
    }

    /**
     * Activation hook
     */
    public function onPluginActivated()
    {
        if (version_compare(PHP_VERSION, '5.5', '<')) {
            $this->log('php version must be greater than or equal to 5.5');
            deactivate_plugins($this->baseFile);
            wp_die(
                '<p>The <strong>DataCue</strong> plugin requires PHP version 5.5 or greater.</p>',
                'Plugin Activation Error',
                [ 'response' => 200, 'back_link' => true ]
            );
            return;
        }
        $this->log('onPluginActivated');
        $this->createQueueTable();
    }

    /**
     * Deletion hook
     */
    public function onPluginDeactivated()
    {
        $this->log('onPluginDeactivated');

        wp_clear_scheduled_hook( 'datacue_worker_cron' );
    }

    /**
     * Create job queue table
     */
    private function createQueueTable()
    {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        global $wpdb;

        $wpdb->hide_errors();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}datacue_queue (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				`action` varchar(32) NOT NULL,
				model varchar(32) NOT NULL,
				model_id int(11) DEFAULT NULL,
                job mediumtext NOT NULL,
                status int(11) NOT NULL DEFAULT 0,
                executed_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id)
				) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * Log function
     * @param $message
     */
    private function log($message)
    {
        if (!is_null($this->logger)) {
            $this->logger->info($message);
        }
    }
}
