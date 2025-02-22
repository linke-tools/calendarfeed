<?php

function jcal_to_rss($urls, $timezone = 'Europe/Berlin') {
    if (!is_array($urls)) {
        $urls = [$urls];
    }
    
    // Set timezone
    $tz = new DateTimeZone($timezone);
    
    // Create XML document
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    
    // Create RSS root
    $rss = $xml->createElement('rss');
    $rss->setAttribute('version', '2.0');
    $xml->appendChild($rss);
    
    // Create channel
    $channel = $xml->createElement('channel');
    $rss->appendChild($channel);
    
    // Get the actual request URL
    $request_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                  "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    // Add required channel elements
    $channel->appendChild($xml->createElement('title', 'Calendar Feed'));
    $channel->appendChild($xml->createElement('description', 'Calendar events from CalDAV calendars'));
    $channel->appendChild($xml->createElement('link', htmlspecialchars($request_url)));
    
    // Add atom:link for self-reference
    $atomLink = $xml->createElementNS('http://www.w3.org/2005/Atom', 'atom:link');
    $atomLink->setAttribute('href', htmlspecialchars($request_url));
    $atomLink->setAttribute('rel', 'self');
    $atomLink->setAttribute('type', 'application/rss+xml');
    $channel->appendChild($atomLink);
    
    // Collect all events
    $all_events = [];
    
    foreach ($urls as $url) {
        // Fetch jCal data from URL
        $jcal_data = json_decode(file_get_contents($url), true);
        if (!$jcal_data) {
            continue; // Skip if URL couldn't be fetched or parsed
        }
        
        // Process each event
        foreach ($jcal_data[2] as $event) {
            if ($event[0] !== 'vevent') {
                continue;
            }
            
            // Convert event properties to associative array
            $properties = [];
            foreach ($event[1] as $prop) {
                $properties[$prop[0]] = $prop[3];
            }
            
            // Store event with start time for sorting
            $start_time = new DateTime($properties['dtstart']);
            $start_time->setTimezone($tz);
            $all_events[] = [
                'start_time' => $start_time,
                'properties' => $properties
            ];
        }
    }
    
    // Sort events by start time
    usort($all_events, function($a, $b) {
        return $a['start_time']->getTimestamp() - $b['start_time']->getTimestamp();
    });
    
    // Process sorted events
    foreach ($all_events as $event) {
        $properties = $event['properties'];
        
        // Create item
        $item = $xml->createElement('item');
        
        // Add title (summary)
        $title = $properties['summary'] ?? 'Ohne Titel';
        $item->appendChild($xml->createElement('title', htmlspecialchars($title, ENT_XML1)));
        
        // Create description
        $description = [];
        
        // Format date/time
        $start_time = $event['start_time'];
        $end_time = null;
        if (isset($properties['dtend'])) {
            $end_time = new DateTime($properties['dtend']);
            $end_time->setTimezone($tz);
        }
        
        // Add time
        if ($end_time) {
            // Check if it's a full-day event
            if (strlen($properties['dtstart']) === 10) { // Format: YYYY-MM-DD
                $description[] = 'Ganztägig';
            } else {
                $description[] = sprintf(
                    '%s - %s',
                    $start_time->format('H:i'),
                    $end_time->format('H:i')
                );
            }
        } else {
            $description[] = $start_time->format('H:i');
        }
        
        // Add location if available
        if (isset($properties['location'])) {
            $description[] = $properties['location'];
        }
        
        // Add description if available
        if (isset($properties['description'])) {
            $description[] = $properties['description'];
        }
        
        // Add description to item
        $item->appendChild($xml->createElement('description', htmlspecialchars(implode(' / ', $description), ENT_XML1)));
        
        // Add guid - combine uid and last-modified to detect changes
        $guid_base = $properties['uid'] ?? uniqid('event-');
        $last_modified = $properties['last-modified'] ?? $properties['created'] ?? '';
        $guid = $guid_base . '#' . $last_modified;
        
        $guid_element = $xml->createElement('guid', htmlspecialchars($guid, ENT_XML1));
        $guid_element->setAttribute('isPermaLink', 'false');
        $item->appendChild($guid_element);
        
        // Add pubDate - use event start time in UTC
        $start_time_utc = clone $start_time;
        $start_time_utc->setTimezone(new DateTimeZone('UTC'));
        $item->appendChild($xml->createElement('pubDate', $start_time_utc->format(DateTime::RSS)));
        
        // Add item to channel
        $channel->appendChild($item);
    }
    
    return $xml->saveXML();
}