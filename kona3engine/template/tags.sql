CREATE TABLE IF NOT EXISTS tags (
    tag TEXT,
    page TEXT,
    created_at INTEGER,
    updated_at INTEGER,
    PRIMARY KEY (tag, page)
);
CREATE INDEX IF NOT EXISTS idx_tag ON tags (tag);
CREATE INDEX IF NOT EXISTS idx_page ON tags (page);
