<?php
require_once dirname(__DIR__).'/kona3engine/kona3login.inc.php';

// Auto setup for konawiki3.ini.php
function konawiki3_setup($file_config) {
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
    $pw = kona3getHash($pw); // convert to hash
    $userid = empty($_POST['userid']) ? '' : $_POST['userid'];
    $txt = file_get_contents('tmp-konawiki3.ini.php');
    $b64 = base64_encode(json_encode([$userid=>$pw]));
    $txt = str_replace('__KONA3_WIKI_USERS__', "base64:$b64", $txt);
    $txt = str_replace('__KONA3_PASSWORD_SALT__', $salt, $txt);
    file_put_contents($file_config, $txt);
    header('location: ./index.php');
    echo "<!DOCTYPE html><body><h1>Please reload</h1></body>";
    return;
  }
}
// easy password
function konawiki3_gen_pw($no) {
  $ch = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_#=|!%&+*@~.";
  $pw = "";
  for ($i = 0; $i < $no; $i++) { $pw .= substr($ch, rand(0, strlen($ch)-1), 1); }
  return $pw;
}
