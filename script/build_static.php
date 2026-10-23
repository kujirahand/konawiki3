<?php
// 静的Wiki出力 CLI (docs/static_wiki.md 12章)
//   php script/build_static.php            更新されたページだけを出力する (差分生成)
//   php script/build_static.php --full     すべてのページを再生成する
//   php script/build_static.php --purge    生成済みの出力を削除する
//   php script/build_static.php --adopt --yes  出力先の既存ファイルを上書きして生成する
if (php_sapi_name() !== 'cli') {
    exit;
}
$root = dirname(__DIR__);
define('KONA3_DIR_INDEX', $root);
$file_def = $root . '/kona3dir.def.php';
if (file_exists($file_def)) {
    require_once $file_def;
}
// index.inc.php は読み込み時に kona3index_main() を実行し、設定ファイルの読み込みと初期化を行う
// (CLI では画面の描画だけを行わない)
require_once $root . '/kona3engine/index.inc.php';

$opts = array_slice($argv, 1);
foreach ($opts as $o) {
    if (!in_array($o, ['--full', '--purge', '--adopt', '--yes', '--help'], true)) {
        fwrite(STDERR, "Unknown option: $o\n");
        exit(2);
    }
}
if (in_array('--help', $opts, true)) {
    echo "Usage: php script/build_static.php [--full] [--purge] [--adopt --yes]\n";
    exit(0);
}

global $kona3conf;
try {
    if (in_array('--purge', $opts, true)) {
        $n = kona3static_purge();
        echo "Removed $n files.\n";
        exit(0);
    }
    $adopt = in_array('--adopt', $opts, true);
    if ($adopt && !in_array('--yes', $opts, true)) {
        fwrite(STDERR, "--adopt overwrites existing files in the output directory. Add --yes to confirm.\n");
        exit(2);
    }
    $r = kona3static_buildAll(['adopt' => $adopt, 'full' => in_array('--full', $opts, true)]);
} catch (Kona3StaticException $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
echo "pages: {$r['pages']}, skipped: {$r['skipped']}, assets: {$r['assets']}, deleted: {$r['deleted']}, failed: {$r['failed']}\n";
foreach ($r['warnings'] as $w) {
    echo "WARN: $w\n";
}
foreach ($r['errors'] as $e) {
    fwrite(STDERR, "ERROR: $e\n");
}
exit(count($r['errors']) > 0 ? 1 : 0);
