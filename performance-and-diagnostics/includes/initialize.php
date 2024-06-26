<?php
/**
 * Initialize user and domain.
 *
 * @return array
 */
function initialize_user_and_domain() {
    $home_path = get_home_path();
    $path_parts = explode('/', trim($home_path, '/'));
    $user = isset($path_parts[1]) ? $path_parts[1] : 'defaultUser';
    $domain = isset($path_parts[2]) ? $path_parts[2] : 'defaultDomain.com';

    // Get custom log paths from settings
    $log_paths = get_option('pad_log_paths', array(
        'today_access_log' => "/home/{$user}/logs/{$domain}/access.log",
        'yesterday_access_log' => "/home/{$user}/logs/{$domain}/access.log.1",
        'error_log' => "/home/{$user}/logs/{$domain}/error.log",
    ));

    return [
        'user' => $user,
        'domain' => $domain,
        'log_paths' => $log_paths,
    ];
}
