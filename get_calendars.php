<?php

require_once __DIR__ . '/url_utils.php';

function get_calendar_urls($calendar_base_url, $username, $password) {
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options for PROPFIND request
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($calendar_base_url, '/') . '/calendars/' . $username,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PROPFIND',
        CURLOPT_HTTPHEADER => ['Depth: 1', 'Content-Type: application/xml'],
        CURLOPT_USERPWD => $username . ':' . $password,
        CURLOPT_POSTFIELDS => '<?xml version="1.0" encoding="utf-8" ?>' .
            '<propfind xmlns="DAV:">' .
            '<prop>' .
            '<resourcetype/>' .
            '<displayname/>' .
            '</prop>' .
            '</propfind>'
    ]);
    
    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check if request was successful
    if ($http_code !== 207) { // 207 is "Multi-Status"
        throw new Exception('Failed to fetch calendars. HTTP code: ' . $http_code);
    }
    
    // Parse XML response
    $xml = new SimpleXMLElement($response);
    $xml->registerXPathNamespace('d', 'DAV:');
    
    // Initialize array for calendar URLs
    $calendars = [];

    // webdav will return relative href to the origin while calendar_base_url
    // might also contain a path element hence we need the real base base i.e.
    // the origin
    $base_url = getUrlPrefix($calendar_base_url);
    
    // Process each response element
    foreach ($xml->xpath('//d:response') as $response) {
        $href = (string)$response->xpath('d:href')[0];
        $resourcetypes = $response->xpath('.//d:resourcetype/*');
        
        // Check if this is a calendar
        foreach ($resourcetypes as $type) {
            if ($type->getName() === 'calendar') {
                // Get calendar name
                $displayname = $response->xpath('.//d:displayname');
                $name = $displayname ? (string)$displayname[0] : basename($href);
                
                // Get collection name (last part of the URL)
                $collection = trim(basename($href), '/');
                
                // Build calendar URL
                if (isRelativeHref($href)) {
                    $calendar_url = rtrim($base_url, '/') . $href;
                } else {
                    $calendar_url = $href;
                }
                
                // Add to results
                $calendars[] = [
                    'name' => $name,
                    'url' => $calendar_url,
                    'collection' => $collection
                ];
                break;
            }
        }
    }
    
    return $calendars;
}

