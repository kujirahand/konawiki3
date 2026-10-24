<?php

function kona3_action_user() {
  global $kona3conf;

  $name = $kona3conf['page'];
  $u = kona3db_getUserByName($name);
  // admin user?
  if (!$u) {
    $admin_users = kona3getAdminUsers();
    if (isset($admin_users[$name])) {
      $u = [
        "user_id" => 0,
        "user" => $name,
        "name" => $name,
      ];
    }
  }
  if (!$u) {
    return kona3error('NG', 'Sorry, unknown user');
  }
  $user_id = $u['user_id'];
  $pages = kona3db_getPageHistoryByUserId($user_id);
  $edit_token = kona3_getEditToken();
  // 自分自身のページかどうか (一覧から他人のページを開く場合もある)
  $loginInfo = kona3getLoginInfo();
  $is_me = $loginInfo && isset($loginInfo['user']) && $loginInfo['user'] === $name;
  kona3template("user.html",[
    "pages" => $pages,
    "user_id" => $user_id,
    "name" => $name,
    "is_me" => $is_me,
    "userList_link" => kona3getPageURL($name, 'users', '', "edit_token=$edit_token"),
    "emailLogs_link" => kona3getPageURL($name, 'emailLogs'),
    "editConf_link" => kona3getPageURL($name, 'editConf'),
    "static_link" => kona3getPageURL($name, 'static'),
    "static_enable" => !empty($kona3conf['static_enable']),
    "static_has_output" => kona3static_hasOutput(),
    "static_edit_token" => kona3_getEditToken('edit_token'),
  ]);
}

