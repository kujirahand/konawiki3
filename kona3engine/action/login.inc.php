<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';

function kona3_action_login() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "login");
  
  $am   = kona3param('a_mode', '');
  $user = kona3param('a_user', '');
  $pw   = kona3param('a_pw',   '');

  $msg = '';

  // check user
  if ($am == "trylogin") {
    $users = $kona3conf['users'];
    if (isset($users[$user]) && $users[$user] == $pw) {
      // ok
      $editLink = kona3getPageURL($page, 'edit');
      $msg = "<a href='$editLink'>Success to login.</a>";
      kona3login();
      kona3showMessage($page, $msg);
      exit;
    } else {
      // ng
      $msg = '<div class="error">Invalid User or Password.</div>';
    }
  }
  
  // show form
  $form = <<<EOS
<div id="loginform">
  {$msg}
  <form method="post" action="$action">
  <input type="hidden" name="a_mode" value="trylogin">
  <p>
    <label for="user">User:</label><br>
    <input id="user" type="text" name="a_user">
  </p>
  <p>
    <label for="pass">Password:</label><br>
    <input id="pass" type="password" name="a_pw">
  </p>
  <p><input type="submit" value="Login"></p>
  </form>
</div>
EOS;
  // show
  kona3template('message', array(
    "page_title" => kona3text2html($page),
    "page_body"  => $form,
  ));
}



