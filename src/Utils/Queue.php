<?php

namespace DataCue\WooCommerce\Utils;

class Queue
{
    /**
     * Add task to queue
     * @param $model
     * @param $action
     * @param $modelId
     * @param $job
     */
    public static function addTask($model, $action, $modelId, $job)
    {
        $job = json_encode($job);
        global $wpdb;
        $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (`model`, `action`, `model_id`, `job`, `executed_at`, `created_at`) values (%s, %s, %d, %s, NULL, NOW())";
        $wpdb->query(
            $wpdb->prepare($sql, $model, $action, $modelId, $job)
        );
    }

    /**
     * Add task without model id
     * @param $model
     * @param $action
     * @param $modelId
     * @param $job
     */
    public static function addTaskWithModelId($model, $action, $job)
    {
        $job = json_encode($job);
        global $wpdb;
        $sql = "INSERT INTO {$wpdb->prefix}datacue_queue (`model`, `action`, `job`, `executed_at`, `created_at`) values (%s, %s, %s, NULL, NOW())";
        $wpdb->query(
            $wpdb->prepare($sql, $model, $action, $job)
        );
    }

    /**
     * Update existing task
     * @param $id
     * @param $job
     */
    public static function updateTask($id, $job)
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
    public static function findTask($model, $action, $modelId)
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
    public static function findAliveTask($model, $action, $modelId)
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
