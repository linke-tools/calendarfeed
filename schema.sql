CREATE TABLE calendar_feeds (
    feed_key CHAR(8) PRIMARY KEY,
    calendar_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CHECK (feed_key REGEXP '^[a-zA-Z0-9]{8}$')
);

-- Index für schnellere Suche nach feed_key
CREATE INDEX idx_feed_key ON calendar_feeds(feed_key); 