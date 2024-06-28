<?php
/**
 * Database Details
 */

// Function to get autoload options size
function get_autoload_options_size() {
    global $wpdb;
    $query = "SELECT SUM(LENGTH(option_value)) as autoload_size FROM {$wpdb->options} WHERE autoload = 'yes'";
    $result = $wpdb->get_row($query);
    $autoload_size_kb = $result->autoload_size / 1024;

    return $autoload_size_kb;
}

// Function to get database details
function get_database_details() {
    global $wpdb;

    // Get autoload options size
    $autoload_size_kb = get_autoload_options_size();

    // Get top 10 autoloaded options by size
    $top_autoloaded_options = $wpdb->get_results("
        SELECT option_name, ROUND(LENGTH(option_value) / 1024, 2) AS size_kb
        FROM {$wpdb->options}
        WHERE autoload = 'yes'
        ORDER BY LENGTH(option_value) DESC
        LIMIT 10
    ");

    // List of main WordPress tables
    $main_tables = [
        'commentmeta',
        'comments',
        'links',
        'options',
        'postmeta',
        'posts',
        'term_relationships',
        'term_taxonomy',
        'termmeta',
        'terms',
        'usermeta',
        'users'
    ];

    $table_sizes = [];

    // Query to get the size of the main tables
    foreach ($main_tables as $table) {
        $full_table_name = $wpdb->prefix . $table;
        $size = $wpdb->get_var($wpdb->prepare(
            "SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1048576, 2) AS 'size' 
             FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s",
            DB_NAME,
            $full_table_name
        ));
        $table_sizes[$full_table_name] = $size ? $size : 0;
    }

    // Query to get the size of all tables larger than 10MB
    $all_tables = $wpdb->get_results("
        SELECT TABLE_NAME AS table_name, 
               ROUND((DATA_LENGTH + INDEX_LENGTH) / 1048576, 2) AS size 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = '" . DB_NAME . "'
        HAVING size > 10
    ");

    // Determine status and message based on size threshold
    $status = $autoload_size_kb < 900 ? 'success' : 'warning';
    $message = "Autoloaded options size (" . number_format($autoload_size_kb, 2) . " KB) is ";
    $message .= $status === 'success' ? "less than" : "greater than or equal to";
    $message .= " threshold (900 KB).";

    // If no tables larger than 10MB, add a message
    $large_tables_data = empty($all_tables) ? 
        [(object) ['Tables Larger Than 10MB' => 'No tables larger than 10MB']] : 
        array_map(function($table) {
            return (object) [
                'Table Name' => $table->table_name,
                'Size (MB)' => $table->size
            ];
        }, $all_tables);

    return (object) [
        'sections' => [
            (object) [
                'data' => [
                    (object) [
                        'Status Check' => $status,
                        'Autoloaded Options' => $message
                    ]
                ]
            ],
            (object) [
                'data' => array_map(function($option) {
                    return (object) [
                        'Top 10 Autoloaded Options' => $option->option_name,
                        'Size (KB)' => $option->size_kb
                    ];
                }, $top_autoloaded_options)
            ],
            (object) [
                'data' => array_map(function($name, $size) {
                    return (object) [
                        'Main WordPress Table' => $name,
                        'Size (MB)' => $size
                    ];
                }, array_keys($table_sizes), $table_sizes)
            ],
            (object) [
                'data' => $large_tables_data
            ]
        ]
    ];
}


?>
