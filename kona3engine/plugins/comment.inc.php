<?php
/**
 * - [書式] #comment(id=bbsid,type=***) 
 * - [引数]
 * -- id=*** ... 掲示板のID
 * -- type=*** ... 掲示板のタイプ(allで全部表示/todoでTODOのもの)
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
  if ($type == "all") {
    return _at_all($pdo, 'all');
  } else if ($type == "todo") {
    return _at_all($pdo, 'todo');
  }
  // create log
  $bbs_id = kona3plugins_comment_getBbsId($pdo, $bbsid);
  $stmt = $pdo->prepare('SELECT * FROM comment_list '.
    'WHERE bbs_id=? '.
    'ORDER BY comment_id ASC '.
    'LIMIT 300');
  $stmt->execute(array(intval($bbs_id)));
  $logs = $stmt->fetchAll();
  $html = "<div class='comments'>";
  if (!$logs) {
    $html .= "";
  } else {
    $html .= "<p class='memo'>Comment:</p><table>";
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
      $todo_v = $row['todo'];
      $todolink = $index."m=todo&id=$id&v=";
      if ($todo_v == 0) { // done
        $todo = "<a href='{$todolink}1'>done</a>";
      } else {
        $todo = "<a href='{$todolink}0'>todo</a>";
      }
      $html .= 
          "<tr>".
          "<td style='vertical-align:top'>".
            "<a name='comment_id_{$id}'>($id)</a> $name</td>".
          "<td>$body<br>".
            "<span class='memo'>($mtime) [$del] [$todo]</span></td>".
          "</tr>";
    }
    $html .= "</table>";
  }
  $action = "index.php?".urlencode($page)."&plugin&name=comment";
  $def_name = isset($_SESSION['name']) ? $_SESSION['name'] : '';
  $def_pw   = isset($_SESSION['password']) ? $_SESSION['password'] : '';
  $html .= <<< EOS
    <form action="$action" method="post">
    <input type="hidden" name="m" value="write">
    <input type="hidden" name="bbs_id" value="$bbs_id">
    <p class="memo">Comment Form:</p>
    <table>
      <tr><th>name</th>
        <td><input type="text" name="name" value="$def_name"></td></tr>
      <tr><th>body</th>
        <td><textarea name="body" rows=4 cols="80"></textarea><br>
          <span class="memo">&gt;1 &gt;2 ...</span></td></tr>
      <tr><th>password</th>
        <td><input type="password" name="pw" value="$def_pw"></td></tr>
      <tr><th></th><td><input type="submit" value="POST"></td></tr>
    </table>
EOS;
  $html .= "</div><!-- /comment -->";
  return $html;
}

// when url = index.php?(page)&plugin
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
      "<p>password: <input type='password' name='pw' value='$key'>".
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
  if ($m == "todo") {
    $id = intval(@$_REQUEST['id']);
    if ($id < 0) kona3error($page, "no id");
    $v = isset($_REQUEST['v']) ? intval($_REQUEST['v']) : -1;
    if ($v < 0) kona3error($page, "no v param");
    $pdo = kona3getDB();
    $stmt = $pdo->prepare(
      'UPDATE comment_list SET todo=? '.
      '  WHERE comment_id=?');
    $stmt->execute(array($v, $id));
    $v = ($v == 1) ? "todo" : "done";
    kona3error($page, "ok comment_id=$id change to $v");
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
      res_id INTEGER DEFAULT 0,
      todo INTEGER DEFAULT 1, /* 0:done 1:to do */  
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
  if (!isset($r['bbs_id'])) { // not exists
    $ins = $pdo->prepare('INSERT INTO comment_bbsid(name)VALUES(?)');
    $ins->execute(array($name));
    $id = $pdo->lastInsertId();
  } else {
    $id = $r["bbs_id"];
  }
  return $id;
}


function _at_all($pdo, $type) {
  $page = kona3getPage();
  $q = $pdo->query('SELECT * FROM comment_bbsid');
  $allbbs = $q->fetchAll();
  $html = "<h3>Comments (type=$type)</h3>";
  foreach ($allbbs as $row) {
    $bbs_id = $row["bbs_id"];
    $bbs_name = htmlentities($row["name"]);
    $link = kona3getPageURL($row["name"]);
    $where = "";
    if ($type == "todo") {
      $where = " AND todo=1";
    }
    $stmt = $pdo->prepare(
      "SELECT * FROM comment_list ".
      "  WHERE bbs_id=? $where ".
      "  ORDER BY comment_id DESC ".
      "  LIMIT 30"); // 最新の30件
    $stmt->execute(array($bbs_id));
    $list = $stmt->fetchAll();
    if (count($list) == 0) continue;
    $html .= "<h4><a href='$link'>$bbs_name</a></h4>";
    $html .= "<ul>";
    $index = "index.php?".urlencode($page)."&plugin&name=comment";
    foreach ($list as $row) {
      $id = $row["comment_id"];
      $name = htmlentities($row["name"]);
      $body = htmlentities(mb_substr($row["body"],0, 100));
      $mtime = date("m-d H:i", $row["mtime"]);
      $todolink = $index."&m=todo&v=0&id=$id";
      $todo = ($row["todo"] == 0) ? "" : "(<a href='$todolink'>todo</a>)";
      $html .= "<li>$name - $body <span class='memo'>($mtime){$todo}</span></li>";
    }
    $html .= "</ul>";
  }
  return $html;
}

