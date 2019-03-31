<?php

namespace DataCue\WooCommerce\Pages;

use DataCue\Client;
use DataCue\Exceptions\UnauthorizedException;
use DataCue\Exceptions\RetryCountReachedException;
use DataCue\WooCommerce\Common\Plugin;

/**
 * Class SettingsPage
 * @package DataCue\WooCommerce\Pages
 */
class SettingsPage
{
    /**
     * Options from constructor
     */
    private $systemOptions;

    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Generation
     * @param $options
     * @return SettingsPage
     */
    public static function registerPage($options)
    {
      return new static($options);
    }

    /**
     * SettingsPage constructor.
     * @param $options
     */
    public function __construct($options)
    {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'addPluginPage']);
            add_action('admin_init', [$this, 'pageInit']);
            add_action('add_option_datacue_options', [$this, 'optionsAdded'], 10, 2);
            add_action('update_option_datacue_options', [$this, 'optionsUpdated'], 10, 2);
            add_filter('plugin_action_links_woocommerce-datacue/woocommerce-datacue.php', [$this, 'pluginActionLinks']);

            $this->systemOptions = $options;
        }
    }

    /**
     * Filter hook
     * @param $links
     * @return array
     */
    public function pluginActionLinks($links)
    {
        $pluginLinks = [];
        $pluginLinks[] = '<a href="' . admin_url('options-general.php?page=datacue-setting-admin') . '">Settings</a>';

        return array_merge($pluginLinks, $links);
    }

    /**
     * Action hook
     */
    public function addPluginPage()
    {
        add_options_page(
            'DataCue Settings',
            'DataCue Settings',
            'manage_options',
            'datacue-setting-admin',
            [$this, 'createAdminPage']
        );
    }

    /**
     * Generate HTML code of the setting page
     */
    public function createAdminPage()
    {
        // Set class property
        $this->options = get_option('datacue_options');
        ?>
        <div class="wrap">
          <h1>DataCue Settings</h1>
          <form method="post" action="options.php">
              <?php
              // This prints out all hidden setting fields
              settings_fields('datacue_option_group');
              do_settings_sections('datacue-setting-admin');
              submit_button();
              ?>
          </form>
          <div style="margin-top: 10px; padding-top: 20px; border-top: 1px solid #aaa;">
            <a href="https://app.datacue.co" target="_blank" style="display: inline-block; text-decoration:none; background-color: #80ab4b; color: #fff; padding: 0 20px; height: 40px; border-radius: 20px; line-height: 40px; text-align: center;">LOGIN TO MY DATACUE DASHBOARD</a>
            <div style="margin-top: 20px; font-size: 15px;">No account yet? Sign up <a href="https://app.datacue.co/en/sign-up" target="_blank" style="color: #8c5c85; font-weight: 600;">here</a></div>
          </div>
        </div>
        <?php
    }

    /**
     * Option first added hook
     * @param $name
     * @param $value
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function optionsAdded($name, $value)
    {
        $this->syncData($value['api_key'], $value['api_secret']);
    }

    /**
     * Option updated hook
     * @param $oldValue
     * @param $newValue
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    public function optionsUpdated($oldValue, $newValue)
    {
        if ($newValue['api_key'] !== $oldValue['api_key'] || $newValue['api_secret'] !== $oldValue['api_secret']) {
            $this->syncData($newValue['api_key'], $newValue['api_secret']);
        }
    }

    /**
     * Register and add settings
     */
    public function pageInit()
    {
        register_setting(
            'datacue_option_group',
            'datacue_options',
            [$this, 'sanitize']
        );

        add_settings_section(
            'data_cue_base',
            'Base Settings',
            [$this, 'printBaseSection'],
            'datacue-setting-admin'
        );

        add_settings_field(
            'api_key',
            'Api Key',
            [$this, 'apiKeyCallback'],
            'datacue-setting-admin',
            'data_cue_base'
        );

        add_settings_field(
            'api_secret',
            'Api Secret',
            [$this, 'apiSecretCallback'],
            'datacue-setting-admin',
            'data_cue_base'
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     *
     * @return array
     */
    public function sanitize($input)
    {
        $newInput = [];
        if (isset($input['api_key']))
            $newInput['api_key'] = sanitize_text_field($input['api_key']);

        if (isset($input['api_secret']))
            $newInput['api_secret'] = sanitize_text_field($input['api_secret']);

        return $newInput;
    }

    /**
     * Print the Section text
     */
    public function printBaseSection()
    {
        print 'Enter your Api Key and Api Secret below:';
    }

    /**
     * Get the settings option and print one of its values
     */
    public function apiKeyCallback()
    {
        printf(
            '<input type="text" id="api_key" name="datacue_options[api_key]" value="%s" style="width: 200px" />',
            isset($this->options['api_key']) ? esc_attr($this->options['api_key']) : ''
        );
    }

    /**
     * Get the settings option and print one of its values
     */
    public function apiSecretCallback()
    {
        printf(
            '<input type="text" id="api_secret" name="datacue_options[api_secret]" value="%s" style="width: 300px" />',
            isset($this->options['api_secret']) ? esc_attr($this->options['api_secret']) : ''
        );
    }

    /**
     * Do sync data to datacue server
     * @param $apiKey
     * @param $apiSecret
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     */
    private function syncData($apiKey, $apiSecret)
    {
        $client = new Client(
            $apiKey,
            $apiSecret,
            ['max_try_times' => $this->systemOptions['max_try_times']],
            $this->systemOptions['env']
        );
        $options = ['debug' => $this->systemOptions['debug']];
        $instance = new Plugin($client, $options);

        try {
            $instance->syncData();
        } catch (UnauthorizedException $e) {
            add_settings_error(
                'datacue_options',
                'authorized_error',
                'Incorrect API key or API secret, please make sure to copy/paste them <strong>exactly</strong> as you see from your dashboard.'
            );
        } catch (RetryCountReachedException $e) {
            add_settings_error(
                'datacue_options',
                'sync_fail',
                'The synchronization task failed, Please contact the administrator.'
            );
        }
    }
}
