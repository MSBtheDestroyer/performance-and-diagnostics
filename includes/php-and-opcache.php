<?php
/**
 * PHP and OPcache Information
 */

// Function to get PHP info
function get_php_info() {
    $php_version = phpversion();
    $php_memory_limit = ini_get('memory_limit');
    $wp_memory_limit = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'Not defined';
    $wp_max_memory_limit = defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : 'Not defined';

    return (object) [
        'php_version' => $php_version,
        'php_memory_limit' => $php_memory_limit,
        'wp_memory_limit' => $wp_memory_limit,
        'wp_max_memory_limit' => $wp_max_memory_limit
    ];
}

// Function to get OPcache info
function get_opcache_info() {
    if (function_exists('opcache_get_status')) {
        $opcache_status = opcache_get_status();

        return (object) [
            'opcache_active_since' => date('Y-m-d H:i:s', $opcache_status['opcache_statistics']['start_time']),
            'opcache_status' => $opcache_status['opcache_enabled'] ? 'Up and Running' : 'Not Running',
            'opcache_oom_restarts' => number_format($opcache_status['opcache_statistics']['oom_restarts']),
            'opcache_memory_limit' => round(ini_get('opcache.memory_consumption')) . ' MB',
            'opcache_used_memory' => round($opcache_status['memory_usage']['used_memory'] / 1048576) . ' MB',
            'opcache_free_memory' => round($opcache_status['memory_usage']['free_memory'] / 1048576) . ' MB',
            'opcache_wasted_memory' => round($opcache_status['memory_usage']['wasted_memory'] / 1048576) . ' MB',
            'opcache_cache_hits' => number_format($opcache_status['opcache_statistics']['hits']),
            'opcache_cache_misses' => number_format($opcache_status['opcache_statistics']['misses'])
        ];
    } else {
        return (object) [
            'opcache_active_since' => 'N/A',
            'opcache_status' => 'N/A',
            'opcache_oom_restarts' => 'N/A',
            'opcache_memory_limit' => 'N/A',
            'opcache_used_memory' => 'N/A',
            'opcache_free_memory' => 'N/A',
            'opcache_wasted_memory' => 'N/A',
            'opcache_cache_hits' => 'N/A',
            'opcache_cache_misses' => 'N/A'
        ];
    }
}

// Main function to get PHP and OPcache info
function get_php_and_opcache_info() {
    return (object) [
        'sections' => [
            (object) [
                'data' => [
                    'PHP Version' => get_php_info()->php_version,
                    'PHP Memory Limit' => get_php_info()->php_memory_limit,
                    'WP Memory Limit' => get_php_info()->wp_memory_limit,
                    'WP Max Memory Limit' => get_php_info()->wp_max_memory_limit
                ]
            ],
            (object) [
                'data' => [
                    'Opcache Session Active Since' => get_opcache_info()->opcache_active_since,
                    'Opcode Caching Status' => get_opcache_info()->opcache_status,
                    'OOM Restarts' => get_opcache_info()->opcache_oom_restarts,
                    'Memory Limit' => get_opcache_info()->opcache_memory_limit,
                    'Used Memory' => get_opcache_info()->opcache_used_memory,
                    'Free Memory' => get_opcache_info()->opcache_free_memory,
                    'Wasted Memory' => get_opcache_info()->opcache_wasted_memory,
                    'Cache Hits' => get_opcache_info()->opcache_cache_hits,
                    'Cache Misses' => get_opcache_info()->opcache_cache_misses
                ]
            ]
        ]
    ];
}


?>
