<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/kona3session.inc.php';

// --- セッション開始オプション ---
$opts = kona3session_buildOptions(
    'testsession',
    KONA3_SESSION_DEFAULT_LIFETIME,
    TRUE,
    FALSE,
    '/tmp/kona3-session-test'
);
test_eq(__LINE__, $opts['gc_maxlifetime'], 86400, 'session options: default gc lifetime is 86400');
test_eq(__LINE__, $opts['cookie_lifetime'], 86400, 'session options: persistent cookie lifetime is 86400');

$opts = kona3session_buildOptions('testsession', 86400, FALSE, FALSE, '/tmp/kona3-session-test');
test_eq(__LINE__, $opts['cookie_lifetime'], 0, 'session options: non-persistent cookie has lifetime zero');
test_eq(__LINE__, $opts['gc_maxlifetime'], 86400, 'session options: non-persistent cookie keeps gc lifetime');

$opts = kona3session_buildOptions('testsession', 0, TRUE, FALSE, '/tmp/kona3-session-test');
test_eq(__LINE__, $opts['gc_maxlifetime'], 86400, 'session options: zero lifetime falls back to default');
$opts = kona3session_buildOptions('testsession', -1, TRUE, FALSE, '/tmp/kona3-session-test');
test_eq(__LINE__, $opts['gc_maxlifetime'], 86400, 'session options: negative lifetime falls back to default');

$opts = kona3session_buildOptions('testsession', 86400, TRUE, TRUE, '/tmp/kona3-session-test');
test_eq(__LINE__, $opts['cookie_secure'], 1, 'session options: HTTPS enables secure cookie');
$opts = kona3session_buildOptions('testsession', 86400, TRUE, FALSE, '/tmp/kona3-session-test');
test_eq(__LINE__, $opts['cookie_secure'], 0, 'session options: HTTP disables secure cookie');

test_eq(__LINE__, $opts['cookie_httponly'], 1, 'session options: cookie is httponly');
test_eq(__LINE__, $opts['cookie_samesite'], 'Lax', 'session options: cookie samesite is Lax');
test_eq(__LINE__, $opts['use_strict_mode'], 1, 'session options: strict mode is enabled');
test_eq(__LINE__, $opts['use_only_cookies'], 1, 'session options: only cookies are used');
test_eq(__LINE__, $opts['use_trans_sid'], 0, 'session options: trans sid is disabled');
test_eq(__LINE__, $opts['cookie_path'], '/', 'session options: cookie path is root');
test_eq(__LINE__, $opts['cookie_domain'], '', 'session options: cookie domain is empty');

$opts = kona3session_buildOptions('testsession', 86400, TRUE, FALSE, '/tmp/kona3-session-test');
test_eq(__LINE__, $opts['save_path'], '/tmp/kona3-session-test', 'session options: save path is set');
test_eq(__LINE__, $opts['gc_probability'], KONA3_SESSION_GC_PROBABILITY, 'session options: gc probability is set');
test_eq(__LINE__, $opts['gc_divisor'], KONA3_SESSION_GC_DIVISOR, 'session options: gc divisor is set');

$opts = kona3session_buildOptions('testsession', 86400, TRUE, FALSE, NULL);
test_assert(__LINE__, !array_key_exists('save_path', $opts), 'session options: null save path does not override php.ini');
test_assert(__LINE__, !array_key_exists('gc_probability', $opts), 'session options: null save path does not set gc probability');
test_assert(__LINE__, !array_key_exists('gc_divisor', $opts), 'session options: null save path does not set gc divisor');

// --- セッション名 ---
$nameA = kona3session_resolveName(KONA3_SESSION_DEFAULT_NAME, '/tmp/kona3-private-a');
$nameA2 = kona3session_resolveName(KONA3_SESSION_DEFAULT_NAME, '/tmp/kona3-private-a');
$nameB = kona3session_resolveName(KONA3_SESSION_DEFAULT_NAME, '/tmp/kona3-private-b');
test_assert(__LINE__, preg_match('/^[A-Za-z0-9]+$/', $nameA) === 1, 'session name: default name has only alphanumeric characters');
test_assert(__LINE__, strpos($nameA, KONA3_SESSION_DEFAULT_NAME) === 0, 'session name: default name has suffix');
test_eq(__LINE__, $nameA, $nameA2, 'session name: same path resolves deterministically');
test_assert(__LINE__, $nameA !== $nameB, 'session name: different paths resolve to different names');
test_eq(__LINE__, kona3session_resolveName('custom_session', '/tmp/kona3-private-a'), 'custom_session', 'session name: explicit name is preserved');

// --- セッション保存先 ---
$tmpDir = sys_get_temp_dir() . '/kona3session-test-' . uniqid('', TRUE);
$privateDir = $tmpDir . '/private';
$reason = NULL;
$savePath = kona3session_resolveSavePath('', $privateDir, $reason);
$defaultSessionDir = $privateDir . '/' . KONA3_SESSION_DIR_NAME;

test_eq(__LINE__, $savePath, $defaultSessionDir, 'session save path: default path is private sessions directory');
test_eq(__LINE__, $reason, NULL, 'session save path: default path has no fallback reason');
test_assert(__LINE__, is_dir($defaultSessionDir), 'session save path: default directory is created');
clearstatcache(TRUE, $defaultSessionDir);
test_eq(__LINE__, fileperms($defaultSessionDir) & 0777, 0700, 'session save path: default directory permission is 0700');
test_assert(__LINE__, file_exists($defaultSessionDir . '/.htaccess'), 'session save path: htaccess is created');

$reason = 'initial';
test_eq(__LINE__, kona3session_resolveSavePath('php', $privateDir, $reason), NULL, 'session save path: php setting keeps php.ini path');
test_eq(__LINE__, $reason, NULL, 'session save path: php setting has no fallback reason');

$customDir = $tmpDir . '/custom-sessions';
$reason = NULL;
test_eq(__LINE__, kona3session_resolveSavePath($customDir, $privateDir, $reason), $customDir, 'session save path: absolute path is used');
test_eq(__LINE__, $reason, NULL, 'session save path: absolute path has no fallback reason');
test_assert(__LINE__, is_dir($customDir), 'session save path: absolute path directory is created');

// 一時ディレクトリを削除する
@unlink($defaultSessionDir . '/.htaccess');
@unlink($customDir . '/.htaccess');
@rmdir($defaultSessionDir);
@rmdir($customDir);
@rmdir($privateDir);
@rmdir($tmpDir);
