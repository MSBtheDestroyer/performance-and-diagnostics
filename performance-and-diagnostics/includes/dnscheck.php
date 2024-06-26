<?php
/**
 * DNS Check Functions
 */

// Function to get server IP
function get_server_ip() {
    return $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
}

// Function to get A record
function get_a_record($domain) {
    $records = dns_get_record($domain, DNS_A);
    return $records ? $records[0]['ip'] : 'Not Found';
}

// Function to get Name Server records
function get_nameserver_records($domain) {
    // Remove "www." from the beginning of the domain if it exists
    $domain = preg_replace('/^www\./i', '', $domain);

    // Check if the domain format is valid
    if (strpos($domain, '.') === false) {
        return ['Invalid domain format'];
    }

    $domain_parts = explode('.', $domain);

    // Check if the domain is a subdomain
    if (count($domain_parts) <= 2) {
        // Not a subdomain, proceed with DNS lookup
        $ns_records = dns_get_record($domain, DNS_NS);
        if ($ns_records === false) {
            return ['Failed to retrieve Name Server records'];
        } else {
            return array_column($ns_records, 'target');
        }
    } else {
        // It's a subdomain, so lookup the NS records for the parent domain
        $parent_domain = implode('.', array_slice($domain_parts, -2));
        $ns_records = dns_get_record($parent_domain, DNS_NS);
        if ($ns_records === false) {
            return ['Failed to retrieve Name Server records for the parent domain'];
        } else {
            return array_column($ns_records, 'target');
        }
    }
}

// Main function to get DNS data
function get_dns() {
    $domain = parse_url(get_site_url(), PHP_URL_HOST);
    $domain = preg_replace('/^www\./', '', $domain);

    $dns = (object) [
        'sections' => []
    ];

    // Server IP
    $server_ip = get_server_ip();
    $dns->sections[] = [
        'name' => 'Server IP',
        'data' => [
            ['Server IP' => $server_ip]
        ]
    ];

    // A Records
    $a_record = get_a_record($domain);
    $www_a_record = get_a_record('www.' . $domain);
    $dns->sections[] = [
        'name' => 'A Record Lookup',
        'data' => [
            ['Domain' => $domain, 'A Record' => $a_record],
            ['Domain' => 'www.' . $domain, 'A Record' => $www_a_record]
        ]
    ];

    // Name Server Records
    $ns_records = get_nameserver_records($domain);
    $dns->sections[] = [
        'name' => 'Name Server Records',
        'data' => array_map(function($ns) {
            return ['Name Server' => $ns];
        }, $ns_records)
    ];

    return $dns;
}
?>