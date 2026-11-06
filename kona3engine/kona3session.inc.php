<?php
// --- kona3session.inc.php ---
// セッションの初期化を担当する (Issue #386)。
// トークンの有効期間 (kona3_getEditToken の1日) とセッションの寿命を揃え、
// 保存先を Wiki 専用 (private/sessions) にして、OS の sessionclean や
// 保存先を共有する他アプリの GC に消されないようにする。
// 詳細は設計書 (セッション寿命とトークン有効期間の整合化) を参照。

if (!defined('KONA3_SESSION_DEFAULT_NAME')) {
    define('KONA3_SESSION_DEFAULT_NAME', 'kona3session');
}
if (!defined('KONA3_SESSION_DEFAULT_LIFETIME')) {
    define('KONA3_SESSION_DEFAULT_LIFETIME', 86400); // kona3_getEditToken の $limit と揃える
}
if (!defined('KONA3_SESSION_DIR_NAME')) {
    define('KONA3_SESSION_DIR_NAME', 'sessions');
}
if (!defined('KONA3_SESSION_GC_PROBABILITY')) {
    define('KONA3_SESSION_GC_PROBABILITY', 1);
}
if (!defined('KONA3_SESSION_GC_DIVISOR')) {
    define('KONA3_SESSION_GC_DIVISOR', 100);
}
if (!defined('KONA3_SESSION_COOKIE_REFRESH_INTERVAL')) {
    define('KONA3_SESSION_COOKIE_REFRESH_INTERVAL', 3600); // Cookie 再発行の最小間隔 (秒)
}

// $kona3conf の設定 (session_name / session_lifetime /
// session_persistent_cookie / session_save_path) を読んでセッションを開始する。
// kona3index_main() から、設定の読み込み後に呼ぶこと。
function kona3session_start()
{
    global $kona3conf;

    $name = isset($kona3conf['session_name'])
        ? $kona3conf['session_name']
        : KONA3_SESSION_DEFAULT_NAME;
    $lifetime = isset($kona3conf['session_lifetime'])
        ? $kona3conf['session_lifetime']
        : KONA3_SESSION_DEFAULT_LIFETIME;
    $persistent = isset($kona3conf['session_persistent_cookie'])
        ? $kona3conf['session_persistent_cookie']
        : TRUE;
    $saveSetting = isset($kona3conf['session_save_path'])
        ? $kona3conf['session_save_path']
        : '';

    $privateDir = defined('KONA3_DIR_PRIVATE')
        ? KONA3_DIR_PRIVATE
        : dirname(__DIR__) . '/private';

    $reason = NULL;
    $name = kona3session_resolveName($name, $privateDir);
    $savePath = kona3session_resolveSavePath($saveSetting, $privateDir, $reason);
    // handler_not_files は redis 等の正常な構成なので記録しない
    if ($reason !== NULL && $reason !== 'handler_not_files' && empty($_COOKIE[$name])) {
        // 毎リクエストの記録を避けるため、新規セッションのときだけ記録する
        error_log("KonaWiki3 Session: save_path fallback (reason={$reason})");
    }

    $options = kona3session_buildOptions(
        $name,
        $lifetime,
        $persistent,
        kona3session_isHttps(),
        $savePath
    );
    $started = session_start($options);
    if ($started) {
        kona3session_refreshCookie($name, $options['cookie_lifetime']);
    }
    return $started;
}

// 使用中のセッション Cookie の期限を延長する。
// PHP は既存セッションの Cookie を再送しないため、何もしないと期限が
// 発行時点から固定され、使い続けていても cookie_lifetime で切れてしまう。
// 毎リクエストの Set-Cookie を避けるため、一定間隔でのみ再発行する。
function kona3session_refreshCookie($name, $cookieLifetime)
{
    $cookieLifetime = intval($cookieLifetime);
    if ($cookieLifetime <= 0) {
        return FALSE; // ブラウザを閉じるまでの Cookie は延長不要
    }
    if (headers_sent()) {
        return FALSE;
    }
    // ブラウザが送ってきた Cookie が現在のセッションと一致するときだけ対象
    if (!isset($_COOKIE[$name]) || $_COOKIE[$name] !== session_id()) {
        return FALSE;
    }

    $now = time();
    $last = isset($_SESSION['kona3_cookie_refreshed'])
        ? $_SESSION['kona3_cookie_refreshed']
        : 0;
    if (!kona3session_shouldRefreshCookie($last, $now)) {
        return FALSE;
    }

    // session_start() が設定した属性をそのまま引き継ぐ (PHP 7.3 以降の配列形式)
    $params = session_get_cookie_params();
    $ok = setcookie(session_name(), session_id(), [
        'expires' => $now + $cookieLifetime,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => isset($params['samesite']) ? $params['samesite'] : 'Lax',
    ]);
    if ($ok) {
        $_SESSION['kona3_cookie_refreshed'] = $now;
    }
    return $ok;
}

// Cookie を再発行すべきかを判定する純粋関数。
// 未設定・非数値・未来の時刻 (時計の巻き戻り) は再発行する側に倒す。
function kona3session_shouldRefreshCookie($last, $now)
{
    if (!is_numeric($last) || $last <= 0) {
        return TRUE;
    }
    $elapsed = $now - $last;
    if ($elapsed < 0) {
        return TRUE;
    }
    return $elapsed >= KONA3_SESSION_COOKIE_REFRESH_INTERVAL;
}

// セッション名を決める。既定名のときだけ、複数 Wiki の同一ホスト設置で
// Cookie が衝突しないよう、private の実パスから接尾辞を付ける。
// 明示的に設定された名前はそのまま尊重する。
function kona3session_resolveName($name, $privateDir)
{
    if (!is_string($name) || $name === '') {
        $name = KONA3_SESSION_DEFAULT_NAME;
    }
    if ($name !== KONA3_SESSION_DEFAULT_NAME) {
        return $name; // 明示設定は尊重する
    }
    $base = realpath($privateDir);
    if ($base === FALSE) {
        $base = $privateDir;
    }
    return $name . substr(sha1($base), 0, 8); // 英数字のみ
}

// 絶対パスか判定する (Unix の / 始まり、Windows の X:\ または X:/)。
function kona3session_isAbsolutePath($path)
{
    return preg_match('#^(/|[A-Za-z]:[\\\\/])#', $path) === 1;
}

// 専用保存先のパスを返す。php.ini の設定に任せる場合は NULL を返す。
// $reason にはフォールバックの理由 ('invalid_path' / 'handler_not_files' /
// 'mkdir_failed' / 'not_writable') が入る。理由がない場合は NULL のまま。
function kona3session_resolveSavePath($setting, $privateDir, &$reason)
{
    $reason = NULL;

    // 設定値を正規化する (前後の空白を除去、"PHP" や " php " も php として扱う)
    $setting = is_string($setting) ? trim($setting) : '';
    if (strtolower($setting) === 'php') {
        return NULL;
    }
    if ($setting !== '' && !kona3session_isAbsolutePath($setting)) {
        // 相対パスは実行時のカレントに依存して危険なので使わない
        $reason = 'invalid_path';
        return NULL;
    }
    if (ini_get('session.save_handler') !== 'files') {
        // redis 等では save_path の意味が異なるため上書きしない
        $reason = 'handler_not_files';
        return NULL;
    }

    $dir = ($setting !== '')
        ? $setting
        : rtrim($privateDir, '/') . '/' . KONA3_SESSION_DIR_NAME;
    if (!is_dir($dir) && !@mkdir($dir, 0700, TRUE) && !is_dir($dir)) {
        $reason = 'mkdir_failed';
        return NULL;
    }
    if (!is_writable($dir)) {
        $reason = 'not_writable';
        return NULL;
    }

    // Web から読めないよう、private/ と同じ .htaccess を配置する
    // (kona3lib_checkSecurity() が置くものと同じ文面)
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        $htaccessBody = <<< 'EOS'
# Deny all access
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Order Allow,Deny
  Deny from all
</IfModule>
EOS;
        @file_put_contents($htaccess, $htaccessBody);
    }

    // .htaccess が効かない環境でも、一覧表示から sess_ ファイル名
    // (= セッション ID) が見えないよう、空の index.html も置く
    $indexHtml = $dir . '/index.html';
    if (!file_exists($indexHtml)) {
        @file_put_contents($indexHtml, '');
    }

    return $dir;
}

// session_start() に渡すオプション配列を組み立てる。
// $savePath が NULL のときは save_path と GC 確率を指定しない
// (php.ini 側の設定、Debian 系では一覧を読めない権限の保存先に任せる)。
function kona3session_buildOptions($name, $lifetime, $persistent, $isHttps, $savePath)
{
    $lifetime = intval($lifetime);
    if ($lifetime <= 0) {
        $lifetime = KONA3_SESSION_DEFAULT_LIFETIME;
    }

    $options = [
        'name' => $name,
        'gc_maxlifetime' => $lifetime,
        'cookie_lifetime' => $persistent ? $lifetime : 0,
        'cookie_path' => '/',
        'cookie_domain' => '',
        'cookie_secure' => $isHttps ? 1 : 0,
        'cookie_httponly' => 1,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => 1,
        'use_cookies' => 1,
        'use_only_cookies' => 1,
        'use_trans_sid' => 0,
    ];
    if ($savePath !== NULL) {
        $options['save_path'] = $savePath;
        $options['gc_probability'] = KONA3_SESSION_GC_PROBABILITY;
        $options['gc_divisor'] = KONA3_SESSION_GC_DIVISOR;
    }
    return $options;
}

function kona3session_isHttps()
{
    // remember-me の Cookie 判定と同じ基準を使う
    if (function_exists('kona3remember_cookie_secure')) {
        return kona3remember_cookie_secure();
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return TRUE;
    }
    return isset($_SERVER['SERVER_PORT'])
        && intval($_SERVER['SERVER_PORT']) === 443;
}
