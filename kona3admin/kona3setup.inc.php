<?php
@session_start();
header('X-Frame-Options: SAMEORIGIN');

// Auto setup
function konawiki3_setup() {
  kona3setup_check_kona3dir_def();
  kona3setup_check_dirs();
  kona3setup_check_admin_user();
  kona3setup_config();
}

function kona3setup_config() {
  require_once KONA3_DIR_ENGINE.'/kona3login.inc.php';
  require_once KONA3_DIR_ENGINE.'/kona3conf.inc.php';
  if (!kona3isAdmin()) {
    echo "<html><body><a href='index.php?go&login'>Please Login.</a></body></html>";
    exit;
  }
  $file_conf = KONA3_DIR_PRIVATE.'/kona3conf.json.php';
  $conf = jsonphp_load($file_conf, []);
  kona3conf_init($conf);
  $q = empty($_POST['q']) ? '' : $_POST['q'];
  if ($q == '') {
    $conf['edit_token'] = kona3_getEditToken();
    if (isset($_GET['admin'])) {
      $conf['admin_email'] = $_GET['admin'];
    }
    kona3setup_template('admin_conf.html', $conf);
    exit;
  }
  if ($q == 'save') {
    // check token
    if (!kona3_checkEditToken()) {
      kona3error("Invalid Token", "<a href='javascript:history.back()'>Plase back</a>, and submit form again.");
      exit;
    } else {
      unset($conf['edit_key']);
    }
    foreach ($conf as $key => $def) {
      $v = isset($_POST[$key]) ? $_POST[$key] : $def;
      $v = trim($v);
      if (strtolower($v) === 'true') { $v = TRUE; }
      if (strtolower($v) === 'false') { $v = FALSE; }
      $conf[$key] = $v;
    }
    // check parameters
    if (strpos($conf['FrontPage'], '/') !== FALSE) {
      kona3setup_error('FrontPage could not include "/".');
      exit;
    }
    if (trim($conf['FrontPage']) == '') { $conf['FrontPage'] = 'FrontPage'; }
    if (preg_match('#[^a-zA-Z0-9\_\-]#', $conf['skin'])) {
      kona3setup_error('Skin name could not include path flag "/" and special chars.');
      exit;
    }
    // save
    jsonphp_save($file_conf, $conf);
    kona3setup_showMessage('<h1>Saved</h1><p><a href="./index.php">Go to FrontPage.</a></p>');
    exit;
  }
}

function kona3setup_error($msg) {
  if (function_exists('template_render')) {
    kona3showMessage('Error', $msg);
  } else {
    echo "<html><body><h1 style='color:red;'>$msg</h1></body></html>";
  }
  exit;
}
function kona3setup_showMessage($msg) {
  if (function_exists('template_render')) {
    kona3showMessage('Setting', $msg);
  } else {
    echo "<html><body><div style='color:blue;'>$msg</div></body></html>";
  }
  exit;
}

function kona3setup_check_admin_user() {
  require_once KONA3_DIR_ENGINE.'/kona3login.inc.php';
  $file_kona3users_json = KONA3_DIR_PRIVATE.'/kona3adminuser.json.php';
  if (file_exists($file_kona3users_json)) {
    return TRUE;
  }
  
  // check $q (setup mode)
  $q = empty($_POST['q']) ? '' : $_POST['q'];
  if ($q == '') {
    kona3setup_template('admin_user.html', []);
    exit;
  }
  
  if ($q == 'save') {
    $pw = trim(empty($_POST['pw']) ? '' : $_POST['pw']);
    $pw2 = trim(empty($_POST['pw2']) ? '' : $_POST['pw2']);
    if ($pw != $pw2) { echo "The master passwords do not match."; exit; }
    $salt = konawiki3_gen_pw(255);
    $hash = kona3getHash($pw, $salt); // convert to hash
    $userid = trim(empty($_POST['userid']) ? '' : $_POST['userid']);
    jsonphp_save($file_kona3users_json, [
      $userid => [
        'hash' => $hash,
        'salt' => $salt,
      ]]);
    kona3login($userid, $userid, 'admin', $userid);
    $userid_ = urldecode($userid);
    echo "<h1>OK, <a href='index.php?admin={$userid_}'>Go to index page.</a></h1>";
    exit;
  }
  echo 'q param error';
  exit;
}

// easy password
function konawiki3_gen_pw($no) {
  $ch = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_#=|!%&+*@~.";
  $pw = "";
  for ($i = 0; $i < $no; $i++) { $pw .= substr($ch, rand(0, strlen($ch)-1), 1); }
  return $pw;
}

function kona3setup_check_kona3dir_def() {
  $file_kona3dir_def = KONA3_DIR_INDEX.'/kona3dir.def.php';
  if (!file_exists($file_kona3dir_def)) {
    if (is_writable(KONA3_DIR_INDEX)) {
      // auto generate kona3dir.def.php
      $tmp = file_get_contents(__DIR__.'/template-kona3dir.def.php');
      file_put_contents($file_kona3dir_def, $tmp);
    }
  }
}

function kona3setup_check_dirs() {
  if (!defined('KONA3_DIR_CACHE')) {
    define('KONA3_DIR_CACHE', dirname(__DIR__).'/cache');
  }
  // check writable
  $dirs = [
    KONA3_DIR_CACHE,
    KONA3_DIR_PRIVATE,
    KONA3_DIR_DATA
  ];
  // check dirs
  $help_chmod = '';
  foreach ($dirs as $dir) {
    if (!is_writable($dir)) {
      $help_chmod .= "$ chmod +w \"$dir\"<br/>\n";
    }
  }
  // show help
  if ($help_chmod != '') {
    kona3_setup_help(
      "Please change directories permission.<br>".
      "<div class='code'><code>{$help_chmod}</code></div>");
    exit;
  }
  // check config error
  if (KONA3_DIR_PRIVATE == KONA3_DIR_DATA || strpos(KONA3_DIR_PRIVATE, KONA3_DIR_DATA) !== FALSE) {
    kona3_setup_help(
      "KONA3_DIR_PRIVATE and KONA3_DIR_DATA should be different directories.<br>".
      "<div class='code'><code>KONA3_DIR_PRIVATE = ".KONA3_DIR_PRIVATE."<br>".
      "KONA3_DIR_DATA = ".KONA3_DIR_DATA."</code></div>");
    exit;
  }
}

function kona3setup_template($file, $params) {
  global $FW_TEMPLATE_PARAMS, $DIR_TEMPLATE_CACHE, $DIR_TEMPLATE;
  $FW_TEMPLATE_PARAMS = [];
  $DIR_TEMPLATE_CACHE = KONA3_DIR_CACHE;
  $DIR_TEMPLATE = __DIR__.'/template';
  include_once KONA3_DIR_ENGINE.'/fw_simple/fw_template_engine.lib.php';
  template_render($file, $params);
}

// help function
function kona3_setup_help($msg) {
  $url = 'https://kujirahand.com/konawiki3/index.php?install';
  echo '<!DOCTYPE html><html><body>';
  echo '<style>.code{ padding:0.5em; line-height:1.2em; font-size:1em; background-color: black; color:white; }</style>';
  echo "<h1>Please setup KonaWiki3.</h1>";
  echo "<p style='color:red;'>$msg</p>";
  echo "<p><a href='$url'>How to Install?</a></p>";
  echo '</body></html>';
  exit;
}

