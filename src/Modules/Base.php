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
        $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (`model`, `action`, `model_id`, `job`, `executed_at`, `created_at`) values (%s, %s, %d, %s, NULL, NOW())";
        $wpdb->query(
            $wpdb->prepare($sql, $model, $action, $modelId, $job)
        );
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
        $sql = "UPDATE {$wpdb->prefix}datacue_queue SET job = %s WHERE id = %d";
        $wpdb->query(
            $wpdb->prepare($sql, $job, $id)
        );
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
        $sql = "SELECT `id`, `model`, `model_id`, `action`, `job` FROM `{$wpdb->prefix}datacue_queue` WHERE `model` = %s AND `action` = %s AND `model_id` = %d LIMIT 1";
        $row = $wpdb->get_row(
            $wpdb->prepare($sql, $model, $action, $modelId)
        );
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
        $sql = "SELECT `id`,`model`, `model_id`,`action`,`job` FROM `{$wpdb->prefix}datacue_queue` WHERE `model` = %s AND `action` = %s AND `model_id` = %d AND `executed_at` IS NULL LIMIT 1";
        $row = $wpdb->get_row(
            $wpdb->prepare($sql, $model, $action, $modelId)
        );
        if (!is_null($row)) {
            $row->job = json_decode($row->job);
        }
        return $row;
    }
}
