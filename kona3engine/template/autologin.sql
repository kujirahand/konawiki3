CREATE TABLE tokens (
    id INTEGER PRIMARY KEY,
    email INTEGER NOT NULL,
    token TEXT NOT NULL,
    user_agent TEXT NOT NULL,
    ip_address TEXT NOT NULL,
    perm TEXT NOT NULL,
    memo TEXT DEFAULT '',
    mtime INTEGER
);
