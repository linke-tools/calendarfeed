<?php

require_once __DIR__ . '/get_calendars.php';

function discover_calendars($config) {
    echo "Starting calendar discovery...\n";
    
    // Create database connection
    $db = new PDO(
        "mysql:host={$config['database']['host']};port={$config['database']['port']};dbname={$config['database']['name']};charset=utf8mb4",
        $config['database']['user'],
        $config['database']['password']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Delete old entries without URL
    $stmt = $db->prepare("
        DELETE FROM calendar_feeds 
        WHERE calendar_url IS NULL 
        AND created_at < DATE_SUB(NOW(), INTERVAL :max_time MINUTE)
    ");
    $stmt->execute(['max_time' => $config['calendar_discovery']['unused_key_expiry_minutes']]);
    echo "Cleaned up expired feed keys\n";
    
    // Get all feed_keys without URL
    $stmt = $db->prepare("SELECT feed_key FROM calendar_feeds WHERE calendar_url IS NULL");
    $stmt->execute();
    $pending_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($pending_keys)) {
        echo "No pending feed keys to discover\n";
        return;
    }
    echo "Found " . count($pending_keys) . " pending feed keys\n";
    
    // Get all available calendars
    $calendars = get_calendar_urls(
        $config['caldav']['base_url'],
        $config['caldav']['username'],
        $config['caldav']['password']
    );
    echo "Found " . count($calendars) . " calendars to check\n";
    
    // Calculate time range for calendar events
    $start_time = time();
    $end_time = $start_time + ($config['calendar_discovery']['discovery_window_minutes'] * 60);
    echo "Checking events from " . date('Y-m-d H:i:s', $start_time) . 
         " to " . date('Y-m-d H:i:s', $end_time) . "\n";
    
    $discovered = 0;
    // Check each calendar for feed keys in event titles
    foreach ($calendars as $calendar) {
        echo "\nChecking calendar: " . $calendar['name'] . "\n";
        
        // Build jcal URL with auth for fetching
        $parsed_url = parse_url($calendar['url']);
        $auth_url = $parsed_url['scheme'] . '://' . 
                   urlencode($config['caldav']['username']) . ':' . 
                   urlencode($config['caldav']['password']) . '@' . 
                   $parsed_url['host'];
        
        if (isset($parsed_url['port'])) {
            $auth_url .= ':' . $parsed_url['port'];
        }
        
        $auth_url .= $parsed_url['path'];
        if (isset($parsed_url['query'])) {
            $auth_url .= '?' . $parsed_url['query'];
        }
        
        $jcal_url = $auth_url . 
                    (strpos($auth_url, '?') === false ? '?' : '&') . 
                    "export&start={$start_time}&end={$end_time}&accept=jcal&expand=1";
        
        // Fetch calendar data
        $jcal_data = json_decode(file_get_contents($jcal_url), true);
        if (!$jcal_data) {
            echo "  Failed to fetch calendar data\n";
            continue;
        }
        
        $events_checked = 0;
        $keys_found = 0;
        // Check each event
        foreach ($jcal_data[2] as $event) {
            if ($event[0] !== 'vevent') {
                continue;
            }
            $events_checked++;
            
            // Get event title
            $title = '';
            foreach ($event[1] as $prop) {
                if ($prop[0] === 'summary') {
                    $title = $prop[3];
                    break;
                }
            }
            
            // Check if any pending key is in the title
            foreach ($pending_keys as $key) {
                if (stripos($title, $key) !== false) {
                    echo "  Found key '$key' in event '$title'\n";
                    $keys_found++;
                    
                    // First delete any existing entries with the same URL
                    $stmt = $db->prepare("
                        DELETE FROM calendar_feeds 
                        WHERE calendar_url = :url
                    ");
                    $stmt->execute(['url' => $calendar['url']]);
                    
                    // Then update with new key
                    $stmt = $db->prepare("
                        UPDATE calendar_feeds 
                        SET calendar_url = :url 
                        WHERE feed_key = :key 
                        AND calendar_url IS NULL
                    ");
                    $stmt->execute([
                        'url' => $calendar['url'],
                        'key' => $key
                    ]);
                    $discovered++;
                }
            }
        }
        echo "  Checked $events_checked events, found $keys_found feed keys\n";
    }
    
    echo "\nDiscovery completed: Connected $discovered calendars\n";
}

try {
    // Load config
    $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error parsing config file: ' . json_last_error_msg());
    }
    
    discover_calendars($config);
    echo "Calendar discovery completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
 