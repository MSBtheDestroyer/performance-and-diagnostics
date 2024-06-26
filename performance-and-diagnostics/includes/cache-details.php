<?php

require_once('initialize.php');

// Function to get Nginx cache ratios
function get_nginx_cache_ratios_info() {
    $init = initialize_user_and_domain();
    $log_directory = '/home/' . $init['user'] . '/logs/' . $init['domain'];
    $file_paths = glob($log_directory . '/access.log*');
    $bypass = $hit = $miss = $feed_hits = $feed_purges = 0;

    foreach ($file_paths as $file_path) {
        $file_handle = fopen($file_path, 'r');
        if ($file_handle) {
            while (($line = fgets($file_handle)) !== false) {
                // Count cache statuses
                if (strpos($line, 'BYPASS') !== false) {
                    $bypass++;
                }
                if (strpos($line, 'HIT') !== false) {
                    $hit++;
                }
                if (strpos($line, 'MISS') !== false) {
                    $miss++;
                }
                // Count feed hits and purges
                if (strpos($line, '/feed') !== false) {
                    $feed_hits++;
                    if (strpos($line, 'purge') !== false) {
                        $feed_purges++;
                    }
                }
            }
            fclose($file_handle);
        }
    }

    $total = $hit + $miss + $bypass;
    $total_cache_engaged = $hit + $miss;
    $cache_hit_ratio_including_bypasses = $total > 0 ? sprintf("%0.2f%%", ($hit / $total) * 100) : 'N/A';
    $cache_hit_ratio_excluding_bypasses = $total_cache_engaged > 0 ? sprintf("%0.2f%%", ($hit / $total_cache_engaged) * 100) : 'N/A';

    return (object) [
        'description' => 'Nginx Cache Ratios',
        'sections' => [
            (object) [
                'name' => 'Cache Status Counts',
                'headers' => ['Cache Status', 'Count'],
                'data' => [
                    (object) ['BYPASS', $bypass],
                    (object) ['HIT', $hit],
                    (object) ['MISS', $miss]
                ]
            ],
            (object) [
                'name' => 'Cache Hit Ratios',
                'headers' => ['Ratio Type', 'Value'],
                'data' => [
                    (object) ['Cache Hit Ratio (Including Bypasses)', $cache_hit_ratio_including_bypasses],
                    (object) ['Cache Hit Ratio (Excluding Bypasses)', $cache_hit_ratio_excluding_bypasses]
                ]
            ],
            (object) [
                'name' => 'Feed Hits and Purges',
                'headers' => ['Type', 'Count'],
                'data' => [
                    (object) ['Number of Feed Hits in Access Log', $feed_hits],
                    (object) ['Number of Feeds Getting Purged in Access Log', $feed_purges]
                ]
            ]
        ]
    ];
}

// Function to get cURL results info
function get_curl_results_info() {
    $current_directory = getcwd();
    $url = 'http://' . explode('/', $current_directory)[3];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0
    ]);

    curl_exec($curl); // Ignore the result of the first run
    $header = curl_exec($curl);
    curl_close($curl);

    $headers = array_filter(explode("\n", $header));

    return (object) [
        'description' => 'cURL Results',
        'sections' => [
            (object) [
                'name' => 'cURL Result Headers',
                'data' => array_map(function($row) {
                    return (object) ['Result' => trim($row)];
                }, $headers)
            ]
        ]
    ];
}

?>
