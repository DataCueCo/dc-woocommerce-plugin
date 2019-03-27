<?php

namespace DataCue\WooCommerce\Modules;

use DataCue\Exceptions\RetryCountReachedException;

/**
 * Class User
 * @package DataCue\WooCommerce\Modules
 */
class User extends Base
{
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
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onUserCreated($userId)
    {
        $this->log('onUserCreated');
        global $wpdb;
        $user = $wpdb->get_row("SELECT `id` as `user_id`, `user_email` as `email`, DATE_FORMAT(`user_registered`, '%Y-%m-%dT%TZ') AS `timestamp` FROM `wp_users` where `id`=$userId");
        $metaInfo = $wpdb->get_results("SELECT `meta_key`, `meta_value` FROM `wp_usermeta` where `user_id`=$userId AND `meta_key` IN('first_name', 'last_name')");
        array_map(function ($item) use ($user) {
            $user->{$item->meta_key} = $item->meta_value;
        }, $metaInfo);

        try {
            $res = $this->client->users->create($user);
            $this->log('create user response: ' . $res);
        } catch (RetryCountReachedException $e) {
            $this->log($e->errorMessage());
        }
    }

    /**
     * User updated callback
     * @param $userId
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onUserUpdated($userId)
    {
        $this->log('onUserUpdated');
        global $wpdb;
        $user = $wpdb->get_row("SELECT `user_email` as `email`, DATE_FORMAT(`user_registered`, '%Y-%m-%dT%TZ') AS `timestamp` FROM `wp_users` where `id`=$userId");
        $metaInfo = $wpdb->get_results("SELECT `meta_key`, `meta_value` FROM `wp_usermeta` where `user_id`=$userId AND `meta_key` IN('first_name', 'last_name')");
        array_map(function ($item) use ($user) {
            $user->{$item->meta_key} = $item->meta_value;
        }, $metaInfo);

        try {
            $res = $this->client->users->update($userId, $user);
            $this->log('update user response: ' . $res);
        } catch (RetryCountReachedException $e) {
            $this->log($e->errorMessage());
        }
    }

    /**
     * User deleted callback
     * @param $userId
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function onUserDeleted($userId)
    {
        $this->log('onUserDeleted');

        try {
            $res = $this->client->users->delete($userId);
            $this->log('delete user response: ' . $res);
        } catch (RetryCountReachedException $e) {
            $this->log($e->errorMessage());
        }
    }
}
