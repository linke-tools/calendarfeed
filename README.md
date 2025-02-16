# Calendar Feed Generator

A tool to generate RSS feeds from CalDAV calendars. Users can share their calendars and receive a personalized RSS feed URL.

## Features
- Automatic calendar discovery
- Multiple calendars in one feed
- Configurable time ranges
- Timezone support
- Secure feed keys

## Installation

1. Copy `config.sample.json` to `config.json`
2. Create the database using `schema.sql`
3. Configure all parameters in `config.json`
4. Set up a cron job to run `discover_calendars.php` periodically

## Configuration Parameters

### Database Settings
```json
"database": {
    "host": "localhost",     // Database server address
    "port": 3306,           // MariaDB/MySQL port
    "name": "calendar_feeds", 
    "user": "calendar_user", 
    "password": "your_secure_password"
}
```

### Feed Settings
```json
"feed": {
    "default_timezone": "Europe/Berlin",  // Default timezone if none specified in request
    "max_hours": 8760,                    // Maximum allowed hours parameter (8760 = 1 year)
    "base_url": "https://example.com/feed.php"  // Public URL of the feed endpoint
}
```

### Calendar Discovery Settings
```json
"calendar_discovery": {
    "unused_key_expiry_minutes": 1440,    // Delete unused feed keys after 24 hours
    "discovery_window_minutes": 1440       // Look for feed keys in events within next 24 hours
}
```

### CalDAV Settings
```json
"caldav": {
    "base_url": "http://127.0.0.1:7000",  // CalDAV server address
    "username": "admin",                   // Account used to discover calendars
    "password": "admin"                    // Password for the discovery account
}
```

## Components

### add_feed.php
Web interface for users to:
- Generate new feed keys
- Get instructions for calendar sharing
- View usage examples

### feed.php
RSS feed endpoint that accepts:
- `keys`: Comma-separated list of feed keys
- `hours`: Time range in hours (optional)
- `timezone`: Timezone for event times (optional)

### discover_calendars.php
Background script that:
- Removes expired unused feed keys
- Discovers new calendar connections
- Should be run periodically via cron

## Usage

### For Users
1. Visit the add_feed.php page
2. Generate a new feed key
3. Share calendar with the specified CalDAV user
4. Create an event containing the feed key
5. Wait for discovery (usually within minutes)
6. Use the provided RSS feed URL

### For Administrators
1. Set up the database using schema.sql
2. Configure all parameters in config.json
3. Set up a cron job to run discover_calendars.php
4. Make add_feed.php accessible to users
5. Ensure feed.php is accessible publicly

## Feed URL Options

The feed URL supports several parameters:
- `keys`: Required. One or more feed keys (comma-separated)
- `hours`: Optional. Number of hours to look ahead (default: 24)
- `timezone`: Optional. Timezone for event times (default: from config)

Examples:
```
https://example.com/feed.php?keys=abc123
https://example.com/feed.php?keys=abc123,def456&hours=168&timezone=Europe%2FBerlin
```

## Notes
- The feed max_hours limits how far into the future events can be retrieved (default: 1 year)
- Calendar discovery window is set to 24 hours to give users enough time to set up
- Unused feed keys are automatically removed after 24 hours
- Feed keys are automatically removed if the calendar is connected with a new key
