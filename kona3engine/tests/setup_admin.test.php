<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/kona3login.inc.php';

// #295: スクリプトから管理者ユーザーを作成できること
// (テストサンドボックスの private/ に作るので実データには影響しない)
$adminFile = kona3getAdminUserFile();
@unlink($adminFile);
test_eq(__LINE__, kona3getAdminUsers(), [], "setup_admin: no admin users at first");

test_eq(__LINE__, kona3createAdminUser('admin@example.com', 'secret-pw-1'), TRUE, "setup_admin: create returns TRUE");
test_assert(__LINE__, file_exists($adminFile), "setup_admin: admin file created");

$users = kona3getAdminUsers();
test_assert(__LINE__, isset($users['admin@example.com']), "setup_admin: user is listed");
test_ne(__LINE__, $users['admin@example.com']['hash'], 'secret-pw-1', "setup_admin: password is not stored in plain text");
test_assert(__LINE__, !empty($users['admin@example.com']['salt']), "setup_admin: salt is saved");

// 作成した管理者で認証できる (間違ったパスワードは不可)
global $kona3conf;
test_eq(__LINE__, kona3_admin_auth('admin@example.com', 'secret-pw-1', $kona3conf), TRUE, "setup_admin: auth succeeds");
test_eq(__LINE__, kona3isAdmin(), TRUE, "setup_admin: auth gives admin perm");
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
test_eq(__LINE__, kona3_admin_auth('admin@example.com', 'wrong-pw', $kona3conf), FALSE, "setup_admin: wrong password rejected");
test_eq(__LINE__, kona3_admin_auth('nobody@example.com', 'secret-pw-1', $kona3conf), FALSE, "setup_admin: unknown user rejected");

// 既存ユーザーは上書き指定なしでは作り直せない
$threw = FALSE;
try {
    kona3createAdminUser('admin@example.com', 'other-pw');
} catch (RuntimeException $e) {
    $threw = TRUE;
}
test_assert(__LINE__, $threw, "setup_admin: duplicate without overwrite throws");
test_eq(__LINE__, kona3_admin_auth('admin@example.com', 'secret-pw-1', $kona3conf), TRUE, "setup_admin: password unchanged after duplicate attempt");
unset($_SESSION[KONA3_SESSKEY_LOGIN]);

// 上書き指定でパスワードを変更でき、他の管理者は保持される
kona3createAdminUser('second@example.com', 'second-pw');
kona3createAdminUser('admin@example.com', 'secret-pw-2', TRUE);
test_eq(__LINE__, kona3_admin_auth('admin@example.com', 'secret-pw-1', $kona3conf), FALSE, "setup_admin: old password rejected after overwrite");
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
test_eq(__LINE__, kona3_admin_auth('admin@example.com', 'secret-pw-2', $kona3conf), TRUE, "setup_admin: new password accepted after overwrite");
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
test_eq(__LINE__, kona3_admin_auth('second@example.com', 'second-pw', $kona3conf), TRUE, "setup_admin: other admin preserved");
unset($_SESSION[KONA3_SESSKEY_LOGIN]);

// ソルトはユーザーごとに異なる
$users = kona3getAdminUsers();
test_ne(__LINE__, $users['admin@example.com']['salt'], $users['second@example.com']['salt'], "setup_admin: salts differ per user");

// 検証エラー: 空のID / 空のパスワード / 空白を含むID
foreach ([['', 'pw'], ['   ', 'pw'], ['admin@example.com', ''], ['a b@example.com', 'pw']] as $bad) {
    $threw = FALSE;
    try {
        kona3createAdminUser($bad[0], $bad[1], TRUE);
    } catch (InvalidArgumentException $e) {
        $threw = TRUE;
    }
    test_assert(__LINE__, $threw, "setup_admin: invalid input rejected ({$bad[0]})");
}

// enc:key (jsonphp内部の暗号鍵) は管理者ユーザーとして返さない
$rawUsers = jsonphp_load($adminFile, [], true);
test_assert(__LINE__, isset($rawUsers['enc:key']), "setup_admin: raw admin file contains enc:key");
$users = kona3getAdminUsers();
test_assert(__LINE__, !isset($users['enc:key']), "setup_admin: enc:key is not returned as an admin user");

// CLIスクリプトが用意されている
test_assert(__LINE__, file_exists(dirname(KONA3_DIR_ENGINE) . '/script/create_admin.php'), "setup_admin: create_admin.php exists");
test_assert(__LINE__, file_exists(dirname(KONA3_DIR_ENGINE) . '/script/makewiki.php'), "setup_admin: makewiki.php exists");

// --- CLIスクリプト本体の自動テスト (別プロセスで実行し終了コードを確認) ---
$scriptDir = dirname(KONA3_DIR_ENGINE) . '/script';
$cliWiki = sys_get_temp_dir() . '/kona3_cli_test_' . getmypid() . '_' . uniqid();
$runCli = function ($cmd) {
    $out = [];
    $code = -1;
    exec($cmd . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
};
$php = escapeshellarg(PHP_BINARY);
// makewiki でWikiを作成 (管理者なし)
list($code, $out) = $runCli("$php " . escapeshellarg("$scriptDir/makewiki.php") . ' ' . escapeshellarg($cliWiki));
test_eq(__LINE__, $code, 0, "cli: makewiki exits 0");
// create_admin で管理者を作成
list($code, $out) = $runCli("$php " . escapeshellarg("$scriptDir/create_admin.php") . ' --wiki=' . escapeshellarg($cliWiki) . ' cli-admin@example.com cli-secret-1');
test_eq(__LINE__, $code, 0, "cli: create_admin exits 0");
test_assert(__LINE__, file_exists("$cliWiki/private/kona3adminuser.json.php"), "cli: admin file created by CLI");
test_assert(__LINE__, strpos(file_get_contents("$cliWiki/private/kona3adminuser.json.php"), 'cli-admin@example.com') !== FALSE, "cli: admin user written by CLI");
// 重複は --force なしでは exit 1
list($code, $out) = $runCli("$php " . escapeshellarg("$scriptDir/create_admin.php") . ' --wiki=' . escapeshellarg($cliWiki) . ' cli-admin@example.com other-pw');
test_eq(__LINE__, $code, 1, "cli: duplicate exits 1");
// --force では exit 0
list($code, $out) = $runCli("$php " . escapeshellarg("$scriptDir/create_admin.php") . ' --wiki=' . escapeshellarg($cliWiki) . ' --force cli-admin@example.com cli-secret-2');
test_eq(__LINE__, $code, 0, "cli: overwrite with --force exits 0");
// 引数不足は exit 2
list($code, $out) = $runCli("$php " . escapeshellarg("$scriptDir/create_admin.php") . ' --wiki=' . escapeshellarg($cliWiki) . ' only-user');
test_eq(__LINE__, $code, 2, "cli: missing password exits 2");
// --user と位置引数が競合したら exit 2 (無警告で上書きしない)
list($code, $out) = $runCli("$php " . escapeshellarg("$scriptDir/create_admin.php") . ' --wiki=' . escapeshellarg($cliWiki) . ' --user=foo@example.com bar@example.com secret');
test_eq(__LINE__, $code, 2, "cli: conflicting --user and positional exits 2");
// 環境変数だけでも作成できる
list($code, $out) = $runCli('KONA3_ADMIN_USER=env-admin@example.com KONA3_ADMIN_PASSWORD=env-secret ' . "$php " . escapeshellarg("$scriptDir/create_admin.php") . ' --wiki=' . escapeshellarg($cliWiki));
test_eq(__LINE__, $code, 0, "cli: create via env vars exits 0");
// makewiki --admin の入力値不正は exit 2 (create_admin.php と統一)
$cliWiki2 = $cliWiki . '-bad';
list($code, $out) = $runCli("$php " . escapeshellarg("$scriptDir/makewiki.php") . ' ' . escapeshellarg($cliWiki2) . ' --admin=' . escapeshellarg('a b@example.com') . ' --password=<redacted>');
test_eq(__LINE__, $code, 2, "cli: makewiki with invalid admin id exits 2");
// CLIテスト用Wikiを削除
foreach ([$cliWiki, $cliWiki2] as $d) {
    if (!file_exists($d)) continue;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($d);
}

// 後片付け (他のテストに管理者ファイルを残さない)
@unlink($adminFile);
test_eq(__LINE__, kona3getAdminUsers(), [], "setup_admin: cleaned up");
