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
      $m_success = lang('Success to login.');
      $msg = "<a href='$editLink'>$m_success</a>";
      kona3login();
      kona3showMessage($page, $msg);
      exit;
    } else {
      // ng
      $m_invalid = lang('Invalid User or Password.');
      $msg = "<div class=\"error\">$m_invalid</div>";
    }
  }
  
  // show form
  $m_user = lang('User');
  $m_pass = lang('Password');
  $m_login = lang('Login');
  $form = <<<EOS
<div id="loginform">
  {$msg}
  <form
    class="pure-form pure-form-stacked" 
    method="post" action="$action">
    <input type="hidden" name="a_mode" value="trylogin">
    <label for="user">$m_user:</label>
    <input id="user" type="text" name="a_user">
    <label for="pass">$m_pass:</label>
    <input id="pass" type="password" name="a_pw">
    <input class="pure-button pure-button-primary"
      type="submit" value="$m_login">
  </form>
</div>
EOS;
  // show
  kona3template('message.html', array(
    "page_title" => kona3text2html($page),
    "page_body"  => $form,
  ));
}



