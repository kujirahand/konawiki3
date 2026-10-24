<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/plugins/include.inc.php';
require_once dirname(__DIR__) . '/plugins/chapter.inc.php';
require_once dirname(__DIR__) . '/plugins/countpages.inc.php';
require_once dirname(__DIR__) . '/plugins/counter.inc.php';

// #302 フォローアップ: def_text_ext の既定値変更 (txt → md) に伴う互換性テスト
// - kona3getWikiPage() は実際に存在するファイルを表示する (MenuBar.txt 等が消えない)
// - chapter / countpages は .txt と .md の両方を対象にする
// - include / counter はメタデータと既定値に従ってファイルを解決する

global $kona3conf;
$data_dir = KONA3_DIR_DATA;
$meta_dir = $data_dir . '/.meta';
$orig_page = isset($kona3conf['page']) ? $kona3conf['page'] : 'FrontPage';
$uniq = time() . '_' . getmypid();

$cleanup_files = [];
$cleanup_dirs = [];
function def_ext_cleanup()
{
    global $cleanup_files, $cleanup_dirs;
    foreach ($cleanup_files as $f) { if (file_exists($f)) @unlink($f); }
    foreach ($cleanup_dirs as $d) { if (is_dir($d)) @rmdir($d); }
    $cleanup_files = [];
    $cleanup_dirs = [];
}
function def_ext_mkpage($name, $ext, $body)
{
    global $data_dir, $cleanup_files;
    $f = $data_dir . '/' . $name . '.' . $ext;
    file_put_contents($f, $body);
    $cleanup_files[] = $f;
    return $f;
}
function def_ext_delmeta($name)
{
    global $meta_dir, $cleanup_files;
    $mf = $meta_dir . '/' . $name . '.json';
    if (file_exists($mf)) @unlink($mf);
    if (function_exists('kona3db_deletePageMeta')) { kona3db_deletePageMeta($name); }
}

echo "=== def_text_ext followup (#302) Test ===\n";

// ---------- kona3getWikiPage ----------
// 1. .txt のみ存在 + def_text_ext 未設定 → .txt の内容が表示される (MenuBar 回帰)
$page1 = 'WikiPageOnlyTxt_' . $uniq;
def_ext_mkpage($page1, 'txt', 'WIKIPAGE_TXT_MARKER');
unset($kona3conf['def_text_ext']);
$html = kona3getWikiPage($page1, 'DEF');
test_assert(__LINE__, strpos($html, 'WIKIPAGE_TXT_MARKER') !== false, 'getWikiPage should render existing .txt when def_text_ext is not set');

// 2. .md のみ存在 + def_text_ext 未設定 → .md の内容が表示される
$page2 = 'WikiPageOnlyMd_' . $uniq;
def_ext_mkpage($page2, 'md', 'WIKIPAGE_MD_MARKER');
$html = kona3getWikiPage($page2, 'DEF');
test_assert(__LINE__, strpos($html, 'WIKIPAGE_MD_MARKER') !== false, 'getWikiPage should render existing .md when def_text_ext is not set');

// 3. 両方存在 + def=md → .md 優先
$page3 = 'WikiPageBoth_' . $uniq;
def_ext_mkpage($page3, 'txt', 'BOTH_TXT_MARKER');
def_ext_mkpage($page3, 'md', 'BOTH_MD_MARKER');
kona3setConf('def_text_ext', 'md');
$html = kona3getWikiPage($page3, 'DEF');
test_assert(__LINE__, strpos($html, 'BOTH_MD_MARKER') !== false, 'getWikiPage should prefer .md when def is md');
test_assert(__LINE__, strpos($html, 'BOTH_TXT_MARKER') === false, 'getWikiPage should not render .txt when def is md');

// 4. 両方存在 + def=txt → .txt 優先
kona3setConf('def_text_ext', 'txt');
$html = kona3getWikiPage($page3, 'DEF');
test_assert(__LINE__, strpos($html, 'BOTH_TXT_MARKER') !== false, 'getWikiPage should prefer .txt when def is txt');

// 5. メタデータ mode=KonaNotation は def=md より優先される
kona3setConf('def_text_ext', 'md');
kona3db_savePageMeta($page3, ['mode' => 'KonaNotation']);
$html = kona3getWikiPage($page3, 'DEF');
test_assert(__LINE__, strpos($html, 'BOTH_TXT_MARKER') !== false, 'getWikiPage should honor metadata mode over def');
def_ext_delmeta($page3);

// 6. 存在しないページ → $def を返す
$html = kona3getWikiPage('WikiPageMissing_' . $uniq, 'FALLBACK_DEF');
test_eq(__LINE__, $html, 'FALLBACK_DEF', 'getWikiPage should return default for missing page');
def_ext_cleanup();

// ---------- chapter ----------
// 7. .txt と .md の両方が一覧に出る。同名ページは def 側だけが1件出る
$ch_dir_name = 'ChapterDir_' . $uniq;
$ch_dir = $data_dir . '/' . $ch_dir_name;
mkdir($ch_dir);
$cleanup_dirs[] = $ch_dir;
$mk = function ($rel, $body) use ($data_dir, &$cleanup_files) {
    $f = $data_dir . '/' . $rel;
    file_put_contents($f, $body);
    $cleanup_files[] = $f;
};
$mk($ch_dir_name . '/Alpha.txt', 'Chapter Alpha Txt Head');
$mk($ch_dir_name . '/Beta.md', '# Chapter Beta Md Head');
$mk($ch_dir_name . '/Dup.txt', 'Chapter Dup Txt Head');
$mk($ch_dir_name . '/Dup.md', '# Chapter Dup Md Head');
$kona3conf['page'] = 'FrontPage';
kona3setConf('def_text_ext', 'md');
$html = kona3plugins_chapter_execute([$ch_dir_name]);
test_assert(__LINE__, strpos($html, 'Chapter Alpha Txt Head') !== false, 'chapter should list .txt pages');
test_assert(__LINE__, strpos($html, 'Chapter Beta Md Head') !== false, 'chapter should list .md pages');
test_assert(__LINE__, strpos($html, 'Chapter Dup Md Head') !== false, 'chapter should prefer .md for duplicated page when def is md');
test_assert(__LINE__, strpos($html, 'Chapter Dup Txt Head') === false, 'chapter should not list duplicated page twice');
def_ext_cleanup();

// ---------- countpages ----------
// 8. .txt と .md の両方が集計される。同名ページは def 側だけを数える
$cp_dir_name = 'CountDir_' . $uniq;
$cp_dir = $data_dir . '/' . $cp_dir_name;
mkdir($cp_dir);
$cleanup_dirs[] = $cp_dir;
$mk($cp_dir_name . '/P1.txt', '12345');   // 5 chars
$mk($cp_dir_name . '/P2.md', '1234567');  // 7 chars
$mk($cp_dir_name . '/Dup.txt', 'xx');    // 2 chars (not counted)
$mk($cp_dir_name . '/Dup.md', 'yyyy');   // 4 chars (counted)
kona3setConf('def_text_ext', 'md');
$html = kona3plugins_countpages_execute([$cp_dir_name]);
test_assert(__LINE__, strpos($html, 'Total 16ch') !== false, 'countpages should count both .txt and .md (5+7+4=16)');
test_assert(__LINE__, strpos($html, 'file:3') !== false, 'countpages should count duplicated page once');
def_ext_cleanup();

// ---------- include ----------
// 9. 両方存在 + def=md → .md が取り込まれる (従来は常に .txt 優先だった)
$page9 = 'IncludeBoth_' . $uniq;
def_ext_mkpage($page9, 'txt', 'INCLUDE_TXT_MARKER');
def_ext_mkpage($page9, 'md', 'INCLUDE_MD_MARKER');
kona3setConf('def_text_ext', 'md');
$html = '';
$b = kona3plugins_include_file($page9, $html);
test_assert(__LINE__, $b === true, 'include should find the page');
test_assert(__LINE__, strpos($html, 'INCLUDE_MD_MARKER') !== false, 'include should prefer .md when def is md');
// 10. メタデータ mode=KonaNotation → .txt が取り込まれる
kona3db_savePageMeta($page9, ['mode' => 'KonaNotation']);
$html = '';
$b = kona3plugins_include_file($page9, $html);
test_assert(__LINE__, strpos($html, 'INCLUDE_TXT_MARKER') !== false, 'include should honor metadata mode');
def_ext_delmeta($page9);
def_ext_cleanup();

// ---------- counter ----------
// 11. .md のみのページでもカウンタが "-" にならない
$page11 = 'CounterMd_' . $uniq;
def_ext_mkpage($page11, 'md', '# Counter Md Page');
$kona3conf['page'] = $page11;
kona3setConf('def_text_ext', 'md');
$html = kona3plugins_counter_execute([]);
test_assert(__LINE__, $html !== '-', 'counter should work on .md-only page');
test_assert(__LINE__, preg_match('/\d/', $html) === 1, 'counter should show a number on .md-only page');
def_ext_cleanup();

// 後片付け (後続テストへの影響を避ける)
$kona3conf['page'] = $orig_page;
kona3setConf('def_text_ext', 'md');
