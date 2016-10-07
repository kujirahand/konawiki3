<?php

include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';
include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

function kona3_action_edit() {
  global $kona3conf;

  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "edit");
  $a_mode = kona3param('a_mode', '');
  $i_mode = kona3param('i_mode', 'form'); // ajax

  // check permission
  if (!kona3isLogin()) {
    if ($i_mode == "ajax") {
      echo json_encode(array('result'=>'ng', 'reason'=>'no login')); exit;
    } else {
      $url = kona3getPageURL($page, 'login');
      kona3error($page, "<a href='$url'>Please login.</a>"); exit;
    }
  }


  $fname = kona3getWikiFile($page);
  $msg = "";

  // load body
  $txt = "";
  if (file_exists($fname)) {
    $txt = @file_get_contents($fname);
  }
  $a_hash = hash('sha256', $txt); 

  if ($a_mode == "trywrite") {
    $a_hash_frm = kona3param('a_hash', '');
    $edit_txt = kona3param('edit_txt', '');
    // check hash
    if ($a_hash_frm == $a_hash) {
      // save
      file_put_contents($fname, $edit_txt);
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
    } else {
      if ($i_mode == "ajax") {
        echo json_encode(array(
          'result' => 'ng',
          'reason' => 'Conflict editing, Please submit and check.',
        ));
      }
      $msg = "<div class='error'>Sorry, ".
          "Conflict editing. Failed to save. ".
          " Please check page and save again.</div>";
      $txt = kona3_make_diff($edit_txt, $txt);
    }
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
      $res[] = '>< '.$a;
      $ia++;
      continue;
    }
    //
    $res[] = $a;
    $ia++;
  }
  return implode("\n", $res);
}





