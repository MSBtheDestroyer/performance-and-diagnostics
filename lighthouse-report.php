<?php
// Include the WordPress environment
require_once(dirname(__FILE__) . '/../../../wp-load.php');

// Get the site URL
$url = get_site_url();

function display_lighthouse_report() {
    $api = new My_Plugin_API();
    $details = $api->get_page_speed_details(get_site_url()); // Use the site's URL

    echo '<div class="container">';
    echo '<h1>Google PageSpeed Insights Report</h1>';

    if ($details) {
        echo '<h2>Page Speed Details (Home Page)</h2>';
        echo '<div class="copy-content" id="lighthouse-report-content">';
        echo '<table>';
        echo '<thead><tr><th>Metric</th><th>Value</th><th>Description</th></tr></thead>';
        echo '<tbody>';
        echo '<tr><td>TTFB</td><td>' . esc_html($details['ttfb']['value']) . '</td><td><a href="' . esc_url($details['ttfb']['description']) . '" target="_blank">' . esc_html($details['ttfb']['description']) . '</a></td></tr>';
        echo '<tr><td>Full Load Time</td><td>' . esc_html($details['full_load_time']['value']) . '</td><td><a href="' . esc_url($details['full_load_time']['description']) . '" target="_blank">' . esc_html($details['full_load_time']['description']) . '</a></td></tr>';
        echo '<tr><td>FCP</td><td>' . esc_html($details['fcp']['value']) . '</td><td><a href="' . esc_url($details['fcp']['description']) . '" target="_blank">' . esc_html($details['fcp']['description']) . '</a></td></tr>';
        echo '<tr><td>LCP</td><td>' . esc_html($details['lcp']['value']) . '</td><td><a href="' . esc_url($details['lcp']['description']) . '" target="_blank">' . esc_html($details['lcp']['description']) . '</a></td></tr>';
        echo '<tr><td>Properly size images</td><td>' . esc_html($details['properly_size_images']['value']) . '</td><td><a href="' . esc_url($details['properly_size_images']['description']) . '" target="_blank">' . esc_html($details['properly_size_images']['description']) . '</a></td></tr>';
        echo '<tr><td>Minify CSS</td><td>' . esc_html($details['minify_css']['value']) . '</td><td><a href="' . esc_url($details['minify_css']['description']) . '" target="_blank">' . esc_html($details['minify_css']['description']) . '</a></td></tr>';
        echo '<tr><td>Minify JS</td><td>' . esc_html($details['minify_js']['value']) . '</td><td><a href="' . esc_url($details['minify_js']['description']) . '" target="_blank">' . esc_html($details['minify_js']['description']) . '</a></td></tr>';
        echo '<tr><td>Unused JS</td><td>' . esc_html($details['unused_js']['value']) . '</td><td><a href="' . esc_url($details['unused_js']['description']) . '" target="_blank">' . esc_html($details['unused_js']['description']) . '</a></td></tr>';
        echo '<tr><td>Unused CSS</td><td>' . esc_html($details['unused_css']['value']) . '</td><td><a href="' . esc_url($details['unused_css']['description']) . '" target="_blank">' . esc_html($details['unused_css']['description']) . '</a></td></tr>';
        echo '</tbody>';
        echo '</table>';

        echo '<h2>Page Details</h2>';
        echo '<table>';
        echo '<tbody>';
        echo '<tr>';
        echo '<td><strong>Total Page Size:</strong></td>';
        echo '<td>' . esc_html($details['total_page_size']['value']) . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td><strong>Total Requests:</strong></td>';
        echo '<td>' . esc_html($details['total_requests']['value']) . '</td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';

        echo '<h2>Page Breakdown</h2>';
        echo '<table>';
        echo '<thead><tr><th>Resource Type</th><th>Count</th><th>Size (MB)</th><th>Percentage of Bytes</th></tr></thead>';
        echo '<tbody>';
        foreach ($details['resource_types'] as $type => $info) {
            echo '<tr>';
            echo '<td>' . esc_html($type) . '</td>';
            echo '<td>' . esc_html($info['count']) . '</td>';
            echo '<td>' . esc_html($info['size_mb']) . '</td>';
            echo '<td>' . esc_html($info['percentage']) . '%</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>'; // End of copy-content

        echo '<button class="copy-btn" data-target="#lighthouse-report-content">Copy to Clipboard</button>';
    } else {
        echo 'Failed to retrieve optimization details.';
    }

    echo '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google PageSpeed Insights Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
            box-sizing: border-box;
        }
        h1, h2, h3 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        a {
            color: #1a73e8;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php display_lighthouse_report(); ?>
    </div>
    <script src="<?php echo plugin_dir_url(__FILE__) . 'includes/clipboard-copy.js'; ?>"></script>
</body>
</html>
