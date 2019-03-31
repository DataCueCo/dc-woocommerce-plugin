<?php

namespace DataCue\WooCommerce\Common;

use DataCue\WooCommerce\Utils\Log;

/**
 * Class Activator
 * @package DataCue\WooCommerce\Common
 */
class Activator
{
    /**
     * @var \DataCue\Client
     */
    private $client;

    /**
     * @var Log
     */
    private $logger = null;

    /**
     * Generation
     *
     * @param $file
     * @param $client
     * @param $options
     * @return Plugin
     */
    public static function registerHooks($file, $client, $options)
    {
        return new static($file, $client, $options);
    }
    /**
     * Plugin constructor.
     * @param $file
     * @param $client
     * @param $options
     */
    public function __construct($file, $client, $options)
    {
        $this->client = $client;
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
        $this->log('onPluginActivated');
        $this->createQueueTable();
    }

    /**
     * Deletion hook
     */
    public function onPluginDeactivated()
    {
        $this->log('onPluginDeactivated');

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        global $wpdb;
        $sql = "DROP TABLE IF EXISTS {$wpdb->prefix}datacue_queue;";
        $wpdb->query($sql);
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
