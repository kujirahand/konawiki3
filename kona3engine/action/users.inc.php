<?php

require_once dirname(__DIR__) . '/kona3lib.inc.php';
header('X-Frame-Options: SAMEORIGIN');

function kona3_action_users()
{
    if (!kona3isAdmin()) {
        kona3error(
            lang('Admin Page'),
            lang('You do not have admin perm.')
        );
        exit;
    }
    // check edit_token
    if (!kona3_checkEditToken()) {
        $edit_token = kona3_getEditToken();
        $url = kona3getPageURL('all', 'users', '', "edit_token=$edit_token");
        kona3error(lang('Invalid Token'), "<a href='$url'>Try again.</a>");
        exit;
    }

    // Check Params
    $q = kona3param('q');
    if ($q == 'disable') {
        return users_updateParam("disable");
    } elseif ($q == 'enable') {
        return users_updateParam("enable");
    } elseif ($q == 'delete') {
        return users_updateParam("delete");
    } elseif ($q == 'normal') {
        return users_updateParam("normal");
    } elseif ($q == 'admin') {
        return users_updateParam("admin");
    }

    $edit_token = kona3_getEditToken();
    $users = db_get(
        "SELECT * FROM users " .
            "ORDER BY user_id DESC"
    );
    foreach ($users as &$u) {
        $user_id = $u['user_id'];
        $name = $u['name'];
        $token = $u['token'];
        $email = $u['email'];
        // update status link
        $u['perm_normal_link'] = kona3getPageURL(
            'go',
            'users',
            '',
            "q=normal&user_id=$user_id&token=$token&edit_token=$edit_token"
        );
        $u['perm_admin_link'] = kona3getPageURL(
            'go',
            'users',
            '',
            "q=admin&user_id=$user_id&token=$token&edit_token=$edit_token"
        );
        $u['enable_link'] = kona3getPageURL(
            'go',
            'users',
            '',
            "q=enable&user_id=$user_id&token=$token&edit_token=$edit_token"
        );
        $u['disable_link'] = kona3getPageURL(
            'go',
            'users',
            '',
            "q=disable&user_id=$user_id&token=$token&edit_token=$edit_token"
        );
        $u['delete_link'] = kona3getPageURL(
            'go',
            'users',
            '',
            "q=delete&user_id=$user_id&token=$token&edit_token=$edit_token"
        );
        // edited pages
        $pages = kona3db_getPageHistoryByUserId($user_id);
        $u['pages'] = $pages;
        $u['user_link'] = kona3getPageURL(
            $name,
            'user',
            '',
            "edit_token=$edit_token"
        );
    }
    kona3template("users.html", [
        "users" => $users,
    ]);
}

function users_updateParam($value)
{
    $user_id = intval(kona3param('user_id', 0));
    $token = kona3param('token');
    // check token
    $r = db_get1(
        "SELECT * FROM users WHERE user_id=? AND token=?",
        [$user_id, $token]
    );
    if (!$r) {
        return kona3error('Failed', 'Failed to change User status.');
    }
    // update
    if ($value == "delete") {
        db_exec(
            "DELETE FROM users WHERE user_id=?",
            [$user_id]
        );
    } else if ($value == "enable") {
        db_exec(
            "UPDATE users SET enabled=1 WHERE user_id=?",
            [$user_id]
        );
    } else if ($value == "disable") {
        db_exec(
            "UPDATE users SET enabled=0 WHERE user_id=?",
            [$user_id]
        );
    } else if ($value == "admin") {
        db_exec(
            "UPDATE users SET perm='admin' WHERE user_id=?",
            [$user_id]
        );
    } else if ($value == "normal") {
        db_exec(
            "UPDATE users SET perm='normal' WHERE user_id=?",
            [$user_id]
        );
    }
    $edit_token = kona3_getEditToken();
    redirect(kona3getPageURL('ok', 'users', '', "edit_token=$edit_token"));
}
