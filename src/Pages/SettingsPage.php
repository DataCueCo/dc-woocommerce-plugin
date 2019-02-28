<?php

namespace DataCue\WooCommerce\Pages;

/**
 * Class SettingsPage
 * @package DataCue\WooCommerce\Pages
 */
class SettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Generation
     * @return SettingsPage
     */
    public static function registerPage()
    {
      return new static();
    }

    /**
     * SettingsPage constructor.
     */
    public function __construct()
    {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'addPluginPage']);
            add_action('admin_init', [$this, 'pageInit']);
            add_filter('plugin_action_links_woocommerce-datacue/woocommerce-datacue.php', [$this, 'pluginActionLinks']);
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
        </div>
        <?php
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

        add_settings_section(
            'data_cue_env',
            'Environment Settings',
            [$this, 'printEnvSection'],
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

        add_settings_field(
            'server',
            'Server',
            [$this, 'serverCallback'],
            'datacue-setting-admin',
            'data_cue_env'
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

        if (isset($input['server']))
            $newInput['server'] = sanitize_text_field($input['server']);

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
     * Print the Section text
     */
    public function printEnvSection()
    {
        print 'Enter your environment settings below:';
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
     * Get the settings option and print one of its values
     */
    public function serverCallback()
    {
        $productionChecked = !isset($this->options['server']) || esc_attr($this->options['server']) === 'production' ? 'checked' : '';
        $developmentChecked = isset($this->options['server']) && esc_attr($this->options['server']) === 'development' ? 'checked' : '';

        printf(
            '<input type="radio" name="datacue_options[server]" value="production" %s /> Production ' .
            '<input type="radio" name="datacue_options[server]" value="development" %s /> Development',
            $productionChecked, $developmentChecked
        );
    }
}
