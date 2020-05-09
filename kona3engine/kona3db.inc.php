<?php
// file: kona3database.inc.php

function kona3db_getPageId($page, $canCreate = TRUE) {
  $r = db_get1("SELECT * FROM pages WHERE name = ?", $page);
  if (!$r) {
    if ($canCreate) {
      $page_id = db_insert(
        "INSERT INTO pages (name, ctime, mtime)".
        "VALUES(?,?,?)",
        [$page, time(), time()]);
    }
  } else {
    $page_id = $r['page_id'];
    return $page_id;
  }
  return 0;
}

function kona3db_writePage($page, $body, $user_id=0) {
  $page_id = kona3db_getPageId($page);
  $hash = kona3getHash($body);
  // check 1 hour history
  $recent_time = time() - (60 * 60 * 1);
  $r = db_get1(
    "SELECT * FROM page_history ".
    "WHERE page_id=? AND mtime > ?",
    [$page, $recent_time]);
  if ($r) {
    $history_id = $r['history_id'];
    db_exec(
      "UPDATE page_history SET ".
      "body=?,hash=?,mtime=?,user_id=? ".
      "WHERE history_id=?",
      [$body, $hash, time(), $user_id, $history_id]);
  } else {
    db_exec(
      "INSERT INTO (page_id,user_id,body,hash,mtime)".
      "VALUES(?,?,?,?,?)",
      [$page_id, $user_id, $body, $hash, time()]);
  }
  return TRUE;
}

