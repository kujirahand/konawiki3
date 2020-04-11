<?php

include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';
include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

function kona3_action_edit() {
  global $kona3conf, $page;

  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "edit");
  $a_mode = kona3param('a_mode', '');
  $i_mode = kona3param('i_mode', 'form'); // or ajax

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

  // load body
  $txt = "";
  $fname = kona3getWikiFile($page);
  if (file_exists($fname)) {
    $txt = @file_get_contents($fname);
  }
  $a_hash = hash('sha256', $txt); 

  if ($a_mode == "trywrite") {
    $msg = kona3_trywrite($txt, $a_hash, $i_mode);
  } else {
    $msg = "";
  }

  // show
  kona3template('edit', array(
    "action" => $action,
    "a_hash" => $a_hash,
    "page_title" => kona3text2html($page),
    "page_body"  => kona3text2html($txt),
    "msg" => $msg,
  ));
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
  if ($i_mode == "ajax") {
    kona3_edit_err("Conflict editing, Please submit and check.", $i_mode);
    exit;
  }
  $msg = 
      "<div class='error'>Sorry, Conflict editing. Failed to save. ".
      "Please check page and save again.</div>";
  $txt = kona3_make_diff($edit_txt, $txt);
  return $msg;
}

function kona3_trywrite(&$txt, &$a_hash, $i_mode) {
  require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';
  global $kona3conf, $page;

  $edit_txt = kona3param('edit_txt', '');
  $a_hash_frm = kona3param('a_hash', '');
  $fname = kona3getWikiFile($page);
  
  // check hash
  if ($a_hash_frm !== $a_hash) { // conflict
    return kona3_conflict($edit_txt, $txt, $i_mode);
  }
  // save
  if (file_exists($fname)) {
    if (!is_writable($fname)) {
      kona3_edit_err('Could not write file.', $i_mode);
      exit;
    }
  } else {
    $dirname = dirname($fname);
    if (file_exists($dirname)) {
      if (!is_writable(dirname($fname))) {
        kona3_edit_err('Could not write file. Permission denied.', $i_mode);
        exit;
      }
    } else {
      // auto mkdir ?
      $data_dir = $kona3conf['path.data'];
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
  file_put_contents($fname, $edit_txt);

  if ($kona3conf["git.enabled"]) {
    $branch = $kona3conf["git.branch"];
    $remote_repository = $kona3conf["git.remote_repository"];
    $repo = new Cz\Git\GitRepository(dirname($fname));

    if ($repo->getCurrentBranchName() != $branch) {
      $repo->checkout($branch);
    }

    $repo->addFile($fname);
    $repo->commit("Update $page from Konawiki3");
    $repo->push($remote_repository, array($branch));
  }

  // result
  if ($i_mode == "ajax") {
    echo json_encode(array(
      'result' => 'ok',
      'a_hash' => hash('sha256', $edit_txt),
    ));
    exit;
  }
  $jump = kona3getPageURL($page);
  header("location:$jump");
  echo "ok, saved.";
}





