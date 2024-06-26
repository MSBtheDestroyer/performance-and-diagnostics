<?php

require_once('initialize.php');

// Function to get the last 50 lines of error logs
function get_last_50_lines_of_error_logs() {
    $init = initialize_user_and_domain();
    $log_path = $init['log_paths']['error_log'];

    $log_content = '';

    if (file_exists($log_path)) {
        // Open the file and read the last 50 lines
        $file_content = file($log_path, FILE_IGNORE_NEW_LINES);
        $last_50_lines = array_slice($file_content, -50);

        // Add an extra newline only at the end of each log entry
        $log_entries = [];
        $current_entry = '';
        foreach ($last_50_lines as $line) {
            if (preg_match('/^\d{4}\/\d{2}\/\d{2}/', $line)) {
                if ($current_entry !== '') {
                    $log_entries[] = $current_entry;
                    $current_entry = '';
                }
            }
            $current_entry .= $line . "\n";
        }
        if ($current_entry !== '') {
            $log_entries[] = $current_entry; // Add the last entry if it exists
        }

        $log_content = implode("\n", $log_entries); // Add double newline between entries
    }

    return (object) [
        'description' => 'Last 50 lines of Error Logs',
        'sections' => [
            (object) [
                'name' => 'Error Logs',
                'data' => empty($log_content) ? 'No errors found in the log file.' : $log_content
            ]
        ]
    ];
}
