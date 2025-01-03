<?php
// file: kona3db.inc.php
function kona3db_getPageId($page, $canCreate = FALSE) {
  $r = db_get1(
    "SELECT * FROM pages ".
    "WHERE name = ? ".
    "LIMIT 1", [$page]);
  if ($r) {
    $page_id = $r['page_id'];
    return $page_id;
  }
  if ($canCreate) {
    $page_id = db_insert(
      "INSERT INTO pages (name, ctime, mtime)".
      "VALUES(?, ?, ?)",
      [$page, time(), 0]);
    return $page_id;
  }
  return 0;
}

function kona3db_getPageNameById($page_id) {
  global $kona3db_pagenames;
  if (isset($kona3db_pagenames[$page_id])) {
    return $kona3db_pagenames[$page_id];
  }
  if (!isset($kona3db_pagenames)) {
    $kona3db_pagenames = [];
  }
  
  $r = db_get1(
    "SELECT * FROM pages ".
    "WHERE page_id = ? ".
    "LIMIT 1", [$page_id]);
  if ($r) {
    $name = $kona3db_pagenames[$page_id] = $r['name'];
    return $name;
  }
  return '';
}

function kona3db_writePage($page, $body, $user_id=0, $tags = NULL) {
  $page_id = kona3db_getPageId($page, TRUE);
  $hash = kona3getHash($body);
  // check 1 hour history
  $recent_time = time() - (60 * 60 * 1);
  $r = db_get1(
    "SELECT * FROM page_history ".
    "WHERE page_id=? AND mtime > ? AND user_id=?",
    [$page_id, $recent_time, $user_id]);
  if ($r) {
    $history_id = $r['history_id'];
    db_exec(
      "UPDATE page_history SET ".
      "body=?,hash=?,mtime=?,user_id=? ".
      "WHERE history_id=?",
      [$body, $hash, time(), $user_id, $history_id]);
  } else {
    db_exec(
      "INSERT INTO page_history".
      "(page_id,user_id,body,hash,mtime)".
      "VALUES(?,?,?,?,?)",
      [$page_id, $user_id, $body, $hash, time()]);
  }
  // update pages.mtime (for #recent plugin)
  if (trim($body) != "") {
    db_exec(
      "UPDATE pages SET mtime=? WHERE page_id=?",
      [time(), $page_id]);
  } else {
    // remove page 
    // 間違いかもしれないので履歴は削除しない!!
    // 但し更新履歴には出さないようにしたい	  
    db_exec(
      "UPDATE pages SET mtime=0 WHERE page_id=?",
      [$page_id]);
  }
  // tags
  if ($tags != NULL) {
    $tags_a = explode('/', $tags);
    db_exec("DELETE FROM tags WHERE page_id=?", [$page_id]);
    foreach ($tags_a as $name) {
      $name = trim($name);
      db_exec(
        "INSERT INTO tags (page_id, tag, mtime)VALUES(?, ?, ?)", 
        [$page_id, $name, time()]);
    }
  }
  return TRUE;
}

function kona3db_getUserByName($name) {
  // select
  $r = db_get1("SELECT * FROM users WHERE name=?",
    [$name]);
  return $r;
}

function kona3db_getUserById($user_id) {
  // check cache
  global $kona3db_users;
  if (!isset($kona3db_users)) { $kona3db_users = []; }
  if (isset($kona3db_users[$user_id])) {
    return $kona3db_users[$user_id]; 
  }
  if ($user_id == 0) {
    $u = kona3getLoginInfo();
    $Kona3db_users[0] = $u;
    return $u;
  }
  // select
  $r = db_get1("SELECT * FROM users WHERE user_id=?",
    [$user_id]);
  $kona3db_users[$user_id] = $r;
  return $r;
}

function kona3db_getUserNameById($user_id) {
  $u = kona3db_getUserById($user_id);
  return isset($u['name']) ? $u['name'] : '';
}


function kona3db_getPageHistory($page, $edit_token) {
  $page_id = kona3db_getPageId($page);
  $r = db_get(
    "SELECT * FROM page_history ".
    "WHERE page_id=? ORDER BY history_id DESC",
    [$page_id]);
  if ($r) {
    foreach ($r as &$v) {
      $v['user'] = kona3db_getUserNameById($v['user_id']);
      $v['link'] = kona3getPageURL($page, "edit", "",
        kona3getURLParams([
          "q" => "history",
          "history_id" => $v['history_id'],
          "edit_token" => $edit_token
        ]));
      $v['delete_link'] = kona3getPageURL(
        $page, "edit", "",
        kona3getURLParams([
          "cmd" => "history_delete",
          "history_id" => $v['history_id'],
          "hash" => $v['hash'],
          "edit_token" => $edit_token
        ]));
      $v['size'] = strlen($v['body']);
    }
  }
  return $r;
}

function kona3db_getPageHistoryById($history_id) {
  $r = db_get1(
    "SELECT * FROM page_history ".
    "WHERE history_id=?",
    [$history_id]);
  return $r;
}

function kona3db_getPageHistoryByUserId($user_id) {
  $pages = [];
  $r = db_get(
    "SELECT * FROM page_history ".
    "WHERE user_id=? ".
    "ORDER BY history_id DESC LIMIT 50",
    [$user_id]);
  $result = [];
  foreach ($r as $v) {
    $page_id = $v['page_id'];
    if (isset($pages[$page_id])) { continue; }
    $v['page'] = kona3db_getPageNameById($page_id);
    $pages[$page_id] = TRUE;
    $result[] = $v;
  }
  return $result;
}

function kona3db_getMetaStr($key, $def = '')
{
  $meta = db_get1("SELECT * FROM meta WHERE name=?", [$key]);
  if ($meta) {
    return $meta['value_s'];
  }
  return $def;
}

function kona3db_getMetaInt($key, $def = 0)
{
  $meta = db_get1("SELECT * FROM meta WHERE name=?", [$key]);
  if ($meta) {
    return $meta['value_i'];
  }
  return $def;
}

function kona3db_setMeta($key, $value_s, $value_i = 0)
{
  $meta = db_get1("SELECT * FROM meta WHERE name=?", [$key]);
  if ($meta) {
    db_exec("UPDATE meta SET value_s=?, value_i=? WHERE name=?", [$value_s, $value_i, $key]);
  } else {
    db_exec("INSERT INTO meta (name, value_s, value_i) VALUES (?, ?, ?)", [$key, $value_s, $value_i]);
  }
}

function kona3db_setMetaStr($key, $val)
{
  kona3db_setMeta($key, $val, 0);
}

function kona3db_setMetaInt($key, $val)
{
  kona3db_setMeta($key, "", $val);
}

// subdb for plugins
function subdb_exec($sql, $params = array())
{
  $dbname = "subdb";
  return db_exec($sql, $params, $dbname);
}

function subdb_insert($sql, $params = array())
{
  $dbname = "subdb";
  return db_insert($sql, $params, $dbname);
}

function subdb_get($sql, $params = array())
{
  $dbname = "subdb";
  return db_get($sql, $params, $dbname);
}

function subdb_get1($sql, $params = array())
{
  $dbname = "subdb";
  return db_get1($sql, $params, $dbname);
}
