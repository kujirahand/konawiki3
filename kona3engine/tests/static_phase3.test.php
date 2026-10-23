<?php
require_once __DIR__ . '/test_common.inc.php';

// 静的Wiki出力 フェーズ3 (編集時の1ページ生成・差分生成・pending・sitemap.xml・ボタン) のテスト
global $kona3conf;

function sp_rmdir($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        if (is_dir($p) && !is_link($p)) {
            sp_rmdir($p);
        } else {
            unlink($p);
        }
    }
    rmdir($dir);
}

function sp_throws($fn)
{
    try {
        $fn();
    } catch (Kona3StaticException $e) {
        return TRUE;
    }
    return FALSE;
}

// --- 準備 ---
$tmpRoot = realpath(sys_get_temp_dir()) . '/kona3static_p3_' . getmypid();
sp_rmdir($tmpRoot);
mkdir($tmpRoot);
$out = $tmpRoot . '/out';
$dir = KONA3_DIR_DATA . '/StaticP3';
sp_rmdir($dir);
mkdir($dir);
file_put_contents("$dir/A.txt", "* Page A\n");
file_put_contents("$dir/B.txt", "* Page B\n");
file_put_contents("$dir/List.txt", "* List\n#ls\n");

$kona3conf['wiki_private'] = FALSE;
$kona3conf['skin'] = 'def';
$kona3conf['static_base_url'] = '/';
$kona3conf['static_enable'] = TRUE;
$kona3conf['static_outdir'] = $out;
$manifest = kona3static_manifestFile();
@unlink($manifest);
@unlink(KONA3_DIR_CACHE . '/static_error.log');
foreach (glob(KONA3_DIR_CACHE . '/static_pending*') as $f) { @unlink($f); }

$past = time() - 1000;
$aOut = "$out/StaticP3/A/index.html";
$bOut = "$out/StaticP3/B/index.html";

// --- 差分生成 ---
$r = kona3static_buildAll();
test_eq(__LINE__, implode("\n", $r['errors']), '', 'build: no errors');
test_assert(__LINE__, file_exists($aOut) && file_exists($bOut), 'build: pages are generated');
test_assert(__LINE__, $r['pages'] > 0 && $r['skipped'] === 0, 'first build generates everything');

// 出力が新しいページは再生成されない
touch("$dir/A.txt", $past);
touch("$dir/B.txt", $past);
touch($aOut, time());
touch($bOut, time());
file_put_contents($aOut, 'MARK-A');
file_put_contents($bOut, 'MARK-B');
touch($aOut, time() - 10);
touch($bOut, time() - 10);
$r = kona3static_buildAll();
test_eq(__LINE__, file_get_contents($aOut), 'MARK-A', 'diff: up-to-date page is not rebuilt');
test_eq(__LINE__, file_get_contents($bOut), 'MARK-B', 'diff: up-to-date page B is not rebuilt');
test_assert(__LINE__, $r['skipped'] >= 2, 'diff: skipped count');

// ソースが新しいページだけ再生成される
touch("$dir/A.txt", time());
touch($aOut, time() - 10);
touch($bOut, time() - 10);
$r = kona3static_buildAll();
test_assert(__LINE__, strpos(file_get_contents($aOut), 'Page A') !== FALSE, 'diff: updated source is rebuilt');
test_eq(__LINE__, file_get_contents($bOut), 'MARK-B', 'diff: other page is not rebuilt');

// メタデータの更新も検出される
$metaFile = kona3db_getPageMetaFile('StaticP3/B');
@mkdir(dirname($metaFile), 0777, true);
file_put_contents($metaFile, json_encode(['x' => 1]));
touch($bOut, time() - 10);
touch($metaFile, time());
kona3static_buildAll();
test_assert(__LINE__, strpos(file_get_contents($bOut), 'Page B') !== FALSE, 'diff: meta update is detected');
@unlink($metaFile);

// 出力が無いページは生成される
unlink($bOut);
kona3static_buildAll();
test_assert(__LINE__, file_exists($bOut), 'diff: missing output is generated');

// 揮発ページ (#ls) は毎回再生成される
$listOut = "$out/StaticP3/List/index.html";
touch("$dir/List.txt", $past);
file_put_contents($listOut, 'MARK-L');
touch($listOut, time() - 10);
kona3static_buildAll();
test_assert(__LINE__, file_get_contents($listOut) !== 'MARK-L', 'diff: volatile page is always rebuilt');

// 失敗したページは mtime に関わらず再試行される
$m = json_decode(file_get_contents($manifest), true);
$m['pages']['StaticP3/A']['state'] = 'failed';
file_put_contents($manifest, json_encode($m));
file_put_contents($aOut, 'MARK-A');
touch($aOut, time() + 100);
kona3static_buildAll();
test_assert(__LINE__, strpos(file_get_contents($aOut), 'Page A') !== FALSE, 'diff: failed page is retried');

// 設定の更新で全ページが再生成される
$confFile = KONA3_DIR_PRIVATE . '/kona3conf.json.php';
$confExisted = file_exists($confFile);
$confBackupTime = $confExisted ? filemtime($confFile) : 0;
if ($confExisted) {
    file_put_contents($bOut, 'MARK-B');
    touch($bOut, time() + 100);
    touch($confFile, time() + 50);
    kona3static_buildAll();
    test_assert(__LINE__, strpos(file_get_contents($bOut), 'Page B') !== FALSE, 'diff: conf update rebuilds all pages');
    touch($confFile, $confBackupTime);
}

// 「すべて再生成」は差分判定をしない
file_put_contents($bOut, 'MARK-B');
touch($bOut, time() + 100);
kona3static_buildAll();
test_eq(__LINE__, file_get_contents($bOut), 'MARK-B', 'full: control (diff skips)');
kona3static_buildAll(['full' => TRUE]);
test_assert(__LINE__, strpos(file_get_contents($bOut), 'Page B') !== FALSE, 'full: rebuilds every page');

// --- 編集時の1ページ生成 ---
kona3static_buildAll(['full' => TRUE]);
file_put_contents($aOut, 'MARK-A');
file_put_contents($bOut, 'MARK-B');
file_put_contents("$dir/A.txt", "* Page A2\n");
$w = kona3static_onPageSaved('StaticP3/A');
test_eq(__LINE__, $w, '', 'save: no warning');
test_assert(__LINE__, strpos(file_get_contents($aOut), 'Page A2') !== FALSE, 'save: the saved page is generated');
test_eq(__LINE__, file_get_contents($bOut), 'MARK-B', 'save: other pages are untouched');
test_eq(__LINE__, $GLOBALS['kona3conf']['static_enable'], TRUE, 'save: conf is restored');

// 新規ページ
file_put_contents("$dir/C.txt", "* Page C\n");
kona3static_onPageSaved('StaticP3/C');
test_assert(__LINE__, file_exists("$out/StaticP3/C/index.html"), 'save: new page is generated');
$m = json_decode(file_get_contents($manifest), true);
test_assert(__LINE__, isset($m['pages']['StaticP3/C']) && $m['pages']['StaticP3/C']['state'] === 'ok', 'save: new page is recorded');

// 削除すると旧出力が消える
unlink("$dir/C.txt");
kona3static_onPageSaved('StaticP3/C');
test_assert(__LINE__, !file_exists("$out/StaticP3/C/index.html") && !is_dir("$out/StaticP3/C"), 'save: deleted page output is removed');
$m = json_decode(file_get_contents($manifest), true);
test_assert(__LINE__, !isset($m['pages']['StaticP3/C']), 'save: deleted page is unrecorded');

// 除外ページは生成されない
file_put_contents("$dir/D.txt", "* Page D\n");
$kona3conf['static_exclude'] = ['StaticP3/D'];
kona3static_onPageSaved('StaticP3/D');
test_assert(__LINE__, !file_exists("$out/StaticP3/D/index.html"), 'save: excluded page is not generated');
unset($kona3conf['static_exclude']);

// static_enable がオフなら何もしない
$kona3conf['static_enable'] = FALSE;
file_put_contents("$dir/E.txt", "* Page E\n");
kona3static_onPageSaved('StaticP3/E');
test_assert(__LINE__, !file_exists("$out/StaticP3/E/index.html"), 'save: nothing is generated while disabled');
$kona3conf['static_enable'] = TRUE;

// 生成に失敗しても例外にならず、警告とログが残る
$kona3conf['static_outdir'] = KONA3_DIR_DATA;
$w = kona3static_onPageSaved('StaticP3/A');
test_assert(__LINE__, $w !== '', 'save: failure gives a warning');
test_assert(__LINE__, file_exists(KONA3_DIR_CACHE . '/static_error.log'), 'save: failure is logged');
$kona3conf['static_outdir'] = $out;

// 非公開Wikiでは生成されず、出力が消える
$kona3conf['wiki_private'] = TRUE;
kona3static_onPageSaved('StaticP3/A');
test_assert(__LINE__, !file_exists($aOut), 'private: output is purged on save');
$kona3conf['wiki_private'] = FALSE;
kona3static_buildAll(['full' => TRUE]);

// --- 排他と pending ---
$fp = kona3static_lock();
test_assert(__LINE__, sp_throws(function () { kona3static_buildAll(); }), 'lock: build while locked is refused');
kona3static_unlock($fp);

// ロック中の1ページ生成は pending に記録され、次の生成で反映される (別プロセスでロックを保持)
$lockFile = KONA3_DIR_CACHE . '/static_build.lock';
$holder = proc_open(
    ['php', '-r', '$f=fopen($argv[1],"c");flock($f,LOCK_EX);echo "ok\n";sleep(8);', $lockFile],
    [1 => ['pipe', 'w']],
    $pipes
);
fgets($pipes[1]);
file_put_contents("$dir/B.txt", "* Page B2\n");
touch("$dir/B.txt", $past);
file_put_contents($bOut, 'MARK-B');
touch($bOut, time() + 100);
$t = microtime(true);
$w = kona3static_onPageSaved('StaticP3/B');
test_eq(__LINE__, $w, '', 'pending: no warning when queued');
test_assert(__LINE__, microtime(true) - $t >= 4.5, 'pending: waits for the lock');
test_assert(__LINE__, file_exists(kona3static_pendingFile()), 'pending: page is queued');
test_eq(__LINE__, file_get_contents($bOut), 'MARK-B', 'pending: page is not generated while locked');
proc_terminate($holder);
proc_close($holder);
$r = kona3static_buildAll();
test_assert(__LINE__, strpos(file_get_contents($bOut), 'Page B2') !== FALSE, 'pending: queued page is generated by the next build');
test_assert(__LINE__, !kona3static_hasPending(), 'pending: queue is cleared');

// 異常終了で残った pending も処理される
file_put_contents(KONA3_DIR_CACHE . '/static_pending.dead.json', json_encode('StaticP3/B') . "\n");
file_put_contents($bOut, 'MARK-B');
touch($bOut, time() + 100);
kona3static_buildAll();
test_assert(__LINE__, strpos(file_get_contents($bOut), 'Page B2') !== FALSE, 'pending: leftover file is processed');
test_assert(__LINE__, !file_exists(KONA3_DIR_CACHE . '/static_pending.dead.json'), 'pending: leftover file is removed');

// --- sitemap.xml ---
test_assert(__LINE__, !file_exists("$out/sitemap.xml"), 'sitemap: not generated for a relative base URL');
$kona3conf['static_base_url'] = 'https://example.com/wiki/';
kona3static_buildAll();
$sm = (string)@file_get_contents("$out/sitemap.xml");
test_assert(__LINE__, strpos($sm, '<loc>https://example.com/wiki/StaticP3/A/</loc>') !== FALSE, 'sitemap: page URL');
test_assert(__LINE__, strpos($sm, '<lastmod>') !== FALSE, 'sitemap: lastmod');
test_assert(__LINE__, strpos($sm, 'MenuBar') === FALSE, 'sitemap: excluded pages are not listed');
test_assert(__LINE__, simplexml_load_string($sm) !== FALSE, 'sitemap: valid XML');
$kona3conf['static_base_url'] = '/';
kona3static_buildAll();
test_assert(__LINE__, !file_exists("$out/sitemap.xml"), 'sitemap: removed when the base URL is relative');

// --- 管理画面のボタン (static アクション) ---
function sp_request($mode, $post, $admin)
{
    $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
    $_SESSION = [];
    if ($admin) {
        $_SESSION[KONA3_SESSKEY_LOGIN] = ['time' => time(), 'perm' => 'admin', 'user' => 'admin'];
    }
    $token = kona3_getEditToken('edit_token');
    $_POST = ['edit_token' => $token];
    return kona3static_handleRequest($mode);
}
test_assert(__LINE__, sp_throws(function () { sp_request('build', true, false); }), 'button: non-admin is refused');
test_assert(__LINE__, sp_throws(function () { sp_request('build', false, true); }), 'button: GET is refused');
test_assert(__LINE__, sp_throws(function () {
    sp_request('build', true, true);
    $_POST = [];
    kona3static_handleRequest('build');
}), 'button: missing token is refused');
$html = sp_request('build', true, true);
test_assert(__LINE__, strpos($html, '<p>') !== FALSE, 'button: build result is shown');
$kona3conf['static_enable'] = FALSE;
test_assert(__LINE__, sp_throws(function () { sp_request('build', true, true); }), 'button: build is refused while disabled');
test_assert(__LINE__, kona3static_hasOutput(), 'button: output is recorded');
$html = sp_request('purge', true, true);
test_assert(__LINE__, !file_exists($aOut), 'button: purge works while disabled');
test_assert(__LINE__, !kona3static_hasOutput(), 'button: purge empties the record');
$kona3conf['static_enable'] = TRUE;
$_SESSION = [];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';

// --- 後片付け ---
sp_rmdir($dir);
@unlink($manifest);
@unlink(KONA3_DIR_CACHE . '/static_error.log');
foreach (glob(KONA3_DIR_CACHE . '/static_pending*') as $f) { @unlink($f); }
sp_rmdir($tmpRoot);
