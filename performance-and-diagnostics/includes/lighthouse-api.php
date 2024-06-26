<?php

class My_Plugin_API {
    private $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    private $api_key = 'AIzaSyDZnj8x8_qyItpDBWmR51hHfy0eKww29GI'; 

    public function get_page_speed_details($url) {
        $api_endpoint = "{$this->api_url}?url=" . urlencode($url) . "&key={$this->api_key}&strategy=desktop";
        $response = wp_remote_get($api_endpoint, [
            'timeout' => 120, // Set timeout to 120 seconds
        ]);

        if (is_wp_error($response)) {
            error_log('API request failed: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            return false;
        }

        return $this->parse_data($data);
    }

    private function parse_data($data) {
        $details = [];
        $size_by_type = [];
        $count_by_type = [];
        $total_size = 0;

        if (isset($data['lighthouseResult']['audits'])) {
            $audits = $data['lighthouseResult']['audits'];

            $metrics = [
                'server-response-time' => ['fields' => ['description', 'displayValue'], 'label' => 'ttfb'],
                'interactive' => ['fields' => ['description', 'displayValue'], 'label' => 'full_load_time'],
                'first-contentful-paint' => ['fields' => ['displayValue', 'description'], 'label' => 'fcp'],
                'largest-contentful-paint' => ['fields' => ['description', 'displayValue'], 'label' => 'lcp'],
                'uses-responsive-images' => ['fields' => ['description', 'displayValue'], 'label' => 'properly_size_images'],
                'unminified-css' => ['fields' => ['description', 'overallSavingsBytes'], 'label' => 'minify_css'],
                'unminified-javascript' => ['fields' => ['description', 'overallSavingsBytes'], 'label' => 'minify_js'],
                'unused-javascript' => ['fields' => ['description', 'overallSavingsBytes'], 'label' => 'unused_js'],
                'unused-css-rules' => ['fields' => ['description', 'overallSavingsBytes'], 'label' => 'unused_css'],
                'total-byte-weight' => ['fields' => ['description', 'numericValue'], 'label' => 'total_page_size'],
                'network-requests' => ['fields' => ['description'], 'label' => 'total_requests'],
            ];

            foreach ($metrics as $key => $metric) {
                $fields_values = [];
                $display_value = 'N/A';
                $description = 'N/A';

                foreach ($metric['fields'] as $field) {
                    if (isset($audits[$key][$field])) {
                        $fields_values[$field] = $audits[$key][$field];
                    }
                }

                if (isset($fields_values['description'])) {
                    $description = $fields_values['description'];
                    if (preg_match('/\[.*?\]\((http[s]?:\/\/[^\)]+)\)/', $description, $matches)) {
                        $description = $matches[1];
                    }
                }

                if ($key === 'network-requests') {
                    if (isset($audits['network-requests']['details']['items'])) {
                        $requests = $audits['network-requests']['details']['items'];
                        $total_requests = count($requests);

                        foreach ($requests as $req) {
                            if (isset($req['transferSize']) && isset($req['resourceType'])) {
                                $size_by_type[$req['resourceType']] = ($size_by_type[$req['resourceType']] ?? 0) + $req['transferSize'];
                                $count_by_type[$req['resourceType']] = ($count_by_type[$req['resourceType']] ?? 0) + 1;
                            }
                        }

                        $total_size = array_sum($size_by_type);
                        $details['total_requests'] = [
                            'value' => $total_requests,
                            'description' => 'Lists the network requests that were made during page load.'
                        ];
                    }
                } elseif ($key === 'server-response-time' && isset($audits['server-response-time']['numericValue'])) {
                    $response_time_in_ms = $audits['server-response-time']['numericValue'];
                    $response_time_in_s = $response_time_in_ms / 1000;
                    $display_value = sprintf("%.3f seconds", $response_time_in_s);
                } elseif ($key === 'total-byte-weight' && isset($audits['total-byte-weight']['numericValue'])) {
                    $display_value = sprintf("%.2f MB", $audits['total-byte-weight']['numericValue'] / (1024 * 1024));
                } elseif (in_array($key, ['unminified-css', 'unminified-javascript', 'unused-javascript', 'unused-css-rules', 'uses-responsive-images'])) {
                    if (isset($audits[$key]['details']['overallSavingsBytes'])) {
                        $savings_bytes = $audits[$key]['details']['overallSavingsBytes'];
                        $savings_kb = $savings_bytes / 1024;
                        $display_value = sprintf("Potential Savings %.2f KB", $savings_kb);
                    }
                } else {
                    foreach ($metric['fields'] as $field) {
                        if (isset($audits[$key][$field])) {
                            $fields_values[$field] = $audits[$key][$field];
                        }
                    }

                    if (isset($fields_values['description'])) {
                        $description = $fields_values['description'];
                        if (preg_match('/\[.*?\]\((http[s]?:\/\/[^\)]+)\)/', $description, $matches)) {
                            $description = $matches[1];
                        }
                    }

                    foreach (['numericValue', 'displayValue', 'score', 'title', 'overallSavingsMs'] as $field) {
                        if (isset($fields_values[$field])) {
                            $display_value = $fields_values[$field];
                            break;
                        }
                    }
                }

                $details[$metric['label']] = [
                    'value' => $display_value,
                    'description' => $description
                ];
            }

            // Add resource type details
            $details['resource_types'] = [];
            foreach ($size_by_type as $type => $size) {
                $details['resource_types'][$type] = [
                    'count' => $count_by_type[$type],
                    'size_mb' => $this->format_bytes($size, 2),
                    'percentage' => ($total_size > 0) ? round(($size / $total_size) * 100, 2) : 0
                ];
            }

            // Add total page size and total requests
            $details['total_page_size'] = [
                'value' => sprintf("%.2f MB", $total_size / (1024 * 1024)),
                'description' => 'https://developer.chrome.com/docs/lighthouse/performance/total-byte-weight/'
            ];
            $details['total_requests'] = [
                'value' => array_sum($count_by_type),
                'description' => 'Lists the network requests that were made during page load.'
            ];
        }

        return $details;
    }

    private function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
