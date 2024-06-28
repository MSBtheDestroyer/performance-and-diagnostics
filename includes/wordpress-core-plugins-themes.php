<?php
/**
 * WordPress Core, Plugins, and Themes Info
 */

// Function to get WordPress core update info
function get_wordpress_core_update_info() {
    require_once( ABSPATH . 'wp-admin/includes/update.php' );
    $updates = get_core_updates();
    
    if (isset($updates[0]->response) && $updates[0]->response == 'latest') {
        return (object)[
            'message' => 'WordPress is at the latest version!'
        ];
    } else {
        return array_map(function($update) {
            return (object)[
                'Current Version' => $update->current,
                'Update Status' => $update->response
            ];
        }, $updates);
    }
}

// Function to get theme details
function get_theme_details() {
    $themes = wp_get_themes();
    $active_theme = wp_get_theme();
    $theme_updates = get_theme_updates();

    $theme_details = [];

    foreach ($themes as $theme_slug => $theme) {
        if (($theme->get('Status') === 'parent' || $theme_slug === $active_theme->get_stylesheet()) && isset($theme_updates[$theme_slug])) {
            $theme_details[] = (object)[
                'Status' => $theme_slug === $active_theme->get_stylesheet() ? 'Active' : 'Parent',
                'Name' => $theme->get('Name'),
                'Version' => $theme->get('Version'),
                'Update' => 'Available',
                'Update Version' => $theme_updates[$theme_slug]['new_version']
            ];
        }
    }

    return $theme_details;
}

// Function to get active plugins with updates
function get_active_plugins_update_info() {
    $all_plugins = get_plugins();
    $active_plugins = get_option('active_plugins');
    $plugin_updates = get_plugin_updates();

    $active_plugins_update_info = [];

    foreach ($all_plugins as $plugin_path => $plugin_data) {
        if (in_array($plugin_path, $active_plugins) && isset($plugin_updates[$plugin_path])) {
            $active_plugins_update_info[] = (object)[
                'Status' => 'Active',
                'Name' => $plugin_data['Name'],
                'Version' => $plugin_data['Version'],
                'Update' => 'Available',
                'Update Version' => $plugin_updates[$plugin_path]->update->new_version
            ];
        }
    }

    return $active_plugins_update_info;
}

// Main function to get WordPress core, plugins, and themes info
function get_wordpress_core_plugins_themes_info() {
    $core_updates = get_wordpress_core_update_info();
    $theme_updates = get_theme_details();
    $plugin_updates = get_active_plugins_update_info();

    $sections = [];

    if (is_object($core_updates) && isset($core_updates->message)) {
        $sections[] = (object)[
            'name' => 'Core Updates',
            'data' => [(object)['Message' => $core_updates->message]]
        ];
    } else {
        $sections[] = (object)[
            'name' => 'Core Updates',
            'data' => $core_updates
        ];
    }

    if (empty($theme_updates)) {
        $sections[] = (object)[
            'name' => 'Updates For Active Theme',
            'data' => [(object)['Message' => 'Active theme is up to date! Good work!']]
        ];
    } else {
        $sections[] = (object)[
            'name' => 'Updates For Active Theme',
            'data' => $theme_updates
        ];
    }

    if (empty($plugin_updates)) {
        $sections[] = (object)[
            'name' => 'Updates For Active Plugins',
            'data' => [(object)['Message' => 'Active plugins are up to date! Good work!']]
        ];
    } else {
        $sections[] = (object)[
            'name' => 'Updates For Active Plugins',
            'data' => $plugin_updates
        ];
    }

    return (object)[
        'sections' => $sections
    ];
}
?>
