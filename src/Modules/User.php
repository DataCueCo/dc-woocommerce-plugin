<?php

namespace DataCue\WooCommerce\Modules;

/**
 * Class User
 * @package DataCue\WooCommerce\Modules
 */
class User extends Base
{
    /**
     * Generate user item for DataCue
     * @param $id int User ID
     * @param $withId bool
     * @return array|null
     */
    public static function generateUserItem($id, $withId = false)
    {
        global $wpdb;
        $sql = "SELECT `user_email` as `email`, DATE_FORMAT(`user_registered`, '%%Y-%%m-%%dT%%TZ') AS `timestamp` FROM `{$wpdb->prefix}users` where `id`=%d";
        $user = $wpdb->get_row(
            $wpdb->prepare($sql, $id)
        );
        if (empty($user)) {
            return null;
        }
        $sql = "SELECT `meta_key`, `meta_value` FROM `{$wpdb->prefix}usermeta` where `user_id`=%d AND `meta_key` IN('first_name', 'last_name')";
        $metaInfo = $wpdb->get_results(
            $wpdb->prepare($sql, $id)
        );
        array_map(function ($item) use ($user) {
            $user->{$item->meta_key} = $item->meta_value;
        }, $metaInfo);

        if ($withId) {
            $user->user_id = $id;
        }

        return $user;
    }

    /**
     * User constructor.
     * @param $client
     * @param array $options
     */
    public function __construct($client, array $options = [])
    {
        parent::__construct($client, $options);

        add_action('user_register', [$this, 'onUserCreated']);
        add_action('profile_update', [$this, 'onUserUpdated']);
        add_action('deleted_user', [$this, 'onUserDeleted']);
    }

    /**
     * User created callback
     * @param $userId
     */
    public function onUserCreated($userId)
    {
        $this->log('onUserCreated');
        $user = static::generateUserItem($userId, true);

        $this->addTaskToQueue('users', 'create', $userId, ['item' => $user]);
    }

    /**
     * User updated callback
     * @param $userId
     */
    public function onUserUpdated($userId)
    {
        $this->log('onUserUpdated');
        $user = static::generateUserItem($userId, false);

        if ($task = $this->findAliveTask('users', 'create', $userId)) {
            $user->user_id = $userId;
            $this->updateTask($task->id, ['item' => $user]);
        } elseif ($task = $this->findAliveTask('users', 'update', $userId)) {
            $this->updateTask($task->id, ['userId' => $userId, 'item' => $user]);
        } else {
            $this->addTaskToQueue('users', 'update', $userId, ['userId' => $userId, 'item' => $user]);
        }
    }

    /**
     * User deleted callback
     * @param $userId
     */
    public function onUserDeleted($userId)
    {
        $this->log('onUserDeleted');

        $this->addTaskToQueue('users', 'delete', $userId, ['userId' => $userId]);
    }
}
