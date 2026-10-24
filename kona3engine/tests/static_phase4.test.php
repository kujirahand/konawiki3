<?php
require_once __DIR__ . '/test_common.inc.php';

// 静的Wiki出力 フェーズ4 (クライアントサイド検索・タグ一覧ページ) のテスト
global $kona3conf;

function sp4_rmdir($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        if (is_dir($p) && !is_link($p)) {
            sp4_rmdir($p);
        } else {
            unlink($p);
        }
    }
    rmdir($dir);
}

// --- 準備 ---
$tmpRoot = realpath(sys_get_temp_dir()) . '/kona3static_p4_' . getmypid();
sp4_rmdir($tmpRoot);
mkdir($tmpRoot);
$out = $tmpRoot . '/out';
$dir = KONA3_DIR_DATA . '/StaticP4';
sp4_rmdir($dir);
mkdir($dir);
file_put_contents("$dir/A.txt", "* Page A\nalpha keyword 検索対象\n#iflogin(secret-only-for-login)\n");
file_put_contents("$dir/B.txt", "* Page B\nbeta\n");
file_put_contents("$dir/C.txt", "* Page C\n");
$metaA = kona3db_getPageMetaFile('StaticP4/A');
$metaB = kona3db_getPageMetaFile('StaticP4/B');
@mkdir(dirname($metaA), 0777, true);
file_put_contents($metaA, json_encode(['tags' => ['TagOne', 'a/b']]));
file_put_contents($metaB, json_encode(['tags' => ['TagOne', 'tagone']]));

// 表示されない側のファイル (.md と .txt が両方ある場合) と、Markdown の !!iflogin は載せない
file_put_contents("$dir/D.txt", "* Page D\nshown-text\n");
file_put_contents("$dir/D.md", "# D\nhidden-md-text\n");
file_put_contents("$dir/E.md", "# E\n!!iflogin(md-secret)\n");
// 数字だけのタグ名・ページ名
file_put_contents("$dir/123.txt", "* Num\n");
$metaN = kona3db_getPageMetaFile('StaticP4/123');
file_put_contents($metaN, json_encode(['tags' => ['123']]));

$kona3conf['wiki_private'] = FALSE;
$kona3conf['skin'] = 'def';
$kona3conf['static_base_url'] = '/';
$kona3conf['static_enable'] = TRUE;
$kona3conf['static_outdir'] = $out;
$manifest = kona3static_manifestFile();
@unlink($manifest);
@unlink(KONA3_DIR_CACHE . '/static_error.log');
foreach (glob(KONA3_DIR_CACHE . '/static_pending*') as $f) { @unlink($f); }

$r = kona3static_buildAll();
test_eq(__LINE__, implode("\n", $r['errors']), '', 'build: no errors');

// --- URL ---
test_eq(__LINE__, kona3static_tagUrl(), '/_kona3/tags/', 'tagUrl: list');
test_eq(__LINE__, kona3static_tagUrl('TagOne'), '/_kona3/tags/tagone/', 'tagUrl: tag');
test_eq(__LINE__, kona3static_tagUrl('a/b'), '/_kona3/tags/a_2Fb/', 'tagUrl: slash is one segment');
test_eq(__LINE__, kona3static_searchUrl(), '/_kona3/search/', 'searchUrl');

// --- 検索インデックス ---
$json = (string)@file_get_contents("$out/_kona3/search/index.json");
$idx = json_decode($json, true);
test_assert(__LINE__, is_array($idx), 'search: index.json is valid JSON');
$byTitle = [];
foreach ((array)$idx as $e) {
    $byTitle[$e['t']] = $e;
}
test_assert(__LINE__, isset($byTitle['StaticP4/A']), 'search: page is indexed');
test_eq(__LINE__, $byTitle['StaticP4/A']['u'], '../../StaticP4/A/', 'search: page URL is relative to the search page (#287)');
test_assert(__LINE__, strpos($byTitle['StaticP4/A']['b'], '検索対象') !== FALSE, 'search: body is indexed');
test_assert(__LINE__, strpos($json, 'secret-only-for-login') === FALSE, 'search: login-only lines are not indexed');
test_eq(__LINE__, json_encode($byTitle['StaticP4/A']['g']), '["TagOne","a\\/b"]', 'search: tags are indexed');
test_assert(__LINE__, !isset($byTitle['MenuBar']), 'search: excluded pages are not indexed');
$sp = (string)@file_get_contents("$out/_kona3/search/index.html");
test_assert(__LINE__, strpos($sp, "fetch('index.json')") !== FALSE, 'search: page loads the index relatively (#287)');
test_assert(__LINE__, strpos($sp, 'index.php?') === FALSE, 'search: page has no dynamic links');

test_assert(__LINE__, (strpos($json, 'shown-text') !== FALSE) !== (strpos($json, 'hidden-md-text') !== FALSE), 'search: only the displayed source is indexed');
test_assert(__LINE__, strpos($json, 'md-secret') === FALSE, 'search: markdown !!iflogin is not indexed');
test_assert(__LINE__, is_file("$out/_kona3/tags/123/index.html"), 'tags: numeric tag is generated');

// --- タグ一覧ページ ---
$tl = (string)@file_get_contents("$out/_kona3/tags/index.html");
test_assert(__LINE__, strpos($tl, 'href="../../_kona3/tags/tagone/"') !== FALSE, 'tags: list links to a tag (relative, #287)');
test_assert(__LINE__, strpos($tl, '(2)') !== FALSE, 'tags: page count');
$tp = (string)@file_get_contents("$out/_kona3/tags/tagone/index.html");
test_assert(__LINE__, strpos($tp, 'href="../../../StaticP4/A/"') !== FALSE && strpos($tp, 'href="../../../StaticP4/B/"') !== FALSE, 'tag page: lists pages (relative, #287)');
test_assert(__LINE__, strpos($tp, 'StaticP4/C') === FALSE, 'tag page: untagged page is not listed');
test_assert(__LINE__, is_file("$out/_kona3/tags/a_2Fb/index.html"), 'tag page: slash tag is generated');
test_eq(__LINE__, substr_count($tl, 'href="../../_kona3/tags/tagone/"'), 1, 'tags: case-variant tags are merged into one entry');
test_eq(__LINE__, kona3static_tagUrl('tagone'), kona3static_tagUrl('TagOne'), 'tags: case-variant tags share a URL');

// --- ページ側のタグリンク ---
$a = (string)@file_get_contents("$out/StaticP4/A/index.html");
test_assert(__LINE__, strpos($a, "href='../../_kona3/tags/tagone/'") !== FALSE, 'show: tag links to the tag page (relative, #287)');
test_assert(__LINE__, strpos($a, 'href="../../_kona3/tags/"') !== FALSE, 'show: Tags links to the tag list (relative, #287)');

// --- 再生成で古いタグページが消える ---
file_put_contents($metaA, json_encode(['tags' => ['TagOne']]));
touch($metaA, time() + 5);
kona3static_buildAll();
test_assert(__LINE__, !file_exists("$out/_kona3/tags/a_2Fb/index.html"), 'rebuild: stale tag page is removed');

// --- 後片付け ---
@unlink($metaA);
@unlink($metaB);
@unlink($metaN);
sp4_rmdir($dir);
@unlink($manifest);
@unlink(KONA3_DIR_CACHE . '/static_error.log');
foreach (glob(KONA3_DIR_CACHE . '/static_pending*') as $f) { @unlink($f); }
sp4_rmdir($tmpRoot);
