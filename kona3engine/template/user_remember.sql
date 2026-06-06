CREATE TABLE remember_tokens (
  selector TEXT PRIMARY KEY,
  token_hash TEXT NOT NULL,
  user_id INTEGER DEFAULT 0,
  user TEXT DEFAULT '',
  email TEXT DEFAULT '',
  perm TEXT DEFAULT 'normal',
  expires INTEGER NOT NULL,
  created_at INTEGER NOT NULL,
  last_used_at INTEGER DEFAULT 0,
  user_agent TEXT DEFAULT ''
);

CREATE INDEX idx_remember_tokens_expires ON remember_tokens(expires);
