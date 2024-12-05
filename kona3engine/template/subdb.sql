/* PLUGINS TABLE */
CREATE TABLE plugins (
    plugins_id INTEGER PRIMARY KEY AUTOINCREMENT,
    plugins_name TEXT NOT NULL DEFAULT '',
    sub_name TEXT NOT NULL DEFAULT '',
    title TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '',
    tag TEXT NOT NULL DEFAULT '',
    meta TEXT NOT NULL DEFAULT '',
    ivalue INTEGER DEFAULT 0,
    fvalue REAL DEFAULT 0.0,
    ctime INTEGER DEFAULT 0,
    mtime INTEGER DEFAULT 0
);

/* INFO TABLE */
CREATE TABLE info (
    info_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL DEFAULT '',
    body TEXT,
    tag TEXT NOT NULL DEFAULT '',
    ivalue INTEGER DEFAULT 0,
    ctime INTEGER,
    mtime INTEGER
);

/* COUNTER TABLE */
CREATE TABLE counter (
  page_id INTEGER PRIMARY KEY,
  value INTEGER DEFAULT 0,
  mtime INTEGER DEFAULT 0
);

/* COUNTER_MONTH TABLE */
CREATE TABLE counter_month (
  counter_id INTEGER PRIMARY KEY,
  page_id INTEGER,
  year INTEGER,
  month INTEGER,
  value INTEGER DEFAULT 0,
  mtime INTEGER DEFAULT 0
);

/* COUNTER_MONTH INDEX */
CREATE UNIQUE INDEX counter_month_index ON counter_month (page_id, year, month);
