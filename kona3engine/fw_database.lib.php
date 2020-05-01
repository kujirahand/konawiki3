<?php
// DATABASE
global $users_cache;
$users_cache = [];

function database_get() {
  global $DB_MAIN, $FILE_DATABASE;
  // 既にオープンしたか確認
  if ($DB_MAIN) {
    return $DB_MAIN;
  }
  // Open
  $need_init = FALSE;
  if (!file_exists($FILE_DATABASE)) {
    $need_init = TRUE;
  }
  $pdo = $DB_MAIN = new PDO("sqlite:$FILE_DATABASE");
  // エラーで例外を投げる
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  // 連想配列を返す
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  // 生成
  if ($need_init) {
    $file = __DIR__.'/init.sql';
    $sql = file_get_contents($file);
    $pdo->exec($sql);
  }
  return $pdo;
}

function db_exec($sql, $params = array()) {
  $db = database_get();
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  return $db;
}

function db_get($sql, $params = array()) {
  $db = database_get();
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $r = $stmt->fetchAll();
  return $r;
}



