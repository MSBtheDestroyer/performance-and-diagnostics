<?php
/**
 * Initialize user and domain.
 *
 * @return array
 */
function initialize_user_and_domain()
{
    $home_path = get_home_path();
    $path_parts = explode('/', trim($home_path, '/'));
    $user = isset($path_parts[1]) ? $path_parts[1] : 'defaultUser';
    $domain = isset($path_parts[2]) ? $path_parts[2] : 'defaultDomain.com';

    // Get custom log paths from settings
    $log_paths = get_option('pad_log_paths', array());

    // Ensure log paths are set by the user
    if (empty($log_paths['today_access_log']) || empty($log_paths['yesterday_access_log']) || empty($log_paths['error_log'])) {
        return [
            'user' => $user,
            'domain' => $domain,
            'log_paths' => null, // Indicate missing log paths
        ];
    }

    return [
        'user' => $user,
        'domain' => $domain,
        'log_paths' => $log_paths,
    ];
}
