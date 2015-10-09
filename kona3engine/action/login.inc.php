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
<div>
  {$msg}
  <form method="post" action="$action">
  <input type="hidden" name="a_mode" value="trylogin">
  <table>
    <tr>
      <th>User name:</th>
      <td><input type="text" name="a_user"></th>
    </tr>
    <tr>
      <th>Password:</th>
      <td><input type="password" name="a_pw"></td>
    </tr>
    <tr>
      <th></th>
      <td><input type="submit" value="Login"></th>
    </tr>
  </table>
  </form>
</div>
EOS;
  // show
  kona3template('message', array(
    "page_title" => kona3text2html($page),
    "page_body"  => $form,
  ));
}



