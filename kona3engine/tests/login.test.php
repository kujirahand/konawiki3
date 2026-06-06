<?php
require_once __DIR__ . '/test_common.inc.php';

// login test
test_eq(__LINE__, kona3isLogin(), FALSE, "login test");
test_eq(__LINE__, kona3getHash('abcd', ''), "8131f5dbead7a23afa3a57a7249ab4e6b9ba4f8905ffd07bed0f7d6a92a4730480c6aa939f9c3a333caba7890583fa29e5546c34f35d619238683ccfdee4a6e0", "login hash");
test_eq(__LINE__, kona3getHash('abcd', 'salt#aaa'), "b5bc0d50708f1c90e095494f648d91495c645c20db31d443f80f42c847e5b9afb98c7de4c6489a9563e1523b91f295acbc8f58b9eca84d2461ed3200b5f9ecfa", "login hash with salt");

$rememberUserId = 151001;
$rememberEmail = 'remember@example.com';
db_exec("DELETE FROM users WHERE user_id=? OR email=?", [$rememberUserId, $rememberEmail]);
db_exec(
    "INSERT INTO users (user_id,name,email,password,perm,enabled,ctime,mtime) VALUES (?,?,?,?,?,?,?,?)",
    [$rememberUserId, 'remember-user', $rememberEmail, '', 'normal', 1, time(), time()]
);
kona3login('remember-user', $rememberEmail, 'normal', $rememberUserId);
$issued = kona3remember_issue();
test_assert(__LINE__, is_array($issued), "remember token issued");
$row = db_get1(
    "SELECT * FROM remember_tokens WHERE selector=?",
    [$issued['selector']],
    'remember'
);
test_assert(__LINE__, is_array($row), "remember token saved");
test_eq(__LINE__, $row['token_hash'], hash('sha256', $issued['token']), "remember token hash saved");
test_ne(__LINE__, $row['token_hash'], $issued['token'], "remember raw token is not saved");
test_eq(__LINE__, strpos($_COOKIE[KONA3_REMEMBER_COOKIE], ':'), strlen($issued['selector']), "remember cookie selector format");

kona3login('remember-user', $rememberEmail, 'normal', $rememberUserId);
$reissued = kona3remember_issue();
$oldRow = db_get1(
    "SELECT * FROM remember_tokens WHERE selector=?",
    [$issued['selector']],
    'remember'
);
test_eq(__LINE__, $oldRow, FALSE, "old remember token is revoked when issuing a new token");
test_assert(__LINE__, is_array($reissued), "remember token reissued");

unset($_SESSION[KONA3_SESSKEY_LOGIN]);
$oldCookie = $reissued['selector'] . ':' . $reissued['token'];
$_COOKIE[KONA3_REMEMBER_COOKIE] = $oldCookie;
test_eq(__LINE__, kona3remember_tryAutoLogin(), TRUE, "remember auto login succeeds");
test_eq(__LINE__, kona3isLogin(), TRUE, "remember auto login creates session");
test_eq(__LINE__, kona3getUserId(), $rememberUserId, "remember auto login restores current user");
test_ne(__LINE__, $_COOKIE[KONA3_REMEMBER_COOKIE], $oldCookie, "remember token rotates");
$newCookie = $_COOKIE[KONA3_REMEMBER_COOKIE];

unset($_SESSION[KONA3_SESSKEY_LOGIN]);
$_COOKIE[KONA3_REMEMBER_COOKIE] = $oldCookie;
test_eq(__LINE__, kona3remember_tryAutoLogin(), FALSE, "old remember token is rejected after rotation");
test_eq(__LINE__, kona3isLogin(), FALSE, "old remember token does not create session");

$_COOKIE[KONA3_REMEMBER_COOKIE] = $newCookie;
db_exec("UPDATE users SET enabled=0 WHERE user_id=?", [$rememberUserId]);
test_eq(__LINE__, kona3remember_tryAutoLogin(), FALSE, "disabled user remember token is rejected");
test_eq(__LINE__, kona3isLogin(), FALSE, "disabled user remember token does not create session");

db_exec("UPDATE users SET enabled=1 WHERE user_id=?", [$rememberUserId]);
kona3login('remember-user', $rememberEmail, 'normal', $rememberUserId);
$logoutToken = kona3remember_issue();
$_COOKIE[KONA3_REMEMBER_COOKIE] = $logoutToken['selector'] . ':' . $logoutToken['token'];
kona3remember_logout();
$parsed = kona3remember_parseCookie($logoutToken['selector'] . ':' . $logoutToken['token']);
$deleted = db_get1(
    "SELECT * FROM remember_tokens WHERE selector=?",
    [$parsed['selector']],
    'remember'
);
test_eq(__LINE__, $deleted, FALSE, "remember token deleted on logout");
test_assert(__LINE__, empty($_COOKIE[KONA3_REMEMBER_COOKIE]), "remember cookie cleared on logout");
db_exec("DELETE FROM users WHERE user_id=? OR email=?", [$rememberUserId, $rememberEmail]);
