<?php
/**
 * - [書式] #comment(id=bbsid,type=***) 
 * - [引数]
 * -- id=*** ... 掲示板のID
 * -- type=*** ... 掲示板のタイプ(adminで管理モード)
*/
function kona3plugins_comment_execute($params) {
  global $kona3conf;
  
  $page = $kona3conf['page'];
  $bbsid = $page;
  $type = "";
  $pdo = kona3getDB();
  if (!$pdo) {
    return "([#comment] SQLite could not use...)";
  }
  // check params     
  foreach ($params as $s) {
    if (!preg_match('#^(\w+?)\=(.+)$#', $s, $m)) continue;
    list($match, $key, $val) = $m;
    if ($key == "id") {
      $bbsid = $val;
      continue;
    }
    if ($key == "type") {
      $type = $val;
      continue;
    }
  }
  // check table exists?
  kona3plugins_comment_init_db($pdo);
  $bbs_id = kona3plugins_comment_getBbsId($pdo, $bbsid);
  // create log
  $stmt = $pdo->prepare('SELECT * FROM comment_list '.
    'WHERE bbs_id=? '.
    'ORDER BY comment_id ASC '.
    'LIMIT 300');
  $stmt->execute(array(intval($bbs_id)));
  $logs = $stmt->fetchAll();
  if (!$logs) {
    $logs = [["comment_id"=>0, "name"=>"","body"=>"no log", "mtime"=>0]];
  }
  $html = "<div class='comments'>";
  $html .= "<p>Comment:</p><table>";
  $index = "index.php?".urlencode($page)."&plugin&name=comment&";
  foreach ($logs as $row) {
    $id = $row["comment_id"];
    $name = htmlentities($row['name']);
    $body = htmlentities($row['body']);
    $body = str_replace("\n", "<br>", $body);
    $body = str_replace(" ", "&nbsp;", $body);
    $body = preg_replace('#\&gt;(\d+)#', '<a href="#comment_id_$1">&gt;$1</a>', $body);
    $mtime = ($row['mtime'] == 0) ? "-" : date("Y-m-d H:i", $row['mtime']);
    
    $del = "<a href='{$index}m=del&id=$id'>del</a>";
    $html .= 
        "<tr>".
        "<td style='vertical-align:top'>".
          "<a name='comment_id_{$id}'>($id)</a> $name</td>".
        "<td>$body<br>".
          "<span class='memo'>($mtime) [$del]</span></td>".
        "</tr>";
  }
  $html .= "</table>";
  $action = "index.php?".urlencode($page)."&plugin&name=comment";
  $def_name = isset($_SESSION['name']) ? $_SESSION['name'] : '';
  $def_pw   = isset($_SESSION['password']) ? $_SESSION['password'] : '';
  $html .= <<< EOS
    <form action="$action" method="post">
    <input type="hidden" name="m" value="write">
    <input type="hidden" name="bbs_id" value="$bbs_id">
    <p>Form:</p>
    <table>
      <tr><th>name</th>
        <td><input type="text" name="name" value="$def_name"></td></tr>
      <tr><th>body</th>
        <td><textarea name="body" rows=4 cols="40"></textarea><br>
          <span class="memo">&gt;1 &gt;2 ...</span></td></tr>
      <tr><th>password</th>
        <td><input type="password" name="pw" value="$def_pw"></td></tr>
      <tr><th></th><td><input type="submit" value="POST"></td></tr>
    </table>
EOS;
  $html .= "</div><!-- /comment -->";
  return $html;
}

function kona3plugins_comment_action() {
  global $kona3conf;
  $page = kona3getPage();
  $m = isset($_REQUEST['m']) ? $_REQUEST['m'] : '';
  $is_login = kona3isLogin();
  if ($m == "") {
    kona3error($page, 'No mode'); exit;
  }
  if ($m == "write") {
    kona3plugins_comment_action_write($page);
    return;
  }
  if ($m == "del") {
    $id = intval(@$_REQUEST['id']);
    if ($id <= 0) kona3error($page, 'no id');
    $key = $_SESSION['password'];
    $del = "<form method='post'>".
      "<input type='hidden' name='m' value='del2'>".
      "<input type='hidden' name='id' value='$id'>".
      "<p>Really delete (id=$id)?</p>".
      "<p>Delete Key: <input type='password' name='pw' value='$key'>".
      " <input type='submit' value='Delete'></p>".
      "</form>";
    kona3error($page, $del); exit;
  }
  if ($m == "del2") {
    $id = intval(@$_REQUEST['id']);
    $pw = isset($_REQUEST['pw']) ? $_REQUEST['pw'] : '';
    if ($id <= 0) kona3error($page, "no id");
    $pdo = kona3getDB();
    $stmt = $pdo->prepare('SELECT * FROM comment_list WHERE comment_id=?');
    $stmt->execute(array($id));
    $row = $stmt->fetch();
    if ($row['delkey'] === $pw || $is_login) {
      $pdo->exec("DELETE FROM comment_list WHERE comment_id=$id");
      header('location: index.php?'.urlencode($page));
      exit;
    }
  }
  kona3error($page, 'Invalid mode'); exit;
}

function kona3plugins_comment_action_write($page) {
  $bbs_id = isset($_POST['bbs_id']) ? $_POST['bbs_id'] : '';
  $name = isset($_POST['name']) ? $_POST['name'] : '';
  $body = isset($_POST['body']) ? $_POST['body'] : '';
  $pw   = isset($_POST['pw']) ? $_POST['pw'] : '';

  //
  $bbs_id = intval($bbs_id);
  if ($body == '' || $bbs_id <= 0) {
    kona3error($page, 'Invalid data'); exit;
  }
  if ($name == '') $name = 'no name';
  $pdo = kona3getDB();
  $stmt = $pdo->prepare(
    "INSERT INTO comment_list(bbs_id, name, body, delkey, ctime, mtime)".
    "VALUES(?, ?, ?, ?, ?, ?)".
    "");
  $a = array($bbs_id, $name, $body, $pw, time(), time());
  $r = $stmt->execute($a);
  $_SESSION['name'] = $name;
  $_SESSION['password'] = $pw;
  // jump
  header("location: index.php?".urlencode($page));
}


function kona3plugins_comment_init_db($pdo) {
  $sql = <<< EOS
    /* comment table */
    CREATE TABLE IF NOT EXISTS comment_list (
      comment_id INTEGER PRIMARY KEY,
      bbs_id INTEGER DEFAULT 0,
      name TEXT DEFAULT 'no name',
      body TEXT DEFAULT '',
      delkey TEXT DEFAULT '',
      res_id INTEGER DEFAULT -1,
      ctime INTEGER,
      mtime INTEGER
    );
    /* id to bbs_id */
    CREATE TABLE IF NOT EXISTS comment_bbsid(
      bbs_id INTEGER PRIMARY KEY,
      name TEXT DEFAULT ''
    );
EOS;
  $pdo->exec($sql);
}

function kona3plugins_comment_getBbsId($pdo, $name) {
  $stmt = $pdo->prepare('SELECT * FROM comment_bbsid WHERE name=?');
  $stmt->execute(array($name));
  $r = $stmt->fetch();
  if (empty($r['bbs_id'])) { // not exists
    $ins = $pdo->prepare('INSERT INTO comment_bbsid(name)VALUES(?)');
    $ins->execute(array($name));
    $id = $ins->lastInsertId();
  } else {
    $id = $r["bbs_id"];
  }
  return $id;
}




