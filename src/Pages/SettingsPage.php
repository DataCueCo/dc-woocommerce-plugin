<?php

namespace DataCue\WooCommerce\Pages;

use DataCue\Client;
use DataCue\Exceptions\UnauthorizedException;
use DataCue\WooCommerce\Common\Initializer;
use Exception;

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

            add_filter('plugin_action_links_dc-woocommerce-plugin/dc-woocommerce-plugin.php', [$this, 'pluginActionLinks']);

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

        $this->enqueueScripts();
        $this->enqueueStyles();
    }

    public function enqueueScripts()
    {
        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-tabs' );
    }

    public function enqueueStyles()
    {
        $wp_scripts = wp_scripts();
        wp_enqueue_style(
            'jquery-ui-theme-smoothness',
            sprintf(
                '//ajax.googleapis.com/ajax/libs/jqueryui/%s/themes/smoothness/jquery-ui.css', // working for https as well now
                $wp_scripts->registered['jquery-ui-core']->ver
            )
        );
    }

    /**
     * Generate HTML code of the setting page
     */
    public function createAdminPage()
    {
        // Dates
        $dates = '<select id="datacue-logs-date-select">';
        $timestamp = time();
        for ($i = 0; $i < 3; $i++) {
            $date = date('Y-m-d', $timestamp);
            if (file_exists(__DIR__ . "/../../datacue-$date.log")) {
                $dates .= "<option value=\"$date\"" . ($i === 0 ? ' selected' : '') . ">$date</option>";
            }
            $timestamp -= 24 * 3600;
        }
        $dates .= '</select>';

        // Set class property
        $this->options = get_option('datacue_options');
        ?>
        <link rel="stylesheet" type="text/css" href="<?php echo get_site_url(null, 'wp-content/plugins/dc-woocommerce-plugin/assets/css/common.css'); ?>" />
        <div class="wrap">
          <h1>DataCue Settings</h1>
          <div id="datacue-tabs">
            <ul>
              <li><a href="#datacue-tabs-1">Settings</a></li>
              <li><a href="#datacue-tabs-2">Sync Status</a></li>
              <li><a href="#datacue-tabs-3">Logs</a></li>
            </ul>
            <div id="datacue-tabs-1">
              <form method="post" action="options.php">
                  <?php
                  // This prints out all hidden setting fields
                  settings_fields('datacue_option_group');
                  do_settings_sections('datacue-setting-admin');
                  submit_button();
                  ?>
              </form>
              <div class="datacue-helper-section">
                <h2>Here are some resources you might find helpful</h2>
                <ul class="list">
                  <li><a href="https://help.datacue.co/install/woocommerce.html#add-recommendations" target="_blank">Add banners and products to your site</a></li>
                </ul>
              </div>
              <div class="datacue-helper-section">
                <a class="a-login" href="https://app.datacue.co" target="_blank">LOGIN TO MY DATACUE DASHBOARD</a>
                <div class="a-sign-up">No account yet? Sign up <a href="https://app.datacue.co/en/sign-up" target="_blank" style="color: #8c5c85; font-weight: 600;">here</a></div>
              </div>
              <div class="datacue-helper-section">
                <h2>Support Center</h2>
                <p>Questions? Need help? Email us at <a href="mailto:support@datacue.co" target="_blank">support@datacue.co</a> to speak to a real person</p>
              </div>
            </div>
            <div id="datacue-tabs-2">
              <table id="datacue-sync-status-table">
                <thead>
                <tr>
                  <th width="100">Data Type</th>
                  <th width="100">Total</th>
                  <th width="100">Number of pending</th>
                  <th width="100">Number of successes</th>
                  <th width="100">Number of failures</th>
                </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div id="datacue-tabs-3">
              <?php echo $dates; ?>
              <iframe id="datacue-log-frame" src="" style="width: 100%; height: 500px; border: 0.5px solid #eee; margin-top: 5px;"></iframe>
            </div>
          </div>
        </div>
        <script type="text/javascript">
        function loadSyncStatus() {
          jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: {
              action: 'load_sync_status_action'
            },
            success: function(data) {
              var html = '';
              Object.keys(data).forEach(function (key) {
                html += '<tr>';
                html += '<td align="center">' + key + '</td>';
                html += '<td align="center">' + data[key].total + '</td>';
                html += '<td align="center">' + (data[key].total - data[key].completed - data[key].failed) + '</td>';
                html += '<td align="center">' + data[key].completed + '</td>';
                html += '<td align="center">' + data[key].failed + '</td>';
                html += '</tr>';
              });
              jQuery("#datacue-sync-status-table tbody").html(html);
            },
            error: function() {
              console.log('load sync status error');
            }
          });
        }
        function getLogOfDate(date) {
          jQuery("#datacue-log-frame").attr('src', '<?php echo plugins_url('datacue-', 'dc-woocommerce-plugin/dc-woocommerce-plugin.php'); ?>' + date + '.log');
        }
        jQuery(document).ready(function () {
          jQuery('#datacue-tabs').tabs();
          var currentDate = jQuery("#datacue-logs-date-select").val();
          if (currentDate && currentDate != '') {
            getLogOfDate(currentDate);
          }
          jQuery("#datacue-logs-date-select").change(function() {
            getLogOfDate(jQuery("#datacue-logs-date-select").val());
          });
          loadSyncStatus();
          setInterval(loadSyncStatus, 30000);
        });
        </script>
        <?php
    }

    /**
     * Option first added hook
     * @param $name
     * @param $value
     */
    public function optionsAdded($name, $value)
    {
        $this->syncData($value['api_key'], $value['api_secret']);
    }

    /**
     * Option updated hook
     * @param $oldValue
     * @param $newValue
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
        $instance = new Initializer($client, $options);

        try {
            $instance->syncData();
        } catch (UnauthorizedException $e) {
            add_settings_error(
                'datacue_options',
                'authorized_error',
                'Incorrect API key or API secret, please make sure to copy/paste them <strong>exactly</strong> as you see from your dashboard.'
            );
        } catch (Exception $e) {
            add_settings_error(
                'datacue_options',
                'sync_fail',
                'The synchronization task failed, Please contact the administrator.'
            );
        }
    }
}
