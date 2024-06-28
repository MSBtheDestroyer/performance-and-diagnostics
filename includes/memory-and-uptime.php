<?php
/**
 * Memory and Uptime Check Functions
 */

// Function to get server uptime
function get_server_uptime() {
    if (file_exists('/proc/uptime') && file_exists('/proc/loadavg')) {
        $uptime_data = file_get_contents('/proc/uptime');
        $load_data = file_get_contents('/proc/loadavg');
        
        $uptime_data = explode(' ', $uptime_data);
        $uptime_seconds = floatval($uptime_data[0]);
        
        $days = floor($uptime_seconds / 86400);
        $hours = floor(($uptime_seconds % 86400) / 3600);
        $minutes = floor(($uptime_seconds % 3600) / 60);
        
        $current_time = date('H:i:s');

        $load_avg = explode(' ', $load_data);

        $uptime_string = sprintf(
            '%s up %d days, %02d:%02d,  load average: %s, %s, %s',
            $current_time,
            $days,
            $hours,
            $minutes,
            $load_avg[0],
            $load_avg[1],
            $load_avg[2]
        );

        return $uptime_string;
    } else {
        return "Uptime information is not available.";
    }
}

// Function to get memory usage
function get_memory_usage() {
    $meminfo = file_get_contents('/proc/meminfo');
    $data = [];
    if ($meminfo) {
        foreach (explode("\n", $meminfo) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s+kB$/', $line, $matches)) {
                $data[$matches[1]] = $matches[2];
            }
        }
    }

    $total_mb = isset($data['MemTotal']) ? $data['MemTotal'] / 1024 : 0;
    $free_mb = isset($data['MemFree']) ? $data['MemFree'] / 1024 : 0;
    $available_mb = isset($data['MemAvailable']) ? $data['MemAvailable'] / 1024 : 0;
    $used_mb = $total_mb - $available_mb;
    $percentage_used = $total_mb > 0 ? ($used_mb / $total_mb) * 100 : 0;

    return (object) [
        'total_mb' => $total_mb,
        'used_mb' => $used_mb,
        'percentage_used' => $percentage_used
    ];
}

// Main function to get Memory and Uptime data
function get_memory_and_uptime() {
    $uptime = get_server_uptime();
    $memory = get_memory_usage();

    $memory_and_uptime = (object) [
        'sections' => []
    ];

    // Uptime
    $memory_and_uptime->sections[] = [
        'data' => [
            ['Uptime' => $uptime]
        ]
    ];

    // Memory Usage
    $memory_and_uptime->sections[] = [
        'data' => [
            [
                'Total MB' => number_format($memory->total_mb, 0),
                'Used MB' => number_format($memory->used_mb, 0),
                '% Memory Used' => number_format($memory->percentage_used, 2) . '%'
            ]
        ]
    ];

    return $memory_and_uptime;
}
?>
