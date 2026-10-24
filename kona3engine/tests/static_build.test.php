<?php
require_once __DIR__ . '/test_common.inc.php';

// 静的Wiki出力 全体生成のテスト (docs/static_wiki.md 11〜14章)
global $kona3conf;

function sb_rmdir($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        if (is_dir($p) && !is_link($p)) {
            sb_rmdir($p);
        } else {
            unlink($p);
        }
    }
    rmdir($dir);
}

function sb_throws($fn)
{
    try {
        $fn();
    } catch (Kona3StaticException $e) {
        return TRUE;
    }
    return FALSE;
}

// --- 出力先の安全確認 ---
@mkdir(KONA3_DIR_DATA, 0777, true); // 他のテストが data を消している場合に備える
$idx = kona3static_indexDir();
test_assert(__LINE__, sb_throws(function () { kona3static_checkOutdir(KONA3_DIR_DATA); }), 'reject data dir');
test_assert(__LINE__, sb_throws(function () { kona3static_checkOutdir(KONA3_DIR_DATA . '/sub'); }), 'reject under data dir');
test_assert(__LINE__, sb_throws(function () { kona3static_checkOutdir(KONA3_DIR_PRIVATE); }), 'reject private dir');
test_assert(__LINE__, sb_throws(function () { kona3static_checkOutdir(KONA3_DIR_CACHE); }), 'reject cache dir');
test_assert(__LINE__, sb_throws(function () { kona3static_checkOutdir(KONA3_DIR_ENGINE); }), 'reject engine dir');
test_assert(__LINE__, sb_throws(function () { kona3static_checkOutdir(KONA3_DIR_SKIN); }), 'reject skin dir');
test_assert(__LINE__, sb_throws(function () use ($idx) { kona3static_checkOutdir($idx); }), 'reject index dir');
test_assert(__LINE__, sb_throws(function () use ($idx) { kona3static_checkOutdir(dirname($idx)); }), 'reject parent of index dir');
test_assert(__LINE__, sb_throws(function () { kona3static_checkOutdir(KONA3_DIR_DATA . '/dist/../x'); }), 'reject .. into data');
test_eq(__LINE__, kona3static_checkOutdir('dist'), str_replace('\\', '/', $idx) . '/dist', 'relative outdir');

test_assert(__LINE__, sb_throws(function () { kona3static_checkOutdir(strtoupper(KONA3_DIR_DATA) . '/static'); }) || !file_exists(strtoupper(KONA3_DIR_DATA)), 'reject data dir regardless of case');
test_assert(__LINE__, sb_throws(function () { kona3static_checkOutdir(KONA3_DIR_DATA . '/../DATA/x'); }), 'reject data dir via .. and case');

// --- 準備 ---
$tmpRoot = realpath(sys_get_temp_dir()) . '/kona3static_build_' . getmypid();
sb_rmdir($tmpRoot);
mkdir($tmpRoot);
$out = $tmpRoot . '/out';
$dir = KONA3_DIR_DATA . '/StaticBuildTest';
sb_rmdir($dir);
mkdir($dir);
mkdir("$dir/Sub");
file_put_contents("$dir/Page.txt", "* Build Page\n[[StaticBuildTest/Md]]\n#ref(a.png)\n");
file_put_contents("$dir/Md.md", "# Md Page\n");
file_put_contents("$dir/Sub/Deep.txt", "deep\n");
file_put_contents("$dir/Old.txt", "!!alias(StaticBuildTest/Page)\n");
$aliasJson = KONA3_DIR_DATA . '/' . KONA3_ALIAS_SHOW_JSON_NAME;
$aliasBackup = file_exists($aliasJson) ? file_get_contents($aliasJson) : null;
file_put_contents($aliasJson, json_encode(['StaticBuildJsonAlias' => 'StaticBuildTest/Page']));
file_put_contents("$dir/Hidden.txt", "hidden\n");
file_put_contents("$dir/a.png", "PNGDATA");
file_put_contents("$dir/evil.php", "<?php echo 1;");
file_put_contents("$dir/x.php.png", "x");
file_put_contents("$dir/note.html", "<script>1</script>");
$metaFile = kona3db_getPageMetaFile('StaticBuildTest/Hidden');
@mkdir(dirname($metaFile), 0777, true);
file_put_contents($metaFile, json_encode(['static' => false]));

$kona3conf['wiki_private'] = FALSE;
$kona3conf['skin'] = 'def';
$kona3conf['static_base_url'] = '/';
$manifest = kona3static_manifestFile();
@unlink($manifest);

// --- 列挙 ---
$scan = kona3static_scanData();
test_eq(__LINE__, $scan['pages']['StaticBuildTest/Page'], 'page', 'scan: page');
test_eq(__LINE__, $scan['pages']['StaticBuildTest/Sub'], 'dir', 'scan: dir only page');
test_assert(__LINE__, isset($scan['files']['StaticBuildTest/a.png']), 'scan: attachment');
test_assert(__LINE__, !isset($scan['files']['StaticBuildTest/evil.php']), 'scan: php is denied');
test_assert(__LINE__, !isset($scan['files']['StaticBuildTest/x.php.png']), 'scan: multi extension is denied');
test_assert(__LINE__, !isset($scan['files']['StaticBuildTest/note.html']), 'scan: html is denied');
test_assert(__LINE__, kona3static_isExcluded('MenuBar'), 'MenuBar is excluded');
test_assert(__LINE__, kona3static_isExcluded('StaticBuildTest/Hidden'), 'meta static=false is excluded');

// --- 全体生成 ---
$r = kona3static_buildAll(['outdir' => $out]);
test_eq(__LINE__, implode("\n", $r['errors']), '', 'build: no errors');
test_assert(__LINE__, file_exists("$out/StaticBuildTest/Page/index.html"), 'build: page');
test_assert(__LINE__, file_exists("$out/StaticBuildTest/Md/index.html"), 'build: markdown page');
test_assert(__LINE__, file_exists("$out/StaticBuildTest/Sub/Deep/index.html"), 'build: nested page');
test_assert(__LINE__, file_exists("$out/StaticBuildTest/Sub/index.html"), 'build: directory page');
test_assert(__LINE__, !file_exists("$out/StaticBuildTest/Hidden/index.html"), 'build: excluded page');
test_assert(__LINE__, !file_exists("$out/MenuBar/index.html"), 'build: MenuBar is not output');
test_assert(__LINE__, file_exists("$out/404.html"), 'build: 404.html');
test_assert(__LINE__, file_exists("$out/_kona3/skin/def/kona3.css"), 'build: skin asset');
test_assert(__LINE__, file_exists("$out/_kona3/resource/pure-min.css"), 'build: resource asset');
test_eq(__LINE__, @file_get_contents("$out/_kona3/files/StaticBuildTest/a.png"), 'PNGDATA', 'build: attachment is copied');
test_assert(__LINE__, !file_exists("$out/_kona3/files/StaticBuildTest/evil.php"), 'build: php is not copied');
$page = (string)@file_get_contents("$out/StaticBuildTest/Page/index.html");
test_assert(__LINE__, strpos($page, '/_kona3/files/StaticBuildTest/a.png') !== FALSE, 'build: ref points to the copied attachment');
test_assert(__LINE__, strpos($page, '/StaticBuildTest/Md/') !== FALSE, 'build: link to markdown page');
// #287: 書き出したHTMLは相対URL (このページは出力先から2階層目)
test_assert(__LINE__, strpos($page, '../../_kona3/resource/pure-min.css') !== FALSE, 'build: css url is relative (#287)');
test_assert(__LINE__, strpos($page, '../../StaticBuildTest/Md/') !== FALSE, 'build: page link is relative (#287)');
test_assert(__LINE__, strpos($page, '="/_kona3') === FALSE && strpos($page, "='/_kona3") === FALSE, 'build: no root-absolute asset url (#287)');
test_assert(__LINE__, strpos($page, '="/StaticBuildTest') === FALSE && strpos($page, "='/StaticBuildTest") === FALSE, 'build: no root-absolute page url (#287)');
$jsonAlias = (string)@file_get_contents("$out/StaticBuildJsonAlias/index.html");
test_assert(__LINE__, strpos($jsonAlias, 'http-equiv="refresh"') !== FALSE && strpos($jsonAlias, '/StaticBuildTest/Page/') !== FALSE, 'build: alias-show.json only alias is output');
test_assert(__LINE__, !file_exists("$out/alias-show.json") && !file_exists("$out/_kona3/files/alias-show.json"), 'build: alias-show.json is not published');
$alias = (string)@file_get_contents("$out/StaticBuildTest/Old/index.html");
test_assert(__LINE__, strpos($alias, 'http-equiv="refresh"') !== FALSE && strpos($alias, '/StaticBuildTest/Page/') !== FALSE, 'build: alias redirect');
test_assert(__LINE__, strpos($alias, 'rel="canonical"') !== FALSE, 'build: alias canonical');
test_assert(__LINE__, file_exists($manifest), 'build: manifest');
test_eq(__LINE__, count(glob("$out/StaticBuildTest/Page/.kona3tmp_*")), 0, 'build: no temporary file');

// --- 再生成と古い出力の削除 ---
unlink("$dir/Md.md");
$r = kona3static_buildAll(['outdir' => $out]);
test_assert(__LINE__, !file_exists("$out/StaticBuildTest/Md/index.html"), 'rebuild: removed page output is deleted');
test_assert(__LINE__, !is_dir("$out/StaticBuildTest/Md"), 'rebuild: empty directory is removed');
test_assert(__LINE__, file_exists("$out/StaticBuildTest/Page/index.html"), 'rebuild: other pages stay');

// --- 添付判定 (ref プラグインと共通) ---
test_assert(__LINE__, kona3static_isCopyable('StaticBuildTest/a.png'), 'copyable: png');
test_assert(__LINE__, !kona3static_isCopyable('StaticBuildTest/evil.php'), 'not copyable: php');
test_assert(__LINE__, !kona3static_isCopyable('StaticBuildTest/Page.txt'), 'not copyable: page source');
test_assert(__LINE__, !kona3static_isCopyable('../private/kona3json_iv.bin'), 'not copyable: parent dir');
test_assert(__LINE__, !kona3static_isCopyable(KONA3_ALIAS_SHOW_JSON_NAME), 'not copyable: alias-show.json');

// --- 中間階層のシンボリックリンクを通して書かない ---
mkdir("$out/inner");
$other = $tmpRoot . '/other';
mkdir($other);
if (@symlink($other, "$out/linked")) {
    $st = ['outdir' => $out, 'old' => kona3static_loadManifest($out), 'dirs' => [], 'adopt' => FALSE];
    test_assert(__LINE__, sb_throws(function () use (&$st) { kona3static_publish($st, 'linked/x.html', 'x', false); }), 'reject symlinked intermediate dir');
    test_assert(__LINE__, !file_exists("$other/x.html"), 'symlinked dir target is untouched');
    unlink("$out/linked");
}
rmdir("$out/inner");

// --- 改ざんされたマニフェストでも出力先の外は消さない ---
file_put_contents("$tmpRoot/victim.txt", 'keep');
test_assert(__LINE__, !kona3static_safeUnlink($out, '../victim.txt'), 'safeUnlink: rejects ..');
test_assert(__LINE__, !kona3static_safeUnlink($out, '/etc/passwd'), 'safeUnlink: rejects absolute');
test_eq(__LINE__, file_get_contents("$tmpRoot/victim.txt"), 'keep', 'safeUnlink: outside file stays');
$m = kona3static_loadManifest($out);
$m['assets']['../victim.txt'] = true;
kona3static_saveManifest($m);
kona3static_purge($out);
test_eq(__LINE__, file_get_contents("$tmpRoot/victim.txt"), 'keep', 'purge: tampered manifest does not delete outside file');
kona3static_buildAll(['outdir' => $out]);

// --- 利用者のファイルは上書き・削除しない ---
file_put_contents("$out/mine.txt", 'mine');
mkdir("$out/mydir");
file_put_contents("$dir/Mine.txt", "mine\n");
mkdir("$out/StaticBuildTest/Mine");
file_put_contents("$out/StaticBuildTest/Mine/index.html", 'user file');
$r = kona3static_buildAll(['outdir' => $out]);
test_eq(__LINE__, file_get_contents("$out/StaticBuildTest/Mine/index.html"), 'user file', 'user file is not overwritten');
test_assert(__LINE__, count($r['errors']) > 0, 'overwrite conflict is reported');
$r = kona3static_purge($out);
test_assert(__LINE__, $r > 0, 'purge: removes files');
test_eq(__LINE__, file_get_contents("$out/mine.txt"), 'mine', 'purge: user file stays');
test_eq(__LINE__, file_get_contents("$out/StaticBuildTest/Mine/index.html"), 'user file', 'purge: user page stays');
test_assert(__LINE__, is_dir("$out/mydir"), 'purge: user dir stays');
test_assert(__LINE__, !file_exists("$out/StaticBuildTest/Page/index.html"), 'purge: generated file is removed');
test_assert(__LINE__, !is_dir("$out/_kona3"), 'purge: generated dirs are removed');

// --- --adopt ---
$r = kona3static_buildAll(['outdir' => $out, 'adopt' => TRUE]);
test_eq(__LINE__, implode("\n", $r['errors']), '', 'adopt: no errors');
test_assert(__LINE__, strpos(file_get_contents("$out/StaticBuildTest/Mine/index.html"), 'Mine') !== FALSE || strpos(file_get_contents("$out/StaticBuildTest/Mine/index.html"), 'mine') !== FALSE, 'adopt: overwrites');
kona3static_purge($out);

// --- 衝突は何も書かずに中止する ---
file_put_contents("$dir/about.txt", "a\n");
file_put_contents("$dir/About.txt", "b\n");
// 大文字小文字を区別しないFS (macOS, Windows) では2ファイルが同一になるので衝突を作れない
if (count(glob("$dir/[aA]bout.txt")) === 2) {
    test_assert(__LINE__, sb_throws(function () use ($out) { kona3static_buildAll(['outdir' => $out]); }), 'collision aborts the build');
    test_assert(__LINE__, !file_exists("$out/StaticBuildTest/Page/index.html"), 'collision: nothing is written');
}
@unlink("$dir/about.txt");
@unlink("$dir/About.txt");

// --- 出力先を変更しても、記録済みの旧出力先を削除できる ---
$out2 = $tmpRoot . '/out2';
kona3static_buildAll(['outdir' => $out, 'adopt' => TRUE]);
test_assert(__LINE__, file_exists("$out/StaticBuildTest/Page/index.html"), 'moved outdir: prepared');
$kona3conf['static_outdir'] = $out2;
$n = kona3static_purge($out2);
test_assert(__LINE__, $n > 0 && !file_exists("$out/StaticBuildTest/Page/index.html"), 'purge removes the output at the recorded outdir');
unset($kona3conf['static_outdir']);

// --- 非公開Wikiは生成せず、出力を削除する ---
kona3static_buildAll(['outdir' => $out, 'adopt' => TRUE]);
test_assert(__LINE__, file_exists("$out/StaticBuildTest/Page/index.html"), 'private: prepared');
$kona3conf['wiki_private'] = TRUE;
test_assert(__LINE__, sb_throws(function () use ($out) { kona3static_buildAll(['outdir' => $out]); }), 'private wiki is refused');
test_assert(__LINE__, !file_exists("$out/StaticBuildTest/Page/index.html"), 'private wiki: output is purged');
$kona3conf['wiki_private'] = FALSE;

// --- ロック ---
$fp = kona3static_lock();
test_assert(__LINE__, sb_throws(function () { kona3static_lock(); }), 'second lock is refused');
kona3static_unlock($fp);
$fp = kona3static_lock();
test_assert(__LINE__, is_resource($fp), 'lock can be taken again');
kona3static_unlock($fp);

// --- 後片付け ---
sb_rmdir($dir);
if ($aliasBackup === null) {
    @unlink($aliasJson);
} else {
    file_put_contents($aliasJson, $aliasBackup);
}
@unlink($metaFile);
@unlink($manifest);
sb_rmdir($tmpRoot);
