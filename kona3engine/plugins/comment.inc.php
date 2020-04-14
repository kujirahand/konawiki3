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
  // select logs
  $bbs_id = kona3plugins_comment_getBbsId($pdo, $bbsid);
  $stmt = $pdo->prepare('SELECT * FROM comment_list '.
    'WHERE bbs_id=? '.
    'ORDER BY comment_id ASC '.
    'LIMIT 300');
  $stmt->execute(array(intval($bbs_id)));
  $logs = $stmt->fetchAll();
  // render logs
  $html_comments = _renderCommentList($page, $logs);
  $html_form = _renderCommentForm($page, $bbs_id);
  return <<<__EOS__
<!-- #comment plugin -->
<div class="plugin_comment">
  <p class='memo'><a name='CommentBox'>Comment:</a></p>
  <div class='comment_box'>
    {$html_comments}
  </div><!-- end of .comment_box -->
  <div class="comments_form">
    {$html_form}
  </div>
</div><!-- end of .plugin_comment -->
__EOS__;
}

function _renderCommentList($page, $logs) {
  if (!$logs) return "";
  $html = "";
  $index = kona3getPageURL($page, 'plugin', '', 'name=comment');
  $index .= "&";
  foreach ($logs as $row) {
    $id = $row["comment_id"];
    $name = htmlentities($row['name']);
    $body = htmlentities($row['body']);
    $body = str_replace("\n", "<br>", $body);
    $body = str_replace(" ", "&nbsp;", $body);
    $body = preg_replace(
      '#\&gt;(\d+)#',
      '<a href="#comment_id_$1">&gt;$1</a>',
      $body);
    $mtime = ($row['mtime'] == 0)
      ? "-" : date("Y-m-d H:i", $row['mtime']);
    $del = "<a href='{$index}m=del&id=$id'>del</a>";
    $todo_v = $row['todo'];
    $todo_l = ($todo_v == 0) ? "done" : "todo";
    $todo = "<a class='$todo_l' onclick='chtodo(event,$id)'>$todo_l</a>";
    $html .= 
        "<div class='comment_log'>".
          "<div class='comment_title'><a name='comment_id_{$id}'>($id)</a> $name</div>".
          "<div class='comment_body'>$body --- ".
          "<span class='memo'>($mtime) [$del] [$todo]</span></div>".
        "</div><!-- end of .comment_log -->";
  }
  $html .= "";
  return $html;
}

function _renderCommentForm($page, $bbs_id) {
  $form_action = kona3getPageURL($page, 'plugin', '', 'name=comment');
  $def_name = isset($_SESSION['name']) ? $_SESSION['name'] : '';
  $def_pw   = isset($_SESSION['password']) ? $_SESSION['password'] : '';
  $script = _todo_script();
  $msg_post_comment = lang('Post Comment');
  $msg_close = lang('Close');
  return <<< EOS
<div class="comment_form_box">
  <!-- close bar -->
  <div class="comment_close_bar">
    <a href="#CommentBoxForm"
      class="comment_form_close_btn"
      onclick='comment_form_close()'>[{$msg_close}]</a>
  </div>
  <!-- comment form -->
  <form action="$form_action" method="post"
   class="pure-form pure-form-stacked">
    <input type="hidden" name="m" value="write">
    <input type="hidden" name="bbs_id" value="$bbs_id">
    <label for="name">name:</label>
    <input id="name" type="text" name="name" value="$def_name">
    <label for="body">body: <span class="memo">(&gt;1 &gt;2 ...)</span></label>
    <textarea id="body" name="body" rows="4" cols="50"></textarea>
    <label for="password">password</label>
    <input type="password" name="pw" value="$def_pw">
    <input class="pure-button pure-button-primary" type="submit" value="POST">
  </form>
</div><!-- /comment_form_box -->
<div class="msg_post_comment">
  <a href="#CommentBoxForm"
   class="comment_form_open_btn"
   onclick='comment_form_open()'>
      →{$msg_post_comment}</a>
</div><!-- end of .msg_post_comment -->
{$script}\n
EOS;
}


function _err($title, $msg) {
  global $output_format;
  if ($output_format == 'json') {
    echo json_encode(array(
      "result" => "fail",
      "reason" => $title,
      "descripton" => $msg,
    ));
    exit;
  }
  kona3error($title, $msg);
}
function _ok($title, $msg) {
  global $output_format;
  if ($output_format == 'json') {
    echo json_encode(array(
      "result" => "ok",
      "description" => $msg,
    ));
    exit;
  }
  kona3error($title, $msg);
}

// when url = index.php?(page)&plugin
function kona3plugins_comment_action() {
  global $kona3conf, $output_format;
  $page = kona3getPage();
  $m   = kona3param("m", "");
  $output_format = kona3param("fmt", ""); 
  $is_login = kona3isLogin();
  if ($m == "") _err($page, 'No Mode in Comment'); 
  // write comment
  if ($m == "write") {
    kona3plugins_comment_action_write($page);
    return;
  }
  // delete comment (1/2)
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
    _err($page, $del); exit;
  }
  // delete comment (2/2)
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
      if ($output_format == "json") _ok($page, "deleted");
      header('location: index.php?'.urlencode($page));
      exit;
    }
  }
  // set todo
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
    _ok($page, "ok comment_id=$id change to $v"); exit;
  }
  // else
  _err($page, 'Invalid mode'); exit;
}

function kona3plugins_comment_action_write($page) {
  global $output_format;
  $bbs_id = isset($_POST['bbs_id']) ? $_POST['bbs_id'] : '';
  $name = isset($_POST['name']) ? $_POST['name'] : '';
  $body = isset($_POST['body']) ? $_POST['body'] : '';
  $pw   = isset($_POST['pw']) ? $_POST['pw'] : '';

  //
  $bbs_id = intval($bbs_id);
  if ($body == '' || $bbs_id <= 0) {
    _err($page, 'Invalid data'); exit;
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
  
  // show result
  if ($output_format == "json") _ok($page, "inserted"); 
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
    $html .= "<h4><a href='$link#CommentBox'>$bbs_name</a></h4>";
    $html .= "<ul>";
    $index = "index.php?".urlencode($page)."&plugin&name=comment";
    foreach ($list as $row) {
      $id = $row["comment_id"];
      $name = htmlentities($row["name"]);
      $body = htmlentities(mb_substr($row["body"],0, 100));
      $mtime = date("m-d H:i", $row["mtime"]);
      $todo_v = $row["todo"];
      $todo_l = ($todo_v == 0) ? "done" : "todo";
      $todo = "(<a class='$todo_l' onclick='chtodo(event,$id,$todo_v)'>$todo_l</a>)";
      $html .= "<li>".
        "<a href='{$link}#comment_id_{$id}'>($id)</a>".
        "$name - $body ".
        "<span class='memo'>$mtime {$todo}</span>".
        "</li>\n";
    }
    $html .= "</ul>";
  }
  $html .= _todo_script();
  return $html;
}

function _todo_script() {
  global $kona3conf;
  // do not use double
  global $kona3_todo_script;
  if ($kona3_comment_todo_script === TRUE) return "";
  $kona3_comment_todo_script =TRUE;
  //
  $page = $kona3conf['page'];
  $action = "index.php?".urlencode($page)."&plugin&name=comment";
  $script = <<< 'EOS'
function chtodo(event, id) {
  var e = event.target;
  var v = (e.innerHTML == "todo") ? 1 : 0;
  cv = (v == 0) ? 1 : 0;
  var para = {"m": "todo", "id": id, "v": cv, "fmt": "json"};
  $.post(comment_api, para, function(data){
    var o = JSON.parse(data);
    if (o["result"] == "ok") {
      e.innerHTML = (cv == 0) ? "done": "todo";
      $(e).attr('class', (cv == 1) ? 'todo' : 'done');
    } else {
      alert("error:" + o["reason"]);
    }
  });
}
// close form
$(document).ready(function() {
  comment_form_close();
});
function comment_form_open() {
  $(".comment_form_box").show();
  $(".comment_form_open_btn").hide();
  $(".comment_form_close_btn").show();
}
function comment_form_close() {
  $(".comment_form_box").hide();
  $(".comment_form_open_btn").show();
  $(".comment_form_close_btn").hide();
}
EOS;
  $script = <<< EOS
<script type="text/javascript">
var comment_api = "$action";
{$script}
</script>
EOS;
  return $script;
}



