<?php

function kona3_action_user() {
  global $kona3conf;

  // 非公開Wikiでは未ログインで他人の情報を出さない (show と同じ扱い, #324)
  if (kona3getConf('wiki_private') && !kona3isLogin()) {
    header('HTTP/1.0 403 Forbidden');
    $url = kona3getPageURL($kona3conf['page'], "login");
    $msg_private = lang('Private Mode');
    $msg_please_login = lang('Please login.');
    kona3error(
      $kona3conf['page'],
      "<div>$msg_private</div><div>&nbsp;</div>" .
        "<div><a href='$url'>$msg_please_login</a></div>"
    );
    return;
  }

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
  $static_has_output = kona3static_hasOutput();
  $pages = kona3db_getPageHistoryByUserId($user_id);
  $edit_token = kona3_getEditToken('edit_token');
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
    "static_has_output" => $static_has_output,
    // 生成済みの静的サイトへのリンク (#290)。出力が無ければ空文字にして表示しない
    "static_top_url" => $static_has_output ? kona3static_outputTopUrl() : '',
    "static_edit_token" => kona3_getEditToken('edit_token'),
  ]);
}

