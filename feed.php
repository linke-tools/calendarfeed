<?php

require_once __DIR__ . '/jcal2rss.php';

try {
    // Load config
    $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error parsing config file');
    }

    // Get and validate parameters
    if (!isset($_GET['keys'])) {
        throw new Exception('Missing parameter: keys');
    }
    
    // Parse keys
    $keys = array_filter(array_map('trim', explode(',', $_GET['keys'])));
    if (empty($keys)) {
        throw new Exception('No valid keys provided');
    }

    // Get and validate hours
    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
    if ($hours <= 0 || $hours > $config['feed']['max_hours']) {
        throw new Exception("Hours must be between 1 and {$config['feed']['max_hours']}");
    }

    // Get and validate timezone
    $timezone = isset($_GET['timezone']) ? urldecode($_GET['timezone']) : $config['feed']['default_timezone'];
    try {
        $tz = new DateTimeZone($timezone);
    } catch (Exception $e) {
        $timezone = $config['feed']['default_timezone'];
        $tz = new DateTimeZone($timezone);
    }

    // Create database connection
    $db = new PDO(
        "mysql:host={$config['database']['host']};port={$config['database']['port']};dbname={$config['database']['name']};charset=utf8mb4",
        $config['database']['user'],
        $config['database']['password']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get calendar URLs for the provided keys
    $stmt = $db->prepare("SELECT feed_key, calendar_url FROM calendar_feeds WHERE feed_key IN (" . 
        str_repeat('?,', count($keys) - 1) . '?)');
    $stmt->execute($keys);
    $calendar_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Collect URLs and track missing keys
    $urls = [];
    $found_keys = [];
    foreach ($calendar_data as $row) {
        if (!empty($row['calendar_url'])) {
            $url = $row['calendar_url'];
            
            // Add authentication to URL
            $parsed_url = parse_url($url);
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
            
            // Add export parameters
            $urls[] = $auth_url . 
                     (strpos($auth_url, '?') === false ? '?' : '&') . 
                     "export&start=" . time() . 
                     "&end=" . (time() + ($hours * 3600)) . 
                     "&accept=jcal&expand=1";
            
            $found_keys[] = $row['feed_key'];
        }
    }
    $missing_keys = array_diff($keys, $found_keys);

    // Generate feed
    header('Content-Type: application/rss+xml; charset=utf-8');
    
    if (empty($urls)) {
        // If no valid URLs found, return empty feed with comment
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<!-- No valid calendar URLs found for keys: " . implode(', ', $keys) . " -->\n";
        echo "<rss version=\"2.0\"><channel></channel></rss>";
    } else {
        $feed = jcal_to_rss($urls, $timezone);
        if (!empty($missing_keys)) {
            // Insert comment about missing keys after XML declaration
            $feed = preg_replace(
                '/<\?xml.*?\?>\s*/s',
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!-- Missing keys: " . 
                implode(', ', $missing_keys) . " -->\n",
                $feed
            );
        }
        echo $feed;
    }

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error: " . $e->getMessage();
} 