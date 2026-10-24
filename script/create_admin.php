<?php
// 管理者ユーザーをCLIから作成する (#295)
//   php script/create_admin.php <user_id> <password>
//   php script/create_admin.php --user=<user_id> --password=<password> [--force]
//   php script/create_admin.php --wiki=<dir> <user_id> <password>
//   KONA3_ADMIN_USER=... KONA3_ADMIN_PASSWORD=... php script/create_admin.php
//
// パスワードをコマンドライン引数に書くとシェル履歴に残るため、
// テストや自動セットアップでは環境変数 KONA3_ADMIN_PASSWORD の利用を推奨する。
// 既存ユーザーは --force を付けたときだけ上書きする。
if (php_sapi_name() !== 'cli') {
    exit;
}

function create_admin_usage()
{
    echo "Usage: php script/create_admin.php [--wiki=<dir>] [--force] <user_id> <password>\n";
    echo "       php script/create_admin.php [--wiki=<dir>] [--force] --user=<user_id> --password=<password>\n";
    echo "Env:   KONA3_ADMIN_USER / KONA3_ADMIN_PASSWORD (used when arguments are omitted)\n";
}

$wikiDir = null;
$force = false;
$user = getenv('KONA3_ADMIN_USER') ?: null;
$password = getenv('KONA3_ADMIN_PASSWORD') ?: null;
$positional = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        create_admin_usage();
        exit(0);
    } elseif ($arg === '--force') {
        $force = true;
    } elseif (strpos($arg, '--wiki=') === 0) {
        $wikiDir = substr($arg, strlen('--wiki='));
    } elseif (strpos($arg, '--user=') === 0) {
        $user = substr($arg, strlen('--user='));
    } elseif (strpos($arg, '--password=') === 0) {
        $password = substr($arg, strlen('--password='));
    } elseif (strpos($arg, '--') === 0) {
        fwrite(STDERR, "Unknown option: $arg\n");
        create_admin_usage();
        exit(2);
    } else {
        $positional[] = $arg;
    }
}
if (isset($positional[0])) $user = $positional[0];
if (isset($positional[1])) $password = $positional[1];
if (count($positional) > 2 || $user === null || $user === '' || $password === null || $password === '') {
    fwrite(STDERR, "ERROR: user_id and password are required.\n");
    create_admin_usage();
    exit(2);
}

$root = dirname(__DIR__);
// 対象Wikiのディレクトリ定数を決める
if ($wikiDir !== null) {
    $wikiDir = realpath($wikiDir);
    if ($wikiDir === false) {
        fwrite(STDERR, "ERROR: wiki dir not found. Run script/makewiki.php first.\n");
        exit(2);
    }
    $defFile = $wikiDir . '/kona3dir.def.php';
    if (!file_exists($defFile)) {
        fwrite(STDERR, "ERROR: kona3dir.def.php not found in wiki dir: $wikiDir\n");
        exit(2);
    }
    define('KONA3_DIR_INDEX', $wikiDir);
    require_once $defFile;
} elseif (file_exists($root . '/kona3dir.def.php')) {
    define('KONA3_DIR_INDEX', $root);
    require_once $root . '/kona3dir.def.php';
} else {
    define('KONA3_DIR_INDEX', $root);
    define('KONA3_DIR_ENGINE', $root . '/kona3engine');
    define('KONA3_DIR_SKIN', $root . '/skin');
    define('KONA3_DIR_DATA', $root . '/data');
    define('KONA3_DIR_PRIVATE', $root . '/private');
    define('KONA3_DIR_CACHE', $root . '/cache');
}
foreach (['data', 'private', 'cache'] as $d) {
    $const = 'KONA3_DIR_' . strtoupper($d);
    if (defined($const) && !file_exists(constant($const))) {
        mkdir(constant($const), 0777, true);
    }
}

global $kona3conf;
$kona3conf = [];
require_once KONA3_DIR_ENGINE . '/jsonphp.lib.php';
require_once KONA3_DIR_ENGINE . '/kona3login.inc.php';

try {
    kona3createAdminUser($user, $password, $force);
} catch (RuntimeException $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . " (add --force to overwrite)\n");
    exit(1);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(2);
}
echo "Created admin user: $user\n";
echo "Admin file: " . kona3getAdminUserFile() . "\n";
exit(0);
