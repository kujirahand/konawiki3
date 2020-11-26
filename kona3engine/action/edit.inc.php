<?php

include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';
include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

function kona3_action_edit() {
  global $kona3conf, $page;

  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "edit");
  $a_mode = kona3param('a_mode', '');
  $i_mode = kona3param('i_mode', 'form'); // or ajax
  $q = kona3param("q");
  $cmd = kona3param("cmd");

  // check permission
  if (!kona3isLogin()) {
    $url = kona3getPageURL($page, 'login');
    $msg = "<a href=\"$url\">Please login.</a>";
    if ($i_mode == 'ajax') {
      $msg = "Please login.";
    }
    kona3_edit_err($msg, $i_mode);
    exit;
  }
  
  // edit_command ?
  if ($cmd != '') { return edit_command($cmd); }

  // load body
  $txt = "";
  // history mode?
  if ($q == 'history') {
    $history_id = kona3param("history_id");
    $r = kona3db_getPageHistoryById($history_id);
    $txt = isset($r['body']) ? $r['body'] : '(empty)';
  }
  else { // normal mode
    $fname = kona3getWikiFile($page, TRUE, '.txt');
    if (!file_exists($fname)) {
      $fname = kona3getWikiFile($page, FALSE, '');
    }
    if (file_exists($fname)) {
      $txt = @file_get_contents($fname);
      // binary?
      $sz = @filesize($fname);
      if (strpos($txt, '\0') !== FALSE && $sz < 1024 * 1024 * 3) { // max 3MB
        $txt = "data:image/gif;base64,".base64_encode($txt);
      }
    }
  }
  $a_hash = kona3getPageHash($txt);

  if ($a_mode == "trywrite") {
    $msg = kona3_trywrite($txt, $a_hash, $i_mode, $result);
  } else if ($a_mode == "trygit") {
    $msg = kona3_trygit($txt, $a_hash, $i_mode);
  } else {
    $msg = "";
  }
  // Ajaxならテンプレート出力しない
  if ($i_mode == 'ajax') return;
  
  // include script
  $kona3conf['js'][] = kona3getResourceURL('edit.js', TRUE);
  $kona3conf['css'][] = kona3getResourceURL('edit.css', TRUE);

  // history
  $history = kona3db_getPageHistory($page);

  // show
  kona3template('edit.html', array(
    "action" => $action,
    "a_hash" => $a_hash,
    "page_title" => $page,
    "edit_txt"  => $txt,
    "msg" => $msg,
    "history" => $history,
  ));
}

// edit command execute
function edit_command($cmd) {
  global $kona3conf, $page;

  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "edit");

  if (!kona3isAdmin()) {
    return kona3error('Not Admin', 'Sorry, you are not Admin.');
  }
  if ($cmd == 'history_delete') {
    $history_id = intval(kona3param("history_id"));
    $hash = kona3param("hash");
    $r = db_exec(
      'DELETE FROM page_history '.
      'WHERE history_id=? AND hash=?',
      [$history_id, $hash]);
    if ($r) {
      $url = kona3getPageURL($page, "edit");
      return kona3showMessage(
        'DELETE History', 
        "OK! (history_id=$history_id) ".
        "<a href='$url'>Continue to edit</a>");
    } else {
      return kona3error('ng', 'Sorry, failed to delete.');
    }
  }
  kona3error('ng', 'Unknown command');
}

function kona3_make_diff($text_a, $text_b) {
  $lines_a = explode("\n", $text_a);
  $lines_b = explode("\n", $text_b);

  $res = array();
  $ia = $ib = 0;
  for (;;) {
    $a = isset($lines_a[$ia]) ? $lines_a[$ia] : NULL;
    $b = isset($lines_b[$ib]) ? $lines_b[$ib] : NULL;
    if ($a === NULL && $b === NULL) break;
    // same
    if ($a == $b) {
      $res[] = $a;
      $ia++; $ib++;
      continue;
    }
    // not same
    if ($a === NULL) {
      $res[] = '>> '.$b;
      $ib++;
      continue;
    }
    if ($b === NULL) {
      $res[] = '<< '.$a;
      $ia++;
      continue;
    }
    //
    $res[] = $a;
    $ia++;
  }
  return implode("\n", $res);
}

function kona3_edit_err($msg, $method = "web") {
  global $page;
  if ($method == "ajax") {
    echo json_encode(array(
      'result' => 'ng',
      'reason' => $msg,
    ));
  } else {
    kona3error($page, $msg);
  }
}

function kona3_conflict($edit_txt, &$txt, $i_mode) {
  // エラーメッセージ
  $msg = lang("Conflict editing, Please submit and check.");
  // ajaxの場合
  if ($i_mode == "ajax") {
    kona3_edit_err($msg, $i_mode);
    return $msg;
  }
  // formの場合
  $msg = "<div class='error'>$msg</div>";
  $txt = kona3_make_diff($edit_txt, $txt);
  return $msg;
}

function kona3_trywrite(&$txt, &$a_hash, $i_mode, &$result) {
  global $kona3conf, $page;

  $edit_txt = kona3param('edit_txt', '');
  $a_hash_frm = kona3param('a_hash', '');

  // has file ext?
  if (preg_match('#\.([a-zA-Z0-9_]+)$#', $page, $m)) {
    $ext = strtolower($m[1]);
    $fname = kona3getWikiFile($page, FALSE, '');
  } else {
    $fname = kona3getWikiFile($page);
  }
  $user_id = kona3getUserId();

  $result = FALSE;
  // check hash
  if ($a_hash_frm !== $a_hash) { // conflict
    return kona3_conflict($edit_txt, $txt, $i_mode);
  }
  // save
  // === for FILE ===
  if (file_exists($fname)) {
    if (!is_writable($fname)) {
      kona3_edit_err(lang('Could not write file.'), $i_mode);
      return "";
    }
  } else {
    $dirname = dirname($fname);
    if (file_exists($dirname)) {
      if (!is_writable(dirname($fname))) {
        kona3_edit_err(lang('Could not write file.'), $i_mode);
        return "";
      }
    } else {
      // auto mkdir ?
      $data_dir = KONA3_DIR_DATA;
      $max_level = $kona3conf['path.max.mkdir'];
      if ($data_dir != substr($dirname, 0, strlen($data_dir))) {
        kona3_edit_err('Invalid File Path.', $i_mode); exit;
      }
      $dirname2 = substr($dirname, strlen($data_dir) + 1);
      $cnt = count(explode("/", $dirname2));
      if ($cnt <= $max_level) { // 3 level directories
        $b = mkdir($dirname, 0777, TRUE);
        if (!$b) {
          kona3_edit_err('mkdir failed, could not use "/"', $i_mode); exit;
        }
      } else {
        kona3_edit_err(
          "Invalid Wiki Name (not allow use '/' over $max_level times)", 
          $i_mode); exit;
      }
    }
  }
  // write
  $bytes = @file_put_contents($fname, $edit_txt);
  if ($bytes === FALSE) {
    $msg = lang('Could not write file.');
    kona3_edit_err($msg, $i_mode);
    $result = FALSE;
    return $msg;
  }

  // === for Database ===
  kona3db_writePage($page, $edit_txt, $user_id);
  
  // result
  if ($i_mode == "ajax") {
    echo json_encode(array(
      'result' => 'ok',
      'a_hash' => kona3getPageHash($edit_txt),
    ));
    return TRUE;
  }
  $jump = kona3getPageURL($page);
  header("location:$jump");
  echo "ok, saved.";
  return TRUE;
}

function kona3_trygit(&$txt, &$a_hash, $i_mode) {
  require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';
  global $kona3conf, $page;

  $edit_txt = kona3param('edit_txt', '');
  $a_hash_frm = kona3param('a_hash', '');
  $fname = kona3getWikiFile($page);
  
  // 先に保存
  kona3_trywrite($txt, $a_hash, $i_mode, $result);
  if (!$result) {
    return;
  }
  
  // Gitが有効?
  if (!$kona3conf["git_enabled"]) {
    return;
  }
  
  // Git操作
  $branch = $kona3conf["git_branch"];
  $remote_repository = $kona3conf["git_remote_repository"];
  $repo = new Cz\Git\GitRepository(dirname($fname));

  if ($repo->getCurrentBranchName() != $branch) {
    $repo->checkout($branch);
  }

  $repo->addFile($fname);
  $repo->commit("Update $page from Konawiki3");
  $repo->push($remote_repository, array($branch));

  // result
  if ($i_mode == "ajax") {
    echo json_encode(array(
      'result' => 'ok',
      'a_hash' => kona3getPageHash($edit_txt),
    ));
    return;
  }
  $jump = kona3getPageURL($page);
  header("location:$jump");
  echo "ok, saved.";
}



