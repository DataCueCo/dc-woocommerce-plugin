<?php

namespace DataCue\WooCommerce\Modules;

use DataCue\WooCommerce\Utils\Log;

/**
 * Class Base
 * @package DataCue\WooCommerce\Modules
 */
abstract class Base
{
    /**
     * @var \DataCue\Client
     */
    protected $client;

    /**
     * @var Log
     */
    private $logger = null;

    /**
     * Generate Module Object
     * @param $client
     * @param array $options
     * @return Base
     */
    public static function registerHooks($client, $options = [])
    {
        return new static($client, $options);
    }

    /**
     * Base constructor.
     * @param $client
     * @param array $options
     */
    public function __construct($client, $options = [])
    {
        $this->client = $client;

        if (array_key_exists('debug', $options) && $options['debug']) {
            $this->logger = new Log();
        }
    }

    /**
     * Log function
     * @param $message
     */
    protected function log($message)
    {
        if (!is_null($this->logger)) {
            $this->logger->info($message);
        }
    }

    /**
     * Add task to queue
     * @param $model
     * @param $action
     * @param $modelId
     * @param $job
     */
    protected function addTaskToQueue($model, $action, $modelId, $job)
    {
        $job = json_encode($job);
        global $wpdb;
        $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (`model`, `action`, `model_id`, `job`, `executed_at`, `created_at`) values ('$model', '$action', $modelId, '$job', NULL, NOW())";
        dbDelta( $sql );
    }

    /**
     * Update existing task
     * @param $id
     * @param $job
     */
    protected function updateTask($id, $job)
    {
        $job = json_encode($job);
        global $wpdb;
        $sql = "UPDATE {$wpdb->prefix}datacue_queue SET job = '$job' WHERE id = $id";
        dbDelta( $sql );
    }

    /**
     * Find task
     * @param $model
     * @param $action
     * @param $modelId
     * @return array|null|object|void
     */
    protected function findTask($model, $action, $modelId)
    {
        global $wpdb;
        $row = $wpdb->get_row("SELECT `id`,`model`, `model_id`,`action`,`job` FROM `{$wpdb->prefix}datacue_queue` WHERE `model` = '$model' AND `action` = '$action' AND `model_id` = $modelId LIMIT 1");
        if (!is_null($row)) {
            $row->job = json_decode($row->job);
        }
        return $row;
    }

    /**
     * Find alive task
     * @param $model
     * @param $action
     * @param $modelId
     * @return array|null|object|void
     */
    protected function findAliveTask($model, $action, $modelId)
    {
        global $wpdb;
        $row = $wpdb->get_row("SELECT `id`,`model`, `model_id`,`action`,`job` FROM `{$wpdb->prefix}datacue_queue` WHERE `model` = '$model' AND `action` = '$action' AND `model_id` = $modelId AND `executed_at` IS NULL LIMIT 1");
        if (!is_null($row)) {
            $row->job = json_decode($row->job);
        }
        return $row;
    }
}
