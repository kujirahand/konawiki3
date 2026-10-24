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

// ---------- #310 agy レビュー フォローアップ ----------

// 12. chapter: def=txt のときは .txt 側を優先する
$ch2_dir_name = 'ChapterDirTxt_' . $uniq;
$ch2_dir = $data_dir . '/' . $ch2_dir_name;
mkdir($ch2_dir);
$cleanup_dirs[] = $ch2_dir;
$mk($ch2_dir_name . '/Dup.txt', 'ChapterTxt Dup Txt Head');
$mk($ch2_dir_name . '/Dup.md', '# ChapterTxt Dup Md Head');
$kona3conf['page'] = 'FrontPage';
kona3setConf('def_text_ext', 'txt');
$html = kona3plugins_chapter_execute([$ch2_dir_name]);
test_assert(__LINE__, strpos($html, 'ChapterTxt Dup Txt Head') !== false, 'chapter should prefer .txt for duplicated page when def is txt');
test_assert(__LINE__, strpos($html, 'ChapterTxt Dup Md Head') === false, 'chapter should not list .md side when def is txt');
def_ext_cleanup();

// 13. chapter: ドットを含むページ名でも重複排除できる (def 両方向)
$ch3_dir_name = 'ChapterDot_' . $uniq;
$ch3_dir = $data_dir . '/' . $ch3_dir_name;
mkdir($ch3_dir);
$cleanup_dirs[] = $ch3_dir;
$mk($ch3_dir_name . '/Foo.bar.txt', 'DotName Txt Head');
$mk($ch3_dir_name . '/Foo.bar.md', '# DotName Md Head');
$kona3conf['page'] = 'FrontPage';
kona3setConf('def_text_ext', 'md');
$html = kona3plugins_chapter_execute([$ch3_dir_name]);
test_assert(__LINE__, strpos($html, 'DotName Md Head') !== false, 'chapter should list dotted page (.md) when def is md');
test_assert(__LINE__, strpos($html, 'DotName Txt Head') === false, 'chapter should not list dotted page twice');
test_eq(__LINE__, substr_count($html, '<li>'), 1, 'chapter should list dotted page exactly once');
kona3setConf('def_text_ext', 'txt');
$html = kona3plugins_chapter_execute([$ch3_dir_name]);
test_assert(__LINE__, strpos($html, 'DotName Txt Head') !== false, 'chapter should list dotted page (.txt) when def is txt');
test_assert(__LINE__, strpos($html, 'DotName Md Head') === false, 'chapter should not list .md side of dotted page when def is txt');
def_ext_cleanup();

// 14. countpages: def=txt のときは .txt 側を優先して数える
$cp2_dir_name = 'CountDirTxt_' . $uniq;
$cp2_dir = $data_dir . '/' . $cp2_dir_name;
mkdir($cp2_dir);
$cleanup_dirs[] = $cp2_dir;
$mk($cp2_dir_name . '/D.txt', 'xx');    // 2 chars (counted)
$mk($cp2_dir_name . '/D.md', 'yyyy');   // 4 chars (not counted)
$mk($cp2_dir_name . '/Q.md', '12345');  // 5 chars
kona3setConf('def_text_ext', 'txt');
$html = kona3plugins_countpages_execute([$cp2_dir_name]);
test_assert(__LINE__, strpos($html, 'Total 7ch') !== false, 'countpages should count .txt side when def is txt (2+5=7)');
test_assert(__LINE__, strpos($html, 'file:2') !== false, 'countpages should count duplicated page once when def is txt');
def_ext_cleanup();

// 15. countpages: ignore はページ単位で効く (どちらの拡張子でも除外される)
$cp3_dir_name = 'CountIgnore_' . $uniq;
$cp3_dir = $data_dir . '/' . $cp3_dir_name;
mkdir($cp3_dir);
$cleanup_dirs[] = $cp3_dir;
$mk($cp3_dir_name . '/Keep.txt', '111');   // 3 chars
$mk($cp3_dir_name . '/Dup.txt', 'xx');     // 2 chars
$mk($cp3_dir_name . '/Dup.md', 'yyyy');    // 4 chars
$mk($cp3_dir_name . '/Solo.md', '12345');  // 5 chars
kona3setConf('def_text_ext', 'md');
// (a) ignore なし: Keep + Dup(.md) + Solo = 3+4+5 = 12
$html = kona3plugins_countpages_execute([$cp3_dir_name]);
test_assert(__LINE__, strpos($html, 'Total 12ch') !== false, 'countpages ignore: baseline total without ignore');
// (b) ignore=Dup (ページ名指定) → Dup は両拡張子とも除外され 3+5 = 8
$html = kona3plugins_countpages_execute([$cp3_dir_name, 'ignore=Dup']);
test_assert(__LINE__, strpos($html, 'Total 8ch') !== false, 'countpages ignore: page name should exclude both extensions');
test_assert(__LINE__, strpos($html, 'file:2') !== false, 'countpages ignore: excluded page should not be counted');
// (c) ignore=Dup.txt (ファイル名指定) → ページ単位で除外され 8
$html = kona3plugins_countpages_execute([$cp3_dir_name, 'ignore=Dup.txt']);
test_assert(__LINE__, strpos($html, 'Total 8ch') !== false, 'countpages ignore: file name with extension should exclude the page');
// (d) ignore=Dup:Solo (複数指定) → Keep のみ 3
$html = kona3plugins_countpages_execute([$cp3_dir_name, 'ignore=Dup:Solo']);
test_assert(__LINE__, strpos($html, 'Total 3ch') !== false, 'countpages ignore: multiple keys separated by colon should work');
def_ext_cleanup();

// 16. countpages: ディレクトリ名の ignore は従来どおり効く
$cp4_dir_name = 'CountIgnoreDir_' . $uniq;
$cp4_dir = $data_dir . '/' . $cp4_dir_name;
mkdir($cp4_dir);
mkdir($cp4_dir . '/Sub');
$cleanup_dirs[] = $cp4_dir . '/Sub';
$cleanup_dirs[] = $cp4_dir;
$mk($cp4_dir_name . '/Top.md', 'ab');        // 2 chars
$mk($cp4_dir_name . '/Sub/Hidden.md', 'zzzz'); // 4 chars (ignored)
kona3setConf('def_text_ext', 'md');
$html = kona3plugins_countpages_execute([$cp4_dir_name, 'ignore=Sub']);
test_assert(__LINE__, strpos($html, 'Total 2ch') !== false, 'countpages ignore: directory name should exclude its files');
def_ext_cleanup();

// 17. kona3getWikiPage: メタデータ mode=Markdown は def=txt より優先される
$page17 = 'WikiPageMetaMd_' . $uniq;
def_ext_mkpage($page17, 'txt', 'META_TXT_MARKER');
def_ext_mkpage($page17, 'md', 'META_MD_MARKER');
kona3setConf('def_text_ext', 'txt');
kona3db_savePageMeta($page17, ['mode' => 'Markdown']);
$html = kona3getWikiPage($page17, 'DEF');
test_assert(__LINE__, strpos($html, 'META_MD_MARKER') !== false, 'getWikiPage should honor metadata Markdown over def txt');
test_assert(__LINE__, strpos($html, 'META_TXT_MARKER') === false, 'getWikiPage should not render .txt when metadata is Markdown');
def_ext_delmeta($page17);
def_ext_cleanup();

// 18. include: 片方だけ存在するページと、存在しないページ
$page18a = 'IncludeTxtOnly_' . $uniq;
def_ext_mkpage($page18a, 'txt', 'INCLUDE_TXT_ONLY_MARKER');
$page18b = 'IncludeMdOnly_' . $uniq;
def_ext_mkpage($page18b, 'md', 'INCLUDE_MD_ONLY_MARKER');
kona3setConf('def_text_ext', 'md');
$html = '';
$b = kona3plugins_include_file($page18a, $html);
test_assert(__LINE__, $b === true && strpos($html, 'INCLUDE_TXT_ONLY_MARKER') !== false, 'include should include .txt-only page');
$html = '';
$b = kona3plugins_include_file($page18b, $html);
test_assert(__LINE__, $b === true && strpos($html, 'INCLUDE_MD_ONLY_MARKER') !== false, 'include should include .md-only page');
$html = '';
$b = kona3plugins_include_file('IncludeMissing_' . $uniq, $html);
test_assert(__LINE__, $b === false, 'include should return false for missing page');
def_ext_cleanup();

// 19. counter: .txt のみのページでもカウンタが "-" にならない
$page19 = 'CounterTxt_' . $uniq;
def_ext_mkpage($page19, 'txt', 'Counter Txt Page');
$kona3conf['page'] = $page19;
kona3setConf('def_text_ext', 'md');
$html = kona3plugins_counter_execute([]);
test_assert(__LINE__, $html !== '-', 'counter should work on .txt-only page');
test_assert(__LINE__, preg_match('/\d/', $html) === 1, 'counter should show a number on .txt-only page');
def_ext_cleanup();

// 20. kona3template_prepare: data_filename は実ファイルを指す (不在時は def 側)
$page20 = 'TplPage_' . $uniq;
def_ext_mkpage($page20, 'txt', 'Tpl Txt Page');
$orig_data_filename = isset($kona3conf['data_filename']) ? $kona3conf['data_filename'] : null;
$kona3conf['page'] = $page20;
unset($kona3conf['data_filename']);
kona3setConf('def_text_ext', 'md');
kona3template_prepare('show', []);
test_assert(__LINE__, isset($kona3conf['data_filename']) && substr($kona3conf['data_filename'], -4) === '.txt' && file_exists($kona3conf['data_filename']), 'template_prepare should resolve existing .txt page for data_filename');
$kona3conf['page'] = 'TplMissing_' . $uniq;
unset($kona3conf['data_filename']);
kona3template_prepare('show', []);
test_assert(__LINE__, isset($kona3conf['data_filename']) && substr($kona3conf['data_filename'], -3) === '.md', 'template_prepare should fall back to def ext path for missing page');
if ($orig_data_filename === null) { unset($kona3conf['data_filename']); } else { $kona3conf['data_filename'] = $orig_data_filename; }
def_ext_cleanup();

// 21. countpages: ignore の照合は厳密比較 (数値文字列のページ名を誤って除外しない)
$cp5_dir_name = 'CountIgnoreNum_' . $uniq;
$cp5_dir = $data_dir . '/' . $cp5_dir_name;
mkdir($cp5_dir);
$cleanup_dirs[] = $cp5_dir;
$mk($cp5_dir_name . '/0.txt', 'ab');    // 2 chars (page "0")
$mk($cp5_dir_name . '/00.md', 'xyz');   // 3 chars (page "00")
kona3setConf('def_text_ext', 'md');
// ignore=00 → ページ "00" だけが除外され、ページ "0" は数えられる
$html = kona3plugins_countpages_execute([$cp5_dir_name, 'ignore=00']);
test_assert(__LINE__, strpos($html, 'Total 2ch') !== false, 'countpages ignore: numeric page "0" should not match ignore=00');
// ignore=0 → ページ "0" だけが除外され、ページ "00" は数えられる
$html = kona3plugins_countpages_execute([$cp5_dir_name, 'ignore=0']);
test_assert(__LINE__, strpos($html, 'Total 3ch') !== false, 'countpages ignore: numeric page "00" should not match ignore=0');
def_ext_cleanup();

// 22. Git フック: edit_ext フォールバックが実ファイル解決を使う (静的チェック)
$hook_src = file_get_contents(dirname(__DIR__) . '/kona3page_updated.inc.php');
test_assert(__LINE__, strpos($hook_src, 'koan3getWikiFileText($page)') !== false, 'git hook fallback should resolve the actual file via koan3getWikiFileText');

// 後片付け (後続テストへの影響を避ける)
$kona3conf['page'] = $orig_page;
kona3setConf('def_text_ext', 'md');
