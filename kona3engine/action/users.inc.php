<?php

function kona3_action_users() {
  if (!kona3isAdmin()) {
    kona3error(
      lang('Admin Page'),
      lang('You do not have admin perm.'));
    exit;
  }
  // Check Params
  $q = kona3param('q');
  if ($q == 'disable') {
    return users_disable(0);
  }
  elseif ($q == 'enable') {
    return users_disable(1);
  }

  $users = db_get(
    "SELECT * FROM users ".
    "ORDER BY user_id DESC");
  foreach ($users as &$u) {
    $user_id = $u['user_id'];
    $token = $u['token'];
    $email = $u['email'];
    $u['enable_link'] = kona3getPageURL(
      'go', 'users', '', 
      "q=enable&user_id=$user_id&token=$token");
    $u['disable_link'] = kona3getPageURL(
      'go', 'users', '', 
      "q=disable&user_id=$user_id&token=$token");
    $u['delete_link'] = kona3getPageURL(
      'go', 'users', '', 
      "q=delete&user_id=$user_id&token=$token");
  }
  kona3template("users.html",[
    "users" => $users,
  ]);
}

function users_disable($value) {
  $user_id = intval(kona3param('user_id', 0));
  $token = kona3param('token');
  $r = db_get1(
    "SELECT * FROM users WHERE user_id=? AND token=?",
    [$user_id, $token]);
  if (!$r) {
    return kona3error('Failed', 'Failed to change disable');
  }
  db_exec("UPDATE users SET enabled=$value WHERE user_id=?",
    [$user_id]);
  redirect(kona3getPageURL('ok', 'users'));
}




