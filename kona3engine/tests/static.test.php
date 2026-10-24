<?php
require_once __DIR__ . '/test_common.inc.php';

// 静的Wiki出力のテスト (docs/static_wiki.md)
global $kona3conf;

function static_test_throws($fn)
{
    try {
        $fn();
    } catch (Kona3StaticException $e) {
        return TRUE;
    }
    return FALSE;
}

function static_test_rmdir($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        if (is_dir($p) && !is_link($p)) {
            static_test_rmdir($p);
        } else {
            unlink($p);
        }
    }
    rmdir($dir);
}

$kona3conf['FrontPage'] = 'FrontPage';
$kona3conf['static_base_url'] = '/';

// --- 4.4 変換例 ---
$cases = [
    'FrontPage' => 'index.html',
    'はじめに' => 'はじめに/index.html',
    '使い方/基本操作' => '使い方/基本操作/index.html',
    '開発/API仕様' => '開発/API仕様/index.html',
    '質問?回答' => '質問_3F回答/index.html',
    'A:B' => 'A_3AB/index.html',
    '100%完成' => '100_25完成/index.html',
    'foo_bar' => 'foo_5Fbar/index.html',
    'Windows/設定' => 'Windows/設定/index.html',
    '.htaccess' => '_2Ehtaccess/index.html',
    'con' => '_63on/index.html',
    '末尾.' => '末尾_2E/index.html',
    ' 先頭空白' => '_20先頭空白/index.html',
    'a<b>c' => 'a_3Cb_3Ec/index.html',
    'a"b|c*d\\e' => 'a_22b_7Cc_2Ad_5Ce/index.html',
];
foreach ($cases as $name => $path) {
    test_eq(__LINE__, kona3getStaticFile($name), $path, "static file: $name");
}

// --- 予約名 ---
test_eq(__LINE__, kona3getStaticFile('CON'), '_43ON/index.html', 'reserved CON');
test_eq(__LINE__, kona3getStaticFile('Com1'), '_43om1/index.html', 'reserved Com1');
test_eq(__LINE__, kona3getStaticFile('NUL.txt'), '_4EUL.txt/index.html', 'reserved NUL.txt');
test_eq(__LINE__, kona3getStaticFile('console'), 'console/index.html', 'console is not reserved');
test_eq(__LINE__, kona3getStaticFile('開発/aux'), '開発/_61ux/index.html', 'reserved in sub dir');

// --- 復号 (可逆) ---
foreach (['質問?回答', 'foo_bar', '100%完成', 'a_5F_', '.x.', 'A:B"C'] as $name) {
    $enc = kona3static_encodeSegment($name);
    test_eq(__LINE__, kona3static_decodeSegment($enc), $name, "decode: $name");
}

// --- 拒否条件 ---
test_assert(__LINE__, static_test_throws(function () { kona3getStaticFile(''); }), 'reject empty');
test_assert(__LINE__, static_test_throws(function () { kona3getStaticFile('a//b'); }), 'reject empty segment');
test_assert(__LINE__, static_test_throws(function () { kona3getStaticFile('/abs'); }), 'reject absolute');
test_assert(__LINE__, static_test_throws(function () { kona3getStaticFile('a/'); }), 'reject trailing slash');
test_assert(__LINE__, static_test_throws(function () { kona3getStaticFile('a/../b'); }), 'reject ..');
test_assert(__LINE__, static_test_throws(function () { kona3getStaticFile('a/./b'); }), 'reject .');
test_assert(__LINE__, static_test_throws(function () { kona3getStaticFile("bad\xFF\xFE"); }), 'reject invalid utf-8');
test_assert(__LINE__, static_test_throws(function () { kona3getStaticFile(str_repeat('あ', 70)); }), 'reject long segment (210 bytes)');
test_assert(__LINE__, !static_test_throws(function () { kona3getStaticFile(str_repeat('a', 200)); }), 'accept 200 bytes');
test_assert(__LINE__, static_test_throws(function () { kona3getStaticFile(str_repeat('a', 201)); }), 'reject 201 bytes');
test_assert(__LINE__, static_test_throws(function () {
    kona3getStaticFile(implode('/', array_fill(0, 6, str_repeat('b', 190))));
}), 'reject long path');

// --- 衝突検出 ---
$nfc = "caf\u{00E9}";
$nfd = "cafe\u{0301}";
$col = kona3static_findCollisions(['About', 'about', 'Other', $nfc, $nfd, 'foo_bar', 'foo_5Fbar']);
test_eq(__LINE__, count($col), 2, 'collisions: case and NFC/NFD');
test_eq(__LINE__, kona3getStaticFile($nfc), kona3getStaticFile($nfd), 'NFC and NFD to the same path');
$col2 = kona3static_findCollisions(['foo_bar', 'foo_5Fbar']);
test_eq(__LINE__, count($col2), 0, '`_` escape does not collide with a literal `_5F`');
test_eq(__LINE__, count(kona3static_findCollisions(['a', 'b', 'a/b'])), 0, 'no collision');

// --- 公開URL ---
test_eq(__LINE__, kona3getStaticURL('FrontPage'), '/', 'url FrontPage');
test_eq(__LINE__, kona3getStaticURL('foo_bar'), '/foo_5Fbar/', 'url foo_bar');
test_eq(__LINE__, kona3getStaticURL('使い方/基本操作'), '/%E4%BD%BF%E3%81%84%E6%96%B9/%E5%9F%BA%E6%9C%AC%E6%93%8D%E4%BD%9C/', 'url multi-byte');
$kona3conf['static_base_url'] = '/wiki';
test_eq(__LINE__, kona3getStaticURL('FrontPage'), '/wiki/', 'url base /wiki');
test_eq(__LINE__, kona3getStaticURL('a/b'), '/wiki/a/b/', 'url base /wiki child');
$kona3conf['static_base_url'] = 'https://example.com/wiki/';
test_eq(__LINE__, kona3getStaticURL('a'), 'https://example.com/wiki/a/', 'url absolute base');
test_eq(__LINE__, kona3static_assetUrl('skin', 'def/kona3.css'), 'https://example.com/wiki/_kona3/skin/def/kona3.css', 'asset url');
$kona3conf['static_base_url'] = '/';
test_eq(__LINE__, kona3static_assetUrl('files', 'a b/c.png'), '/_kona3/files/a%20b/c.png', 'asset url encode');

// --- 生成した静的サイトを開くURL (#290) ---
$kona3conf['baseurl'] = 'http://localhost/wiki';
$kona3conf['static_outdir'] = 'dist';
test_eq(__LINE__, kona3static_outputTopUrl(), 'http://localhost/wiki/dist/', 'output top url: relative outdir');
$kona3conf['static_outdir'] = 'sub/dir';
test_eq(__LINE__, kona3static_outputTopUrl(), 'http://localhost/wiki/sub/dir/', 'output top url: nested outdir');
$kona3conf['static_outdir'] = '';
test_eq(__LINE__, kona3static_outputTopUrl(), 'http://localhost/wiki/dist/', 'output top url: default outdir is dist');
$kona3conf['static_outdir'] = '/var/www/dist';
test_eq(__LINE__, kona3static_outputTopUrl(), '/', 'output top url: absolute outdir falls back to public url');
$kona3conf['static_outdir'] = '../dist';
test_eq(__LINE__, kona3static_outputTopUrl(), '/', 'output top url: dotdot outdir falls back to public url');
unset($kona3conf['baseurl'], $kona3conf['static_outdir']);

// --- 静的モードでの kona3getPageURL ---
$kona3conf['static_mode'] = TRUE;
test_eq(__LINE__, kona3getPageURL('a/b'), '/a/b/', 'page url in static mode');
test_eq(__LINE__, kona3getPageURL('a/b', 'show'), '/a/b/', 'page url (show) in static mode');
test_assert(__LINE__, strpos(kona3getPageURL('a/b', 'edit'), 'index.php') !== FALSE, 'edit url stays dynamic');
test_eq(__LINE__, kona3getResourceURL('kona3def.css', TRUE), '/_kona3/resource/kona3def.css', 'resource url in static mode');
$kona3conf['static_mode'] = FALSE;
test_assert(__LINE__, strpos(kona3getPageURL('a/b'), 'index.php') !== FALSE, 'page url in normal mode');

// --- 動的リンク検出 ---
$bad = kona3static_findDynamicLinks('<a href="index.php?a&edit">x</a><a href="/ok/">y</a><img src=\'./go.php?x\'>');
test_eq(__LINE__, count($bad), 2, 'find dynamic links');
test_eq(__LINE__, count(kona3static_findDynamicLinks('<a href=index.php?a>x</a>')), 1, 'unquoted dynamic link');
$kona3conf['static_php_url'] = 'https://example.com/index.php';
test_eq(__LINE__, count(kona3static_findDynamicLinks('<a href="https://example.com/index.php">x</a>')), 0, 'static_php_url is allowed');
unset($kona3conf['static_php_url']);
test_eq(__LINE__, count(kona3static_findDynamicLinks('<a href="/a/index.html">x</a><a href="https://example.com/">y</a>')), 0, 'no dynamic links');

// --- 書き込み ---
$tmp = realpath(sys_get_temp_dir()) . '/kona3static_test_' . getmypid();
static_test_rmdir($tmp);
mkdir($tmp);
kona3static_writeFile($tmp, 'a/b/index.html', 'hello');
test_eq(__LINE__, file_get_contents("$tmp/a/b/index.html"), 'hello', 'write file');
kona3static_writeFile($tmp, 'a/b/index.html', 'world');
test_eq(__LINE__, file_get_contents("$tmp/a/b/index.html"), 'world', 'overwrite file');
test_eq(__LINE__, count(glob("$tmp/a/b/.kona3tmp_*")), 0, 'no temporary file is left');
test_assert(__LINE__, static_test_throws(function () use ($tmp) { kona3static_writeFile($tmp, '../evil.html', 'x'); }), 'reject ..');
test_assert(__LINE__, static_test_throws(function () use ($tmp) { kona3static_writeFile($tmp, 'a/../../evil.html', 'x'); }), 'reject nested ..');
test_assert(__LINE__, static_test_throws(function () use ($tmp) { kona3static_writeFile($tmp, '/etc/evil.html', 'x'); }), 'reject absolute');
test_assert(__LINE__, static_test_throws(function () use ($tmp) { kona3static_writeFile($tmp . '/nothing', 'x.html', 'x'); }), 'reject missing outdir');
test_assert(__LINE__, !file_exists(dirname($tmp) . '/evil.html'), 'nothing is written outside');
// シンボリックリンク経由で出力先の外に書けない
$outside = realpath(sys_get_temp_dir()) . '/kona3static_outside_' . getmypid();
static_test_rmdir($outside);
mkdir($outside);
if (@symlink($outside, "$tmp/link")) {
    test_assert(__LINE__, static_test_throws(function () use ($tmp) { kona3static_writeFile($tmp, 'link/x.html', 'x'); }), 'reject symlink escape');
    test_assert(__LINE__, !file_exists("$outside/x.html"), 'symlink target is untouched');
}
// 出力先自身がシンボリックリンクなら拒否する
$linkOut = realpath(sys_get_temp_dir()) . '/kona3static_linkout_' . getmypid();
@unlink($linkOut);
if (@symlink($outside, $linkOut)) {
    test_assert(__LINE__, static_test_throws(function () use ($linkOut) { kona3static_writeFile($linkOut, 'x.html', 'x'); }), 'reject symlinked outdir');
    test_assert(__LINE__, !file_exists("$outside/x.html"), 'symlinked outdir target is untouched');
    @unlink($linkOut);
}
static_test_rmdir($outside);

// --- ページの描画 ---
$kona3conf['wiki_private'] = FALSE;
$kona3conf['skin'] = 'def';
$dir_sub = KONA3_DIR_DATA . '/StaticTest';
$file_a = KONA3_DIR_DATA . '/StaticTest_A.txt';
$file_b = $dir_sub . '/B.md';
if (!is_dir($dir_sub)) {
    mkdir($dir_sub);
}
file_put_contents($file_a, "* Static Test A\nSee [[StaticTest/B]] and [[StaticTest_A]].\n");
file_put_contents($file_b, "# Static Test B\n\nbody of B [A](StaticTest_A)\n");

$kona3conf['page'] = 'FrontPage';
$kona3conf['css'] = ['keep-css'];
$html_a = kona3static_renderPage('StaticTest_A');
test_assert(__LINE__, strpos($html_a, 'Static Test A') !== FALSE, 'render A: body');
test_assert(__LINE__, strpos($html_a, 'href="/StaticTest/B/"') !== FALSE || strpos($html_a, "href='/StaticTest/B/'") !== FALSE, 'render A: link to B is a static url');
test_eq(__LINE__, count(kona3static_findDynamicLinks($html_a)), 0, 'render A: no dynamic links');
test_assert(__LINE__, strpos($html_a, 'edit_token') === FALSE, 'render A: no edit token');
test_assert(__LINE__, strpos($html_a, '/_kona3/resource/pure-min.css') !== FALSE, 'render A: static css url');
test_assert(__LINE__, strpos($html_a, 'name="robots"') === FALSE, 'render A: no noindex');
test_eq(__LINE__, $kona3conf['page'], 'FrontPage', 'render restores page');
test_eq(__LINE__, $kona3conf['css'], ['keep-css'], 'render restores css');
test_assert(__LINE__, empty($kona3conf['static_mode']), 'render restores static_mode');

$html_b = kona3static_renderPage('StaticTest/B');
test_assert(__LINE__, strpos($html_b, 'Static Test B') !== FALSE, 'render B: markdown body');
test_assert(__LINE__, strpos($html_b, 'Static Test A') === FALSE, 'render B: no state leak from A');
test_assert(__LINE__, strpos($html_b, "href='/StaticTest_5FA/'") !== FALSE, 'render B: link to A');
test_eq(__LINE__, count(kona3static_findDynamicLinks($html_b)), 0, 'render B: no dynamic links');

// ログイン中のセッションでも匿名で描画される
$_SESSION = [KONA3_SESSKEY_LOGIN => ['time' => time(), 'user' => 'admin', 'perm' => 'admin']];
$html_login = kona3static_renderPage('StaticTest_A');
test_assert(__LINE__, strpos($html_login, 'edit_token') === FALSE, 'render as anonymous: no edit token');
test_assert(__LINE__, strpos($html_login, 'logout') === FALSE, 'render as anonymous: no logout link');
test_assert(__LINE__, kona3isLogin(), 'login state is restored after render');
$_SESSION = [];

// 1ページ出力
$rel = kona3static_buildPage('StaticTest/B', $tmp);
test_eq(__LINE__, $rel, 'StaticTest/B/index.html', 'build page path');
test_assert(__LINE__, file_exists("$tmp/StaticTest/B/index.html"), 'build page file exists');
test_assert(__LINE__, strpos(file_get_contents("$tmp/StaticTest/B/index.html"), 'Static Test B') !== FALSE, 'build page content');

// 非公開 Wiki は出力できない
$kona3conf['wiki_private'] = TRUE;
test_assert(__LINE__, static_test_throws(function () { kona3static_renderPage('StaticTest_A'); }), 'private wiki is rejected');
test_assert(__LINE__, empty($kona3conf['static_mode']), 'static_mode is cleared after an error');
$kona3conf['wiki_private'] = FALSE;

// エイリアスはリダイレクトとして扱われる (例外)
$file_alias = KONA3_DIR_DATA . '/StaticTest_Alias.txt';
file_put_contents($file_alias, "!!alias(StaticTest_A)\n");
$redirected = FALSE;
try {
    kona3static_renderPage('StaticTest_Alias');
} catch (Kona3StaticRedirectException $e) {
    $redirected = ($e->target === '/StaticTest_5FA/');
}
test_assert(__LINE__, $redirected, 'alias raises a redirect exception');
test_assert(__LINE__, empty($kona3conf['static_mode']), 'static_mode is cleared after redirect');

// 後始末
@unlink($file_alias);
@unlink($file_a);
@unlink($file_b);
@rmdir($dir_sub);
static_test_rmdir($tmp);

// テンプレート用に static_mode が常に定義される (#275: 未定義変数の警告対策)
unset($kona3conf['static_mode']);
kona3template_prepare('show.html', []);
test_assert(__LINE__, array_key_exists('static_mode', $kona3conf) && $kona3conf['static_mode'] === FALSE, 'static_mode is defined as FALSE for templates');
$kona3conf['static_mode'] = TRUE;
kona3template_prepare('show.html', []);
test_assert(__LINE__, $kona3conf['static_mode'] === TRUE, 'static_mode stays TRUE in static mode');
$kona3conf['static_mode'] = FALSE;
