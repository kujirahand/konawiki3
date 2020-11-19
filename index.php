<?php
// ----------------------------------------------------
// konawiki3 - index.php
// ----------------------------------------------------
define('KONA3_FILE_CONFIG', 'konawiki3.ini.php');

// Read config file
$file_config = dirname(__FILE__).'/'.KONA3_FILE_CONFIG;
if (!file_exists($file_config)) {
  konawiki3_setup(); exit;
}
require_once($file_config);

// Include kona3engine/index.inc.php
if (defined("KONA3_DIR_ENGINE")) {
  $engine_index = KONA3_DIR_ENGINE."/index.inc.php";
} else {
  $engine_index = dirname(__FILE__)."/kona3engine/index.inc.php";
}
if (!file_exists($engine_index)) {
  echo "<h1>Sorry, engine not exists...</h1>"; exit;
}
require_once($engine_index);

function konawiki3_setup() {
  $q = empty($_POST['q']) ? '' : $_POST['q'];
  if ($q == '') {
    $pw = ''; // default password
    echo "<!DOCTYPE html><html><body style='background-color:#eee;'><form method='POST'>";
    echo "<h1>KONAWIKI3 ADMIN SETUP</h1><div style='padding:1em;'>";
    echo "User ID:<br><input type='text' name='userid' value='admin@example.com'><br>";
    echo "User Password:<br><input type='text' name='pw' value='$pw'><br>";
    echo "Password (confirm):<br><input type='text' name='pw2' value='$pw'>";
    echo "<input type='hidden' name='q' value='save'><br>";
    echo "<input type='submit' value='Save'>";
    echo "</div></form><body></html>\n";
    return;
  }
  if ($q == 'save') {
    $pw = empty($_POST['pw']) ? '' : $_POST['pw'];
    $pw2 = empty($_POST['pw2']) ? '' : $_POST['pw2'];
    if ($pw != $pw2) { echo "Wrong Master Password"; exit; }
    $salt = konawiki3_gen_pw(20);
    define('KONA3_PASSWORD_SALT', $salt);
    $pw = konawiki3_get_hash($pw); // convert to hash
    $userid = empty($_POST['userid']) ? '' : $_POST['userid'];
    $txt = file_get_contents('tmp-konawiki3.ini.php');
    $b64 = base64_encode(json_encode([$userid=>$pw]));
    $txt = str_replace('##WIKI_USERS##', "base64:$b64", $txt);
    $txt = str_replace('##WIKI_SALT##', $salt, $txt);
    file_put_contents("konawiki3.ini.php", $txt);
    header('location: ./index.php');
    echo "<!DOCTYPE html><body><h1>Please reload</h1></body>";
    return;
  }
}

// easy password
function konawiki3_gen_pw($no) {
  $ch = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_#$!%&+*@~.";
  $pw = "";
  for ($i = 0; $i < $no; $i++) { $pw .= substr($ch, rand(0, strlen($ch)-1), 1); }
  return $pw;
}

// Get Hash
function konawiki3_get_hash($password) {
  $s = KONA3_PASSWORD_SALT . "::" . $password;
  return hash("sha512", $s, FALSE);
}



