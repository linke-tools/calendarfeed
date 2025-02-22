<?php

function generate_feed_key() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $key = '';
    for ($i = 0; $i < 8; $i++) {
        $key .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $key;
}

try {
    // Only process POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Load config
        $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error parsing config file');
        }
        
        // Create database connection
        $db = new PDO(
            "mysql:host={$config['database']['host']};port={$config['database']['port']};dbname={$config['database']['name']};charset=utf8mb4",
            $config['database']['user'],
            $config['database']['password']
        );
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Generate new feed key
        $feed_key = generate_feed_key();
        
        // Insert into database
        $stmt = $db->prepare("INSERT INTO calendar_feeds (feed_key) VALUES (?)");
        $stmt->execute([$feed_key]);
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode(['feed_key' => $feed_key]);
        exit;
    }
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Load config for the template
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('Error parsing config file');
}
$feed_base_url = $config['feed']['base_url'];
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bundescloud Kalender verbinden</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .steps {
            margin: 20px 0;
            padding: 0;
        }
        .steps li {
            margin-bottom: 15px;
        }
        .key-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .key-display {
            font-family: monospace;
            background: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            display: none;
            margin: 10px 0;
            flex: 1;
        }
        button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #45a049;
        }
        .note {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 10px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .url-example {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .url-example code {
            display: block;
            margin: 10px 0;
            word-break: break-all;
        }
        .copy-button {
            background: #6c757d;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .examples {
            margin-top: 30px;
        }
        .example-description {
            margin: 5px 0;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>Bundescloud Kalender verbinden</h1>
    
    <div class="note">
        <strong>Hinweis:</strong> Der Prozess kann einige Minuten dauern. 
        Der Feed wird automatisch erstellt, sobald der Bundescloud Kalender mit Linke.tools verbunden und ein spezieller Termin im Kalender erkannt wurde.
    </div>

    <ol class="steps">
        <li>Klicken Sie auf den Button, um einen neuen Feed-Schlüssel zu generieren:
            <div>
                <button onclick="generateKey()">Neuen Feed-Schlüssel generieren</button>
                <div class="key-container">
                    <div id="keyDisplay" class="key-display"></div>
                    <button onclick="copyKey(this)" class="copy-button" style="display: none">Schlüssel kopieren</button>
                </div>
            </div>
        </li>
    </ol>

    <div id="instructions" style="display: none">
        <ol class="steps" start="2">
            <li>Teilen Sie Ihren Kalender mit dem Bundescloud Benutzer "Linke.tools" (mit Lese-Rechten)</li>
            <li>Erstellen Sie einen neuen Termin in Ihrem Kalender:
                <ul>
                    <li>Der Titel des Termins muss Ihren Feed-Schlüssel <strong><span class="key-inline"></span></strong> enthalten</li>
                    <li>Der Termin muss in den nächsten <?= floor($config['calendar_discovery']['discovery_window_minutes']/60) ?> Stunden stattfinden 
                        (bis <?php
                            $tz = new DateTimeZone($config['feed']['default_timezone']);
                            $date = new DateTime('now', $tz);
                            $date->modify('+' . $config['calendar_discovery']['discovery_window_minutes'] . ' minutes');
                            echo $date->format('d.m.Y H:i');
                        ?> Uhr)</li>
                    <li>Der Termin kann nach erfolgreicher Verbindung wieder gelöscht werden</li>
                </ul>
            </li>
            <li>Warten Sie einige Minuten, bis die Verbindung hergestellt wurde</li>
            <li>Der Feed ist dann unter folgender URL verfügbar:
                <div id="urlExample" class="url-example">
                    <code></code>
                    <button onclick="copyUrl(this)" class="copy-button">URL kopieren</button>
                </div>
            </li>
        </ol>

        <div class="examples">
            <h2>Beispiele und Optionen</h2>
            
            <h3>Zeiträume</h3>
            <p>Sie können den Zeitraum für den Feed mit dem Parameter "hours" anpassen:</p>
            <div class="url-example">
                <div class="example-description">Termine der nächsten 24 Stunden (Standard):</div>
                <code id="example24h"></code>
                <button onclick="copyUrl(this)" class="copy-button">URL kopieren</button>
                
                <div class="example-description">Termine der nächsten Woche:</div>
                <code id="example168h"></code>
                <button onclick="copyUrl(this)" class="copy-button">URL kopieren</button>
                
                <div class="example-description">Termine des nächsten Monats:</div>
                <code id="example720h"></code>
                <button onclick="copyUrl(this)" class="copy-button">URL kopieren</button>
            </div>

            <h3>Mehrere Kalender kombinieren</h3>
            <p>Sie können mehrere Kalender in einem Feed zusammenfassen, indem Sie die Schlüssel mit Komma trennen:</p>
            <div class="url-example">
                <div class="example-description">Beispiel für zwei kombinierte Kalender:</div>
                <code id="exampleMulti"></code>
                <button onclick="copyUrl(this)" class="copy-button">URL kopieren</button>
            </div>

            <h3>Zeitzonen</h3>
            <p>Sie können die Zeitzone für die Anzeige der Termine anpassen:</p>
            <div class="url-example">
                <div class="example-description">Beispiel für Berlin:</div>
                <code id="exampleBerlin"></code>
                <button onclick="copyUrl(this)" class="copy-button">URL kopieren</button>
                
                <div class="example-description">Beispiel für London:</div>
                <code id="exampleLondon"></code>
                <button onclick="copyUrl(this)" class="copy-button">URL kopieren</button>
            </div>
        </div>

        <div class="note" style="margin-top: 20px;">
            <strong>Wichtig:</strong> Wenn Sie einen Kalender mit einem neuen Feed-Schlüssel verbinden, 
            werden alle alten Feed-Schlüssel für diesen Kalender automatisch entfernt.
        </div>
    </div>

    <script>
        const baseUrl = <?= json_encode($feed_base_url) ?>;
        let currentKey = '';
        
        function formatUrl(key, hours = null, timezone = null) {
            let url = baseUrl;
            
            // Replace keys placeholder
            url = url.replace('{keys}', key);
            
            // Replace hours placeholder if present and provided
            if (url.includes('{hours}')) {
                url = url.replace('{hours}', hours || 24);
            } else if (hours) {
                // Add hours parameter if not in template but provided
                url += (url.includes('?') ? '&' : '?') + 'hours=' + hours;
            }
            
            // Replace timezone placeholder if present and provided
            if (url.includes('{timezone}')) {
                url = url.replace('{timezone}', timezone ? encodeURIComponent(timezone) : 'Europe/Berlin');
            } else if (timezone) {
                // Add timezone parameter if not in template but provided
                url += (url.includes('?') ? '&' : '?') + 'timezone=' + encodeURIComponent(timezone);
            }
            
            return url;
        }
        
        function updateExamples(key) {
            currentKey = key;
            // Update key display in instructions
            document.querySelectorAll('.key-inline').forEach(el => el.textContent = key);
            
            // Show instructions
            document.getElementById('instructions').style.display = 'block';
            
            // Update examples
            document.getElementById('example24h').textContent = formatUrl(key);
            document.getElementById('example168h').textContent = formatUrl(key, 168);
            document.getElementById('example720h').textContent = formatUrl(key, 720);
            document.getElementById('exampleMulti').textContent = formatUrl(key + ',WEITERER_SCHLÜSSEL');
            document.getElementById('exampleBerlin').textContent = formatUrl(key, null, 'Europe/Berlin');
            document.getElementById('exampleLondon').textContent = formatUrl(key, null, 'Europe/London');
        }
        
        function generateKey() {
            fetch(window.location.pathname, {
                method: 'POST',
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Fehler: ' + data.error);
                } else {
                    const keyDisplay = document.getElementById('keyDisplay');
                    keyDisplay.textContent = data.feed_key;
                    keyDisplay.style.display = 'block';
                    
                    // Show copy button
                    keyDisplay.nextElementSibling.style.display = 'block';
                    
                    // Update and show URL example
                    const urlExample = document.getElementById('urlExample');
                    urlExample.querySelector('code').textContent = formatUrl(data.feed_key);
                    
                    // Update all examples with the new key
                    updateExamples(data.feed_key);
                }
            })
            .catch(error => {
                alert('Fehler beim Generieren des Schlüssels');
            });
        }

        function copyKey(button) {
            const key = button.previousElementSibling.textContent;
            navigator.clipboard.writeText(key).then(() => {
                const originalText = button.textContent;
                button.textContent = 'Kopiert!';
                setTimeout(() => {
                    button.textContent = originalText;
                }, 2000);
            });
        }

        function copyUrl(button) {
            const url = button.previousElementSibling.textContent;
            navigator.clipboard.writeText(url).then(() => {
                const originalText = button.textContent;
                button.textContent = 'Kopiert!';
                setTimeout(() => {
                    button.textContent = originalText;
                }, 2000);
            });
        }
    </script>
</body>
</html> 