<?php
/*
Plugin Name: Performance and Diagnostics Tool
Description: General Diagnostics and Optimization checks for WordPress.
Version: 1.0
Author: MSB_The_Destroyer
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

require_once 'includes/initialize.php';
require_once 'includes/dnscheck.php';
require_once 'includes/memory-and-uptime.php';
require_once 'includes/php-and-opcache.php';
require_once 'includes/wordpress-core-plugins-themes.php';
require_once 'includes/database-details.php';
require_once 'includes/accesslog-details.php';
require_once 'includes/cache-details.php';
require_once 'includes/cronjob-events.php';
require_once 'includes/error-log-50.php';
require_once 'includes/lighthouse-api.php';

// Filter to add custom tab to Site Health
function wpqr_site_health_navigation_tabs($tabs)
{
    $tabs['wpqr-site-health-tab'] = esc_html_x('Performance & Diagnostics', 'Site Health', 'text-domain');
    return $tabs;
}
add_filter('site_health_navigation_tabs', 'wpqr_site_health_navigation_tabs');

// Function to add Lighthouse button
function wpqr_site_health_lighthouse_button()
{
    $api_key = get_option('pad_api_key');
    if (empty($api_key)) {
        echo '<div class="notice notice-warning"><p>' . __('Please set your Google LightHouse API key in the <a href="' . admin_url('options-general.php?page=pad-settings') . '">Performance & Diagnostics settings</a> before proceeding.', 'textdomain') . '</p></div>';
    } else {
        echo '<form action="' . plugin_dir_url(__FILE__) . 'lighthouse-report.php" method="get" target="_blank">';
        echo '<input type="submit" class="button button-primary" value="' . __('Run Lighthouse Report', 'textdomain') . '">';
        echo '</form>';
    }
}

// Enqueue styles for the Lighthouse report and clipboard script
function enqueue_lighthouse_styles_and_scripts()
{
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'site-health.php') !== false) {
        wp_enqueue_script('clipboard-copy-script', plugin_dir_url(__FILE__) . 'includes/clipboard-copy.js', array(), '1.0', true);
    }
}
add_action('admin_enqueue_scripts', 'enqueue_lighthouse_styles_and_scripts');

// Add settings menu item
function pad_settings_menu()
{
    add_options_page(
        'Performance and Diagnostics Settings',
        'Performance & Diagnostics',
        'manage_options',
        'pad-settings',
        'pad_settings_page'
    );
}
add_action('admin_menu', 'pad_settings_menu');

// Add settings link on plugin page
function pad_plugin_action_links($links)
{
    $settings_link = '<a href="' . admin_url('options-general.php?page=pad-settings') . '">' . __('Settings', 'textdomain') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pad_plugin_action_links');

// Settings page content
function pad_settings_page()
{
    $api_key = get_option('pad_api_key');
    ?>
    <div class="wrap">
    <p><a href="<?php echo admin_url('site-health.php?tab=wpqr-site-health-tab'); ?>">Return to Performance & Diagnostics</a></p>
        <form method="post" action="options.php">
            <?php
settings_fields('pad-settings-group');
    do_settings_sections('pad-settings');
    submit_button();
    ?>
        </form>
    </div>
    <?php

}

// Callback for rendering API key field
function pad_api_key_callback()
{
    $api_key = get_option('pad_api_key');
    echo '<input type="text" name="pad_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
    if (empty($api_key)) {
        echo '<p class="description">' . __('Create API Key for Google Page Speed Insights: <a href="https://developers.google.com/speed/docs/insights/v5/" target="_blank">https://developers.google.com/speed/docs/insights/v5/</a>', 'textdomain') . '</p>';
    }
}

// Register settings
function pad_register_settings()
{
    register_setting('pad-settings-group', 'pad_log_paths');
    register_setting('pad-settings-group', 'pad_api_key');

    add_settings_section('pad-settings-section', 'Log Paths Settings', null, 'pad-settings');

    add_settings_field(
        'today_access_log',
        'Today\'s Access Log Path',
        'pad_log_path_callback',
        'pad-settings',
        'pad-settings-section',
        array('today_access_log')
    );

    add_settings_field(
        'yesterday_access_log',
        'Yesterday\'s Access Log Path',
        'pad_log_path_callback',
        'pad-settings',
        'pad-settings-section',
        array('yesterday_access_log')
    );

    add_settings_field(
        'error_log',
        'Error Log Path',
        'pad_log_path_callback',
        'pad-settings',
        'pad-settings-section',
        array('error_log')
    );

    // Add the API key field
    add_settings_field(
        'api_key',
        'Google LightHouse API Key',
        'pad_api_key_callback',
        'pad-settings',
        'pad-settings-section'
    );
}
add_action('admin_init', 'pad_register_settings');

// Callback for rendering log path fields
function pad_log_path_callback($args)
{
    $log_paths = get_option('pad_log_paths');
    $path = isset($log_paths[$args[0]]) ? esc_attr($log_paths[$args[0]]) : '';
    echo '<input type="text" name="pad_log_paths[' . esc_attr($args[0]) . ']" value="' . $path . '" class="regular-text">';
}

// Function to check if log paths are set
function are_log_paths_set()
{
    $log_paths = get_option('pad_log_paths');
    return !empty($log_paths['today_access_log']) && !empty($log_paths['yesterday_access_log']) && !empty($log_paths['error_log']);
}

function is_dreampress_server()
{
    $hostname = gethostname();
    if (preg_match('/^dp-[a-zA-Z0-9]+$/', $hostname)) {
        return true;
    }
    return false;
}

// Function to add content to custom Site Health tab
function wpqr_site_health_tab_content($tab)
{
    if ('wpqr-site-health-tab' !== $tab) {return;}

    echo '<div class="health-check-body">';
    echo '<div class="health-check-accordion">';

    // DNS Lookup
    echo '<h3 class="health-check-accordion-heading"><button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-dns" type="button"><span class="title">Server IP & DNS</span><span class="icon"></span></button></h3>';
    echo '<div id="health-check-accordion-block-dns" class="health-check-accordion-panel" hidden>';

    echo '<div class="copy-content" id="dns-copy-content">';
    $dns = get_dns();
    foreach ($dns->sections as $section) {
        //echo '<h4>'.$section['name'].'</h4>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        foreach (array_keys($section['data'][0]) as $header) {
            echo '<th>' . $header . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($section['data'] as $table) {
            echo '<tr>';
            foreach ($table as $row) {
                echo '<td>' . $row . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<br>';
    }

    echo '</div>';
    echo '<button class="copy-btn" data-target="#dns-copy-content">Copy to Clipboard</button>';
    echo '</div>';

    // Memory and Uptime
    echo '<h3 class="health-check-accordion-heading"><button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-memory-uptime" type="button"><span class="title">Server Uptime & RAM Usage</span><span class="icon"></span></button></h3>';
    echo '<div id="health-check-accordion-block-memory-uptime" class="health-check-accordion-panel" hidden>';

    echo '<div class="copy-content" id="memory-uptime-copy-content">';
    $memory_and_uptime = get_memory_and_uptime();
    foreach ($memory_and_uptime->sections as $section) {
        //echo '<h4>'.$section['name'].'</h4>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<tbody>';
        foreach ($section['data'] as $table) {
            foreach ($table as $key => $value) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($key) . '</strong></td>';
                echo '<td>' . esc_html($value) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';
        echo '<br>';
    }

    echo '</div>';
    echo '<button class="copy-btn" data-target="#memory-uptime-copy-content">Copy to Clipboard</button>';
    echo '</div>';

    // PHP and OPcache Info
    echo '<h3 class="health-check-accordion-heading"><button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-php-opcache" type="button"><span class="title">PHP & OPcache Info</span><span class="icon"></span></button></h3>';
    echo '<div id="health-check-accordion-block-php-opcache" class="health-check-accordion-panel" hidden>';

    echo '<div class="copy-content" id="php-opcache-copy-content">';
    $php_and_opcache = get_php_and_opcache_info();

    foreach ($php_and_opcache->sections as $section) {
        //echo '<h4>'.$section->name.'</h4>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<tbody>';
        foreach ($section->data as $key => $value) {
            echo '<tr>';
            echo '<td><strong>' . $key . '</strong></td>';
            echo '<td>' . $value . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<br>';
    }

    echo '</div>';
    echo '<button class="copy-btn" data-target="#php-opcache-copy-content">Copy to Clipboard</button>';
    echo '</div>';

    // Core, Plugins & Themes Info
    echo '<h3 class="health-check-accordion-heading"><button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-core-plugins-themes" type="button"><span class="title">Available Updates For Active Core/Plugins/Theme</span><span class="icon"></span></button></h3>';
    echo '<div id="health-check-accordion-block-core-plugins-themes" class="health-check-accordion-panel" hidden>';

    echo '<div class="copy-content" id="core-plugins-themes-copy-content">';
    $core_plugins_themes_info = get_wordpress_core_plugins_themes_info();
    foreach ($core_plugins_themes_info->sections as $section) {
        //echo '<h4>'.$section->name.'</h4>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        foreach (array_keys((array) $section->data[0]) as $header) {
            echo '<th>' . $header . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($section->data as $row) {
            echo '<tr>';
            foreach ((array) $row as $value) {
                echo '<td>' . $value . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<br>';
    }

    echo '</div>';
    echo '<button class="copy-btn" data-target="#core-plugins-themes-copy-content">Copy to Clipboard</button>';
    echo '</div>';

    // Database Details
    echo '<h3 class="health-check-accordion-heading"><button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-database-details" type="button"><span class="title">Database Details</span><span class="icon"></span></button></h3>';
    echo '<div id="health-check-accordion-block-database-details" class="health-check-accordion-panel" hidden>';

    echo '<div class="copy-content" id="database-details-copy-content">';
    $database_details = get_database_details();
    foreach ($database_details->sections as $section) {
        //echo '<h4>'.$section->name.'</h4>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        foreach (array_keys((array) $section->data[0]) as $header) {
            echo '<th>' . $header . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($section->data as $row) {
            echo '<tr>';
            foreach ((array) $row as $value) {
                echo '<td>' . $value . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<br>';
    }

    echo '</div>';
    echo '<button class="copy-btn" data-target="#database-details-copy-content">Copy to Clipboard</button>';
    echo '</div>';

    // Access Log Details
    echo '<h3 class="health-check-accordion-heading"><button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-accesslog-details" type="button"><span class="title">Access Log Details</span><span class="icon"></span></button></h3>';
    echo '<div id="health-check-accordion-block-accesslog-details" class="health-check-accordion-panel" hidden>';

    if (!are_log_paths_set()) {
        echo '<div class="notice notice-warning"><p>' . __('Please set the log paths in the <a href="' . admin_url('options-general.php?page=pad-settings') . '">Performance & Diagnostics settings</a> before proceeding.', 'textdomain') . '</p></div>';
    } else {
        echo '<div class="copy-content" id="accesslog-details-copy-content">';
        // HTTP Requests
        $http_requests_info = get_http_requests_info();
        foreach ($http_requests_info->sections as $section) {
            //echo '<h4>'.$section->name.'</h4>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            foreach (array_keys((array) $section->data[0]) as $header) {
                echo '<th>' . $header . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($section->data as $row) {
                echo '<tr>';
                foreach ((array) $row as $key => $value) {
                    echo '<td>' . $value . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '<br>';
        }

        // Most Requested Files and IPs
        $most_requested_info = get_most_requested_info();
        foreach ($most_requested_info->sections as $section) {
            //echo '<h4>'.$section->name.'</h4>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            foreach (array_keys((array) $section->data[0]) as $header) {
                echo '<th>' . $header . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($section->data as $row) {
                echo '<tr>';
                foreach ((array) $row as $key => $value) {
                    echo '<td>' . $value . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '<br>';
        }

        // Most Requested Pages
        $most_requested_pages_info = get_most_requested_pages_info();
        foreach ($most_requested_pages_info->sections as $section) {
            //echo '<h4>'.$section->name.'</h4>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            foreach (array_keys((array) $section->data[0]) as $header) {
                echo '<th>' . $header . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($section->data as $row) {
                echo '<tr>';
                foreach ((array) $row as $key => $value) {
                    echo '<td>' . $value . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '<br>';
        }

        // Known Bots Requesting the Site
        $known_bots_info = get_known_bots_info();
        foreach ($known_bots_info->sections as $section) {
            //echo '<h4>'.$section->name.'</h4>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            foreach (array_keys((array) $section->data[0]) as $header) {
                echo '<th>' . $header . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($section->data as $row) {
                echo '<tr>';
                foreach ((array) $row as $key => $value) {
                    echo '<td>' . $value . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '<br>';
        }

        echo '</div>';
        echo '<button class="copy-btn" data-target="#accesslog-details-copy-content">Copy to Clipboard</button>';
    }

    echo '</div>';

    // DP Cache Details
    if (is_dreampress_server()) {
        echo '<h3 class="health-check-accordion-heading"><button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-cache" type="button"><span class="title">DreamPress Cache Details</span><span class="icon"></span></button></h3>';
        echo '<div id="health-check-accordion-block-cache" class="health-check-accordion-panel" hidden>';

        echo '<div class="copy-content" id="cache-details-copy-content">';
        $cache_ratios_info = get_nginx_cache_ratios_info();
        foreach ($cache_ratios_info->sections as $section) {
            //echo '<h4>' . $section->name . '</h4>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            foreach ($section->headers as $header) {
                echo '<th>' . $header . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($section->data as $data) {
                echo '<tr>';
                foreach ($data as $value) {
                    echo '<td>' . $value . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '<br>';
        }

        echo '</div>';
        echo '<button class="copy-btn" data-target="#cache-details-copy-content">Copy to Clipboard</button>';
        echo '</div>';
    }

    // cURL Results Section
    echo '<h3 class="health-check-accordion-heading"><button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-curl-results" type="button"><span class="title">cURL Results</span><span class="icon"></span></button></h3>';
    echo '<div id="health-check-accordion-block-curl-results" class="health-check-accordion-panel" hidden>';

    echo '<div class="copy-content" id="curl-results-copy-content">';
    $curl_results_info = get_curl_results_info();
    foreach ($curl_results_info->sections as $section) {
        //echo '<h4>'.esc_html($section->name).'</h4>';
        echo '<table class="wp-list-table widefat fixed">';
        echo '<tbody>';
        foreach ($section->data as $row) {
            foreach ($row as $value) {
                echo '<tr><td>' . esc_html($value) . '</td></tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';
        echo '<br>';
    }

    echo '</div>';
    echo '<button class="copy-btn" data-target="#curl-results-copy-content">Copy to Clipboard</button>';
    echo '</div>';

    // Cronjobs Scheduled Events
    echo '<h3 class="health-check-accordion-heading"><button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-cronjobs" type="button"><span class="title">Cronjobs Scheduled Events</span><span class="icon"></span></button></h3>';
    echo '<div id="health-check-accordion-block-cronjobs" class="health-check-accordion-panel" hidden>';

    echo '<div class="copy-content" id="cronjobs-copy-content">';

    $cronjobs_info = get_cron_events_info();
    foreach ($cronjobs_info->sections as $section) {
        echo '<div class="cronjob-section">';
        echo '<div class="cronjob-header"><h1>' . esc_html($section->name) . '</h1></div>';
        echo '<div class="cronjob-table-container">';
        echo '<table class="widefat">';
        echo '<thead><tr>';
        foreach (array_keys((array) $section->data[0]) as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($section->data as $data) {
            echo '<tr>';
            foreach ($data as $key => $value) {
                echo '<td>' . esc_html($value) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
    }

    echo '</div>';
    echo '<br>';
    echo '<button class="copy-btn" data-target="#cronjobs-copy-content">Copy to Clipboard</button>';
    echo '</div>';

    // Error Logs Section
    echo '<h3 class="health-check-accordion-heading"><button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-error-logs" type="button"><span class="title">Last 50 lines of Error Logs</span><span class="icon"></span></button></h3>';
    echo '<div id="health-check-accordion-block-error-logs" class="health-check-accordion-panel" hidden>';

    echo '<div class="copy-content" id="error-logs-copy-content">';
    $error_logs_info = get_last_50_lines_of_error_logs();
    foreach ($error_logs_info->sections as $section) {
        echo '<div class="error-log-section">';
        echo '<div class="error-log-header"><h1>' . esc_html($section->name) . '</h1></div>';
        echo '<div class="error-log-table-container">';
        echo '<textarea readonly class="error-log-textarea">' . esc_html($section->data) . '</textarea>';
        echo '</div>';
        echo '</div>';
        echo '<br>';
    }

    echo '</div>';
    echo '<button class="copy-btn" data-target="#error-logs-copy-content">Copy to Clipboard</button>';
    echo '</div>';

    // Add the CSS for the .error-log-section
    echo '<style>
        .error-log-section {
            width: 95%;
            margin: 0 auto;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
            max-height: 400px;
            overflow-y: hidden;
            display: flex;
            flex-direction: column;
        }
        .error-log-header {
            padding: 10px;
            background-color: #e0e0e0;
        }
        .error-log-table-container {
            overflow-y: auto;
        }
        .widefat thead tr th {
            position: sticky;
            top: 0;
            background-color: #e0e0e0;
        }
        .error-log-textarea {
            width: 100%;
            height: 600px; /* Adjusted height */
            overflow: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: monospace;
        }
    </style>';

    // Lighthouse API Section
    echo '<h3 class="health-check-accordion-heading">';
    echo '<button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-lighthouse" type="button">';
    echo '<span class="title">Google PageSpeed Insights</span><span class="icon"></span>';
    echo '</button></h3>';
    echo '<div id="health-check-accordion-block-lighthouse" class="health-check-accordion-panel" hidden>';
    wpqr_site_health_lighthouse_button();
    echo '</div>';

}
add_action('site_health_tab_content', 'wpqr_site_health_tab_content');

// Display notice under the plugin entry
function pad_plugin_row_notice($plugin_file, $plugin_data, $status)
{
    if (!is_plugin_active('performance-and-diagnostics/performance-and-diagnostics.php')) {
        return;
    }

    $api_key_notice = empty(get_option('pad_api_key'));
    $log_paths_notice = !are_log_paths_set();

    // Display notice under the plugin entry
    if ($api_key_notice || $log_paths_notice) {
        echo '<tr class="plugin-update-tr active" id="performance-and-diagnostics">
            <td colspan="3" class="plugin-update colspanchange">
                <div class="notice inline notice-warning notice-alt">';
        if ($log_paths_notice) {
            echo '<p>' . __('Please set the log paths in the <a href="' . admin_url('options-general.php?page=pad-settings') . '">Performance & Diagnostics settings</a> before proceeding.', 'textdomain') . '</p>';
        }
        if ($api_key_notice) {
            echo '<p>' . __('Please set your Google LightHouse API key in the <a href="' . admin_url('options-general.php?page=pad-settings') . '">Performance & Diagnostics settings</a> before proceeding.', 'textdomain') . '</p>';
        }
        echo '</div>
            </td>
        </tr>';
    }
}
add_action('after_plugin_row_performance-and-diagnostics/performance-and-diagnostics.php', 'pad_plugin_row_notice', 10, 3);

// Display notice at the top of the Plugins page
function pad_plugins_page_notice()
{
    if (!is_plugin_active('performance-and-diagnostics/performance-and-diagnostics.php')) {
        return;
    }

    $api_key_notice = empty(get_option('pad_api_key'));
    $log_paths_notice = !are_log_paths_set();

    if ($api_key_notice || $log_paths_notice) {
        echo '<div class="notice notice-warning">';
        if ($log_paths_notice) {
            echo '<p>' . __('Please set the log paths in the <a href="' . admin_url('options-general.php?page=pad-settings') . '">Performance & Diagnostics settings</a> before proceeding.', 'textdomain') . '</p>';
        }
        if ($api_key_notice) {
            echo '<p>' . __('Please set your Google LightHouse API key in the <a href="' . admin_url('options-general.php?page=pad-settings') . '">Performance & Diagnostics settings</a> before proceeding.', 'textdomain') . '</p>';
        }
        echo '</div>';
    }
}
add_action('admin_notices', function () {
    if (strpos($_SERVER['REQUEST_URI'], 'plugins.php') !== false) {
        pad_plugins_page_notice();
    }
});

?>
