<?php
session_start(); // セッションを開始

// セッション変数をすべてクリア
$_SESSION = array();

// セッションのクッキーがあれば削除
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// セッションを破棄
session_destroy();

echo "<h1>RESET</h1>";

