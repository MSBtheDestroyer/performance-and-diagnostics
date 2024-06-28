<?php

// Function to get cron events info
function get_cron_events_info() {
    $cron_events = _get_cron_array();

    $events_data = [];

    foreach ($cron_events as $timestamp => $events) {
        if (!is_numeric($timestamp)) {
            continue; // Skip this iteration if the timestamp is not numeric
        }

        foreach ($events as $hook => $event) {
            foreach ($event as $instance) {
                $events_data[] = (object) [
                    'Hook' => $hook,
                    'Next Run GMT' => gmdate('Y-m-d H:i:s', intval($timestamp)),
                    'Next Run Relative' => human_time_diff(current_time('timestamp', 1), $timestamp),
                    'Recurrence' => $instance['schedule'] ? $instance['schedule'] : 'Non-repeating'
                ];
            }
        }
    }

    return (object) [
        'description' => 'Cronjobs Scheduled Events',
        'sections' => [
            (object) [
                'name' => 'Scheduled Events',
                'data' => $events_data
            ]
        ]
    ];
}

// Add the CSS for the .cronjob-section here (if not already added)
function add_cronjob_events_css() {
    echo '<style>';
    echo '.cronjob-section {';
    echo '    width: 95%;';
    echo '    margin: 0 auto;';
    echo '    background-color: #f8f9fa;';
    echo '    border: 1px solid #e0e0e0;';
    echo '    box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);';
    echo '    max-height: 400px;';
    echo '    overflow-y: hidden;';
    echo '    display: flex;';
    echo '    flex-direction: column;';
    echo '}';
    echo '.cronjob-header {';
    echo '    padding: 10px;';
    echo '    background-color: #e0e0e0;';
    echo '}';
    echo '.cronjob-table-container {';
    echo '    overflow-y: auto;';
    echo '}';
    echo '.widefat thead tr th {';
    echo '    position: sticky;';
    echo '    top: 0;';
    echo '    background-color: #e0e0e0;';
    echo '}';
    echo '</style>';
}
add_action('admin_head', 'add_cronjob_events_css');
