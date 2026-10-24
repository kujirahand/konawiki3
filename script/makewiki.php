<?php
// 新規Wikiを作成する。--admin を付けると管理者も同時に作成する (#295)
//   php script/makewiki.php <dir>
//   php script/makewiki.php <dir> --admin=<user_id> --password=<password>
//   KONA3_ADMIN_PASSWORD=... php script/makewiki.php <dir> --admin=<user_id>
if (php_sapi_name() !== 'cli') {
  exit;
}
$args = array_slice($argv, 1);
$dir = null;
// 管理者を作成するのは --admin (または --user) を明示したときだけ。
// 環境変数 KONA3_ADMIN_USER が残っていても、単独では作成を始めない (#296 レビュー指摘)。
// パスワードだけは従来どおり環境変数 KONA3_ADMIN_PASSWORD からも受け取る。
$adminUser = null;
$adminRequested = false;
$adminPassword = getenv('KONA3_ADMIN_PASSWORD') ?: null;
$adminForce = false;
foreach ($args as $arg) {
  if ($arg === '--force') {
    $adminForce = true;
  } elseif (strpos($arg, '--admin=') === 0) {
    $adminUser = substr($arg, strlen('--admin='));
    $adminRequested = true;
  } elseif (strpos($arg, '--user=') === 0) {
    $adminUser = substr($arg, strlen('--user='));
    $adminRequested = true;
  } elseif (strpos($arg, '--password=') === 0) {
    $adminPassword = substr($arg, strlen('--password='));
  } elseif (strpos($arg, '--') === 0) {
    fwrite(STDERR, "Unknown option: $arg\n");
    exit(2);
  } elseif ($dir === null) {
    $dir = $arg;
  } else {
    fwrite(STDERR, "Unknown argument: $arg\n");
    exit(2);
  }
}
if ($dir === null) {
  echo "[USAGE] makewiki.php <dir> [--admin=<user_id>] [--password=<password>] [--force]\n";
  exit(2);
}
// 管理者の入力チェックは Wiki を作る前に済ませる。
// 不正なIDやパスワード不足で終了したときに、中途半端なWikiを残さないため (#296 レビュー指摘)。
if ($adminRequested) {
  $checkUser = is_string($adminUser) ? trim($adminUser) : '';
  if ($checkUser === '') {
    fwrite(STDERR, "ERROR: --admin requires a user id.\n");
    exit(2);
  }
  if (preg_match('/[\s\x00-\x1f\x7f]/', $checkUser)) {
    fwrite(STDERR, "ERROR: Admin user id must not contain whitespace or control characters.\n");
    exit(2);
  }
  if ($adminPassword === null || $adminPassword === '') {
    fwrite(STDERR, "ERROR: --admin requires a password (--password=... or env KONA3_ADMIN_PASSWORD).\n");
    exit(2);
  }
}
if (!file_exists($dir)) {
  mkdir($dir, 0777, true);
}
$dir = realpath($dir);
$root = dirname(__DIR__);
// copy index
copy($root.'/index.php', $dir.'/index.php');
// make kona3dir.def.php
$data =<<<EOD
<?php
# konawiki3 dir info
define('KONA3_DIR_ENGINE',  '$root/kona3engine');
define('KONA3_DIR_SKIN',    '$root/skin');
define('KONA3_DIR_DATA',    __DIR__.'/data');
define('KONA3_DIR_PRIVATE', __DIR__.'/private');
define('KONA3_DIR_CACHE',   __DIR__.'/cache');
EOD;
// save
file_put_contents($dir.'/kona3dir.def.php', $data);
// mkdir
kona_mkdir("$dir/data");
kona_mkdir("$dir/private");
kona_mkdir("$dir/cache");
echo "ok\n";

// 管理者も作成する (ブラウザでの初回セットアップを省略できる)
// 入力チェックは Wiki 作成前に済んでいるので、ここでは作成だけを行う。
if ($adminRequested) {
  if (!defined('KONA3_DIR_PRIVATE')) {
    define('KONA3_DIR_ENGINE', $root.'/kona3engine');
    define('KONA3_DIR_PRIVATE', $dir.'/private');
  }
  global $kona3conf;
  $kona3conf = [];
  require_once $root.'/kona3engine/jsonphp.lib.php';
  require_once $root.'/kona3engine/kona3login.inc.php';
  try {
    kona3createAdminUser($adminUser, $adminPassword, $adminForce);
  } catch (InvalidArgumentException $e) {
    // 入力値不正は create_admin.php と同じ exit 2
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(2);
  } catch (Exception $e) {
    // 既存ユーザーの重複など実行時エラーは exit 1
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
  }
  echo "Created admin user: $adminUser\n";
}

function kona_mkdir($dir) {
  if (!file_exists($dir)) {
    mkdir($dir);
  }
}
