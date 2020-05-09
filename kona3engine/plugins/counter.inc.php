<?php
function kona3plugins_counter_execute($args) {
  global $kona3conf;
  $page = $kona3conf['page'];
  $file = kona3getWikiFile($page);
  if (file_exists($file)) {
    $page_id = kona3db_getPageId($page, TRUE);
  } else {
    $page_id = kona3db_getPageId($page, FALSE);
  }
  if ($page_id == 0) {
    return "-";
  }

  // check table
  if (!db_table_exists('counter')) {
    counter_init_db();
  }

  // === total counter ===
  $value = 0;
  $r = db_get1(
    "SELECT * FROM counter WHERE page_id=?",
    [$page_id]);
  if (!isset($r['value'])) {
    db_insert(
      "INSERT INTO counter ".
      "(page_id, value, mtime) VALUES (?,?,?)",
      [$page_id, 0, time()]);
  } else {
    $value = $r['value'];
  }
  $value += 1;
  db_exec("UPDATE counter SET value=?, mtime=? ".
    "WHERE page_id=?",
    [$value, time(), $page_id]);
  // === monthly counter ===
  $year  = intval(date('Y'));
  $month = intval(date('n'));
  $mvalue = 0;
  $counter_id = 0;
  $r = db_get1(
    "SELECT * FROM counter_month ".
    "WHERE (page_id=?)AND(year=?)AND(month=?) LIMIT 1",
    [$page_id, $year, $month]);
  if (!isset($r['value'])) {
    $counter_id = db_insert(
      "INSERT INTO counter_month ".
      "(page_id, year, month, value, mtime) ".
      "VALUES(?,?,?,?,?)",
      [$page_id, $year, $month, 0, time()]);
  } else {
    $mvalue = $r['value'];
    $counter_id = $r['counter_id'];
  }
  $mvalue += 1;
  db_exec(
    "UPDATE counter_month SET value=?, mtime=? ".
    "WHERE counter_id=?",
    [$mvalue, time(), $counter_id]);
  //
  $m_this = lang('Monthly');
  return 
    "<div class='counter'>".
    "$value".
    "<span class='coutner_month'>({$m_this}{$mvalue})</span>".
    "</div>";
}

function counter_init_db() {
  $sql1 =<<<__EOS__
CREATE TABLE counter (
  page_id INTEGER PRIMARY KEY,
  value INTEGER DEFAULT 0,
  mtime INTEGER DEFAULT 0
);
__EOS__;

  $sql2 =<<<__EOS__
CREATE TABLE counter_month (
  counter_id INTEGER PRIMARY KEY,
  page_id INTEGER,
  year INTEGER,
  month INTEGER,
  value INTEGER DEFAULT 0,
  mtime INTEGER DEFAULT 0
);
__EOS__;
  
  $sql3 = "CREATE UNIQUE INDEX counter_month_index ".
    "ON counter_month (page_id, year, month)";
  db_exec("begin");
  db_exec($sql1);
  db_exec($sql2);
  db_exec($sql3);
  db_exec("commit");
}

