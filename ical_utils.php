<?php

function normalize_ical_lines($content) {
    $normalized = str_replace(["\r\n", "\r"], "\n", $content);
    return str_replace("\n", "\r\n", $normalized);
}

function extract_ical_blocks($ics_content, $block_name) {
    $pattern = '/BEGIN:' . preg_quote($block_name, '/') . '\R.*?\REND:' . preg_quote($block_name, '/') . '/si';
    preg_match_all($pattern, $ics_content, $matches);
    return $matches[0] ?? [];
}

function build_ical_feed($urls, $feed_url = '', $timezone = 'Europe/Berlin') {
    $all_timezones = [];
    $all_events = [];

    foreach ($urls as $url) {
        $ics_content = @file_get_contents($url);
        if ($ics_content === false || trim($ics_content) === '') {
            continue;
        }

        $ics_content = normalize_ical_lines($ics_content);
        $timezone_blocks = extract_ical_blocks($ics_content, 'VTIMEZONE');
        foreach ($timezone_blocks as $tz_block) {
            $all_timezones[] = trim($tz_block);
        }

        $event_blocks = extract_ical_blocks($ics_content, 'VEVENT');
        foreach ($event_blocks as $event_block) {
            $all_events[] = trim($event_block);
        }
    }

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//calendarfeed//iCal Feed//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
    ];

    if ($feed_url !== '') {
        $lines[] = 'URL:' . $feed_url;
    }

    $lines[] = 'X-WR-TIMEZONE:' . $timezone;

    foreach ($all_timezones as $timezone_block) {
        foreach (explode("\r\n", normalize_ical_lines($timezone_block)) as $line) {
            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }

    foreach ($all_events as $event_block) {
        foreach (explode("\r\n", normalize_ical_lines($event_block)) as $line) {
            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }

    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", $lines) . "\r\n";
}
