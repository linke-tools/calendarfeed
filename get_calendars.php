<?php

function get_calendar_urls($base_url, $username, $password) {
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options for PROPFIND request
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($base_url, '/') . '/calendars/' . $username,
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
                $calendar_url = rtrim($base_url, '/') . $href;
                
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
