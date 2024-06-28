<?php

require_once('initialize.php'); 

// Function to get HTTP requests info
function get_http_requests_info() {
    $init = initialize_user_and_domain();

    $access_logs = [$init['log_paths']['today_access_log'], $init['log_paths']['yesterday_access_log']];

    $today_date = date("d/M/Y");
    $yesterday_date = date("d/M/Y", strtotime("-1 day"));

    $today_requests = get_http_requests_count($today_date, $access_logs);
    $yesterday_requests = get_http_requests_count($yesterday_date, $access_logs);

    return (object) [
        'sections' => [
            (object) [
                'name' => 'Total HTTP Requests',
                'data' => [
                    (object) [
                        'Date' => date("Y-m-d") . ' (Today)',
                        'Number of Requests' => $today_requests
                    ],
                    (object) [
                        'Date' => date("Y-m-d", strtotime("-1 day")) . ' (Yesterday)',
                        'Number of Requests' => $yesterday_requests
                    ]
                ]
            ]
        ]
    ];
}

// Function to get HTTP requests count
function get_http_requests_count($date, $log_files) {
    $count = 0;
    foreach ($log_files as $log_file) {
        if (file_exists($log_file)) {
            $file = fopen($log_file, 'r');
            if ($file) {
                while (($line = fgets($file)) !== false) {
                    if (strpos($line, $date) !== false) {
                        $count++;
                    }
                }
                fclose($file);
            }
        }
    }
    return $count;
}

// Function to get most requested files and IPs
function get_most_requested_info() {
    $init = initialize_user_and_domain();
    $file_paths = glob($init['log_paths']['today_access_log']);

    $request_count = [];
    $ip_count = [];

    foreach ($file_paths as $file_path) {
        $file_handle = fopen($file_path, 'r');
        if ($file_handle) {
            while (($line = fgets($file_handle)) !== false) {
                // Parse the line for requested files
                if (preg_match('/"[^"]*"/', $line, $match)) {
                    $parts = explode(' ', trim($match[0], '"'));
                    if (isset($parts[1])) {
                        $path = explode('?', $parts[1])[0];
                        if (preg_match('/\.(html|htm|php|asp|aspx|jpg|jpeg|png|gif|svg|ico|css|js|pdf|doc|docx|ppt|pptx|zip|rar|tar|gz)$/i', $path)) {
                            if (!isset($request_count[$path])) {
                                $request_count[$path] = 0;
                            }
                            $request_count[$path]++;
                        }
                    }
                }

                // Parse the line for IP addresses
                if (preg_match('/^\S+/', $line, $ip_match)) {
                    $ip = $ip_match[0];
                    if (!isset($ip_count[$ip])) {
                        $ip_count[$ip] = 0;
                    }
                    $ip_count[$ip]++;
                }
            }
            fclose($file_handle);
        }
    }

    arsort($request_count);
    arsort($ip_count);
    $top_requested_files = array_slice($request_count, 0, 5, true);
    $top_ips = array_slice($ip_count, 0, 5, true);

    return (object) [
        'description' => 'Most Requested Files and IPs',
        'sections' => [
            (object) [
                'name' => 'Most Requested Files',
                'data' => array_map(function($file, $count) {
                    return (object) [
                        'Count' => $count,
                        'File' => $file
                    ];
                }, array_keys($top_requested_files), $top_requested_files)
            ],
            (object) [
                'name' => 'Repeat IP Requests',
                'data' => array_map(function($ip, $count) {
                    return (object) [
                        'Count' => $count,
                        'IP Address' => $ip
                    ];
                }, array_keys($top_ips), $top_ips)
            ]
        ]
    ];
}

// Function to get most requested pages
function get_most_requested_pages_info() {
    $init = initialize_user_and_domain();
    $file_paths = glob($init['log_paths']['today_access_log']);
    $page_count = [];

    foreach ($file_paths as $file_path) {
        $file_handle = fopen($file_path, 'r');
        if ($file_handle) {
            while (($line = fgets($file_handle)) !== false) {
                if (preg_match('/"[^"]*"/', $line, $match)) {
                    $parts = explode(' ', trim($match[0], '"'));
                    if (isset($parts[1])) {
                        $page = explode('?', $parts[1])[0];
                        if (!preg_match('/\.(html|htm|php|asp|aspx|jpg|jpeg|png|gif|svg|ico|css|js|pdf|doc|docx|ppt|pptx|zip|rar|tar|gz)$/', $page) &&
                            !preg_match('/wp-|wp-content|feed/', $page) &&
                            !preg_match('/^\?.*/', $page)) {
                            if (!isset($page_count[$page])) {
                                $page_count[$page] = 0;
                            }
                            $page_count[$page]++;
                        }
                    }
                }
            }
            fclose($file_handle);
        }
    }

    arsort($page_count);
    $top_requested_pages = array_slice($page_count, 0, 5, true);

    return (object) [
        'description' => 'Most Requested Pages',
        'sections' => [
            (object) [
                'name' => 'Top Requested Pages',
                'data' => array_map(function($page, $count) {
                    return (object) [
                        'Count' => $count,
                        'Page' => $page
                    ];
                }, array_keys($top_requested_pages), $top_requested_pages)
            ]
        ]
    ];
}

function get_known_bots_info() {
    $init = initialize_user_and_domain();
    $access_logs = glob($init['log_paths']['today_access_log']); // Modify this if you need to check more logs

    $compatible_bots = [];
    $other_bots = [];

    foreach ($access_logs as $log_file) {
        if (file_exists($log_file)) {
            $file = fopen($log_file, 'r');
            if ($file) {
                while (($line = fgets($file)) !== false) {
                    // Extract the user agent part of the log line
                    if (preg_match('/"[^"]*" "([^"]*)"/', $line, $matches)) {
                        $user_agent = $matches[1];

                        // Check for 'compatible' bots
                        if (strpos($user_agent, 'compatible') !== false) {
                            if (preg_match('/compatible;([^;]*)/', $user_agent, $matches)) {
                                $bot_name = trim(explode('/', $matches[1])[0]);
                                if (!isset($compatible_bots[$bot_name])) {
                                    $compatible_bots[$bot_name] = 0;
                                }
                                $compatible_bots[$bot_name]++;
                            }
                        } else {
                            // Check for other bots
                            if (preg_match('/bot|crawl|spider|slurp|archiver|seek|extract|search|tracker|find|survey/i', $user_agent)) {
                                $agent_cleaned = preg_replace('/Mozilla\/[^\s]+|AppleWebKit\/[^\s]+|Chrome\/[^\s]+|Safari\/[^\s]+|Version\/[^\s]+|Mobile\/[^\s]+|Gecko\/[^\s]+|Firefox\/[^\s]+|Edg\/[^\s]+|SamsungBrowser\/[^\s]+|CriOS\/[^\s]+|GSA\/[^\s]+/', '', $user_agent);
                                $agent_cleaned = trim($agent_cleaned);
                                if (!isset($other_bots[$agent_cleaned])) {
                                    $other_bots[$agent_cleaned] = 0;
                                }
                                $other_bots[$agent_cleaned]++;
                            }
                        }
                    }
                }
                fclose($file);
            }
        }
    }

    // Limit to top 10 bots
    arsort($compatible_bots);
    arsort($other_bots);
    $compatible_bots = array_slice($compatible_bots, 0, 10, true);
    $other_bots = array_slice($other_bots, 0, 10, true);

    if (empty($compatible_bots)) {
        $compatible_bots = ["None found" => 0];
    }

    if (empty($other_bots)) {
        $other_bots = ["None found" => 0];
    }

    return (object) [
        'description' => 'Known Bots Requesting the Site',
        'sections' => [
            (object) [
                'name' => 'Compatible Bots',
                'data' => array_map(function($bot, $count) {
                    return (object) [
                        'Bot' => $bot,
                        'Count' => $count
                    ];
                }, array_keys($compatible_bots), $compatible_bots)
            ],
            (object) [
                'name' => 'Other Bots',
                'data' => array_map(function($bot, $count) {
                    return (object) [
                        'Bot' => $bot,
                        'Count' => $count
                    ];
                }, array_keys($other_bots), $other_bots)
            ]
        ]
    ];
}



?>
