<?php
/**
 * Issue #53: 編集できるファイル形式のリスト
 * - 直接編集できるのは .txt / .md だけ
 * - それ以外の拡張子 (php, html, json など) はページ名の一部として扱い、
 *   ファイル名に .txt / .md を付与する (例: xxxx.php → xxxx.php.txt / .md)
 */
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/action/edit.inc.php';

global $kona3conf;

echo "=== Editable Ext (#53) Test ===\n";

$editable_data = KONA3_DIR_DATA;
$editable_cleanup = function ($names) use ($editable_data) {
    foreach ($names as $name) {
        foreach (['.txt', '.md', ''] as $suffix) {
            $f = $editable_data . '/' . $name . $suffix;
            if (is_file($f)) @unlink($f);
        }
        $meta = kona3db_getPageMetaFile($name);
        if ($meta && is_file($meta)) @unlink($meta);
    }
};

// --- 1. リストの定義 ---
test_eq(__LINE__, kona3getEditableExtList(), ['txt', 'md'], 'editable ext list is txt and md only');
test_assert(__LINE__, kona3isEditableExt('txt'), 'txt is editable');
test_assert(__LINE__, kona3isEditableExt('md'), 'md is editable');
test_assert(__LINE__, kona3isEditableExt('.txt'), 'leading dot is allowed');
test_assert(__LINE__, !kona3isEditableExt('MD'), 'uppercase MD is not directly editable (case-sensitive, like other txt|md matchers)');
test_assert(__LINE__, !kona3isEditableExt('TXT'), 'uppercase TXT is not directly editable');
test_assert(__LINE__, !kona3isEditableExt('php'), 'php is not directly editable');
test_assert(__LINE__, !kona3isEditableExt('html'), 'html is not directly editable');
test_assert(__LINE__, !kona3isEditableExt('json'), 'json is not directly editable');
test_assert(__LINE__, !kona3isEditableExt(''), 'empty ext is not editable');

// --- 2. kona3stripEditableExt() ---
test_eq(__LINE__, kona3stripEditableExt('Page.txt'), 'Page', 'strip .txt');
test_eq(__LINE__, kona3stripEditableExt('Page.md'), 'Page', 'strip .md');
test_eq(__LINE__, kona3stripEditableExt('dir/Page.txt'), 'dir/Page', 'strip .txt in subdir');
test_eq(__LINE__, kona3stripEditableExt('xxxx.php'), 'xxxx.php', 'do not strip .php');
test_eq(__LINE__, kona3stripEditableExt('test.html'), 'test.html', 'do not strip .html');
test_eq(__LINE__, kona3stripEditableExt('Page'), 'Page', 'no ext is unchanged');
test_eq(__LINE__, kona3stripEditableExt('archive.txt.php'), 'archive.txt.php', 'only the last ext matters');
test_eq(__LINE__, kona3stripEditableExt('Page.TXT'), 'Page.TXT', 'uppercase .TXT is part of the page name');
test_eq(__LINE__, kona3stripEditableExt('Page.txt.md'), 'Page.txt', 'strip only the last editable ext');

// --- 3. kona3getEditFile(): 直接編集 ---
$ext = '';
$f = kona3getEditFile('Direct.txt', $ext);
test_eq(__LINE__, $f, $editable_data . '/Direct.txt', 'Direct.txt is edited directly');
test_eq(__LINE__, $ext, 'txt', 'Direct.txt ext is txt');
$f = kona3getEditFile('Direct.md', $ext);
test_eq(__LINE__, $f, $editable_data . '/Direct.md', 'Direct.md is edited directly');
test_eq(__LINE__, $ext, 'md', 'Direct.md ext is md');

// --- 4. kona3getEditFile(): それ以外の拡張子には .txt / .md を付与 ---
kona3setConf('def_text_ext', 'md');
$f = kona3getEditFile('xxxx.php', $ext);
test_eq(__LINE__, $f, $editable_data . '/xxxx.php.md', 'xxxx.php gets .md appended when def is md');
test_eq(__LINE__, $ext, 'md', 'xxxx.php ext is md when def is md');

kona3setConf('def_text_ext', 'txt');
$f = kona3getEditFile('xxxx.php', $ext);
test_eq(__LINE__, $f, $editable_data . '/xxxx.php.txt', 'xxxx.php gets .txt appended when def is txt');
test_eq(__LINE__, $ext, 'txt', 'xxxx.php ext is txt when def is txt');

kona3setConf('def_text_ext', 'md');
$f = kona3getEditFile('test.html', $ext);
test_eq(__LINE__, $f, $editable_data . '/test.html.md', 'test.html gets .md appended (no longer edited directly)');
$f = kona3getEditFile('data.json', $ext);
test_eq(__LINE__, $f, $editable_data . '/data.json.md', 'data.json gets .md appended');
$f = kona3getEditFile('Sub/xxxx.php', $ext);
test_eq(__LINE__, $f, $editable_data . '/Sub/xxxx.php.md', 'subdir xxxx.php gets .md appended');
$f = kona3getEditFile('NoExtPage', $ext);
test_eq(__LINE__, $f, $editable_data . '/NoExtPage.md', 'page without ext uses def ext');
// 大文字拡張子はページ名の一部 (agy レビュー指摘1)
$f = kona3getEditFile('Page.TXT', $ext);
test_eq(__LINE__, $f, $editable_data . '/Page.TXT.md', 'Page.TXT gets .md appended (uppercase is not direct)');
test_eq(__LINE__, $ext, 'md', 'Page.TXT ext is md');
// ドットで始まるページ名にも付与され、生ファイルにはならない (agy レビュー指摘5)
$f = kona3getEditFile('.htaccess', $ext);
test_eq(__LINE__, $f, $editable_data . '/.htaccess.md', '.htaccess gets .md appended');

// --- 5. kona3getEditFile(): 既存ファイルが優先される ---
$editable_cleanup(['Existing.php', 'Existing2.php']);
file_put_contents($editable_data . '/Existing.php.txt', 'existing txt');
kona3setConf('def_text_ext', 'md');
$f = kona3getEditFile('Existing.php', $ext);
test_eq(__LINE__, $f, $editable_data . '/Existing.php.txt', 'existing .txt file wins over def md');
test_eq(__LINE__, $ext, 'txt', 'existing .txt ext is txt');
file_put_contents($editable_data . '/Existing2.php.md', 'existing md');
kona3setConf('def_text_ext', 'txt');
$f = kona3getEditFile('Existing2.php', $ext);
test_eq(__LINE__, $f, $editable_data . '/Existing2.php.md', 'existing .md file wins over def txt');
test_eq(__LINE__, $ext, 'md', 'existing .md ext is md');
$editable_cleanup(['Existing.php', 'Existing2.php']);
kona3setConf('def_text_ext', 'md');

// --- 6. メタファイル名: .txt/.md だけを取り除く (衝突解消) ---
test_eq(__LINE__, basename(kona3db_getPageMetaFile('Page.txt')), 'Page.json', 'meta of Page.txt is Page.json');
test_eq(__LINE__, basename(kona3db_getPageMetaFile('Page.md')), 'Page.json', 'meta of Page.md is Page.json');
test_eq(__LINE__, basename(kona3db_getPageMetaFile('xxxx.php')), 'xxxx.php.json', 'meta of xxxx.php keeps .php');
test_assert(__LINE__, kona3db_getPageMetaFile('xxxx.php') !== kona3db_getPageMetaFile('xxxx'), 'meta of xxxx.php does not collide with xxxx');

// --- 7. ソース上のガード: エラーで弾く旧仕様が残っていないこと ---
$edit_src = file_get_contents(dirname(__DIR__) . '/action/edit.inc.php');
test_assert(__LINE__, strpos($edit_src, 'Invalid file extension') === false, 'edit action no longer rejects non-listed extensions');
test_assert(__LINE__, strpos($edit_src, 'kona3isEditableExt') !== false, 'edit action uses the editable ext list');
$lib_src = file_get_contents(dirname(__DIR__) . '/kona3lib.inc.php');
test_assert(__LINE__, strpos($lib_src, 'kona3isEditableExt($test_ext)') !== false, 'git hook uses the editable ext list');

// --- 7b. kona3edit_normalizeEditExt() ---
kona3setConf('def_text_ext', 'md');
test_eq(__LINE__, kona3edit_normalizeEditExt('Page.txt', 'md'), 'txt', 'normalize: explicit .txt in page name wins');
test_eq(__LINE__, kona3edit_normalizeEditExt('Page.md', 'txt'), 'md', 'normalize: explicit .md in page name wins');
test_eq(__LINE__, kona3edit_normalizeEditExt('PlainPage', 'txt'), 'txt', 'normalize: valid edit_ext is kept');
test_eq(__LINE__, kona3edit_normalizeEditExt('PlainPage', 'php'), 'md', 'normalize: tampered edit_ext falls back to resolved ext');
test_eq(__LINE__, kona3edit_normalizeEditExt('PlainPage', ''), 'md', 'normalize: empty edit_ext falls back to resolved ext');
test_eq(__LINE__, kona3edit_normalizeEditExt('xxxx.php', 'php'), 'md', 'normalize: tampered edit_ext on dotted page falls back to resolved ext');

// --- 8. kona3_trywrite(): xxxx.php ページを実際に保存できる ---
$editable_cleanup(['EditExtPhp.php']);
$page = 'EditExtPhp.php';
$GLOBALS['page'] = $page;
kona3setConf('def_text_ext', 'md');
$_REQUEST['edit_ext'] = 'md';
$_REQUEST['page_mode'] = 'Markdown';
$_REQUEST['edit_txt'] = 'Hello PHP-named page';
$_REQUEST['tags'] = '';
$_REQUEST['a_mode'] = 'trywrite';
$_REQUEST['postId'] = 0;
$_REQUEST['a_hash'] = kona3getPageHash('');
$txt = '';
$a_hash = kona3getPageHash('');
$result = FALSE;
kona3_trywrite($txt, $a_hash, 'form', $result);
test_eq(__LINE__, $result, TRUE, 'trywrite of .php page should succeed');
test_assert(__LINE__, is_file($editable_data . '/EditExtPhp.php.md'), 'saved as EditExtPhp.php.md');
test_eq(__LINE__, file_get_contents($editable_data . '/EditExtPhp.php.md'), 'Hello PHP-named page', 'content saved in the .md file');
test_assert(__LINE__, !file_exists($editable_data . '/EditExtPhp.php'), 'raw .php file is not created');
test_assert(__LINE__, !file_exists($editable_data . '/EditExtPhp.php.txt'), '.txt file is not created when mode is Markdown');
$meta_file = kona3db_getPageMetaFile('EditExtPhp.php');
test_assert(__LINE__, is_file($meta_file), 'meta file is created for .php page');
test_eq(__LINE__, basename($meta_file), 'EditExtPhp.php.json', 'meta file name keeps .php');

// 再編集 (既存ファイルの更新)
$_REQUEST['edit_txt'] = 'Updated PHP-named page';
$_REQUEST['a_hash'] = kona3getPageHash('Hello PHP-named page');
$txt = 'Hello PHP-named page';
$a_hash = kona3getPageHash('Hello PHP-named page');
$result = FALSE;
kona3_trywrite($txt, $a_hash, 'form', $result);
test_eq(__LINE__, $result, TRUE, 'trywrite update of .php page should succeed');
test_eq(__LINE__, file_get_contents($editable_data . '/EditExtPhp.php.md'), 'Updated PHP-named page', 'content updated in the same .md file');
$editable_cleanup(['EditExtPhp.php']);

// --- 9. kona3_trywrite(): test.html ページは .txt を付与して保存する ---
$editable_cleanup(['EditExtHtml.html']);
$page = 'EditExtHtml.html';
$GLOBALS['page'] = $page;
$_REQUEST['edit_ext'] = 'txt';
$_REQUEST['page_mode'] = 'KonaNotation';
$_REQUEST['edit_txt'] = 'Hello HTML-named page';
$_REQUEST['a_hash'] = kona3getPageHash('');
$txt = '';
$a_hash = kona3getPageHash('');
$result = FALSE;
kona3_trywrite($txt, $a_hash, 'form', $result);
test_eq(__LINE__, $result, TRUE, 'trywrite of .html page should succeed');
test_assert(__LINE__, is_file($editable_data . '/EditExtHtml.html.txt'), 'saved as EditExtHtml.html.txt');
test_assert(__LINE__, !file_exists($editable_data . '/EditExtHtml.html'), 'raw .html file is not created');
$editable_cleanup(['EditExtHtml.html']);

// --- 10. kona3_trywrite(): 大文字拡張子はページ名の一部として保存 (agy 指摘1) ---
$editable_cleanup(['EditExtUpper.TXT']);
@unlink($editable_data . '/EditExtUpper.TXT.txt');
$page = 'EditExtUpper.TXT';
$GLOBALS['page'] = $page;
kona3setConf('def_text_ext', 'md');
$_REQUEST['edit_ext'] = 'md';
$_REQUEST['page_mode'] = 'Markdown';
$_REQUEST['edit_txt'] = 'Hello Upper page';
$_REQUEST['a_hash'] = kona3getPageHash('');
$txt = '';
$a_hash = kona3getPageHash('');
$result = FALSE;
kona3_trywrite($txt, $a_hash, 'form', $result);
test_eq(__LINE__, $result, TRUE, 'trywrite of .TXT page should succeed');
test_assert(__LINE__, is_file($editable_data . '/EditExtUpper.TXT.md'), 'saved as EditExtUpper.TXT.md');
test_assert(__LINE__, !file_exists($editable_data . '/EditExtUpper.TXT'), 'raw .TXT file is not created');
test_assert(__LINE__, !file_exists($editable_data . '/EditExtUpper.TXT.txt'), 'EditExtUpper.TXT.txt is not created');
$editable_cleanup(['EditExtUpper.TXT']);
@unlink($editable_data . '/EditExtUpper.TXT.txt');

// --- 11. kona3_trywrite(): xxxx.php の新規作成で記法を切り替える (agy 指摘5) ---
$editable_cleanup(['EditExtSwitch.php']);
$page = 'EditExtSwitch.php';
$GLOBALS['page'] = $page;
kona3setConf('def_text_ext', 'md');
$_REQUEST['edit_ext'] = 'md';
$_REQUEST['page_mode'] = 'KonaNotation';
$_REQUEST['edit_txt'] = 'Hello Switch page';
$_REQUEST['a_hash'] = kona3getPageHash('');
$txt = '';
$a_hash = kona3getPageHash('');
$result = FALSE;
kona3_trywrite($txt, $a_hash, 'form', $result);
test_eq(__LINE__, $result, TRUE, 'trywrite mode switch of .php page should succeed');
test_assert(__LINE__, is_file($editable_data . '/EditExtSwitch.php.txt'), 'mode switch saves as .php.txt');
test_assert(__LINE__, !file_exists($editable_data . '/EditExtSwitch.php.md'), '.php.md is not created on switch to KonaNotation');
$editable_cleanup(['EditExtSwitch.php']);

// --- 12. kona3_trywrite(): edit_ext 改ざん・欠落は実際の拡張子に正規化 (agy 指摘2) ---
$editable_cleanup(['EditExtTamper']);
@unlink($editable_data . '/EditExtTamper.php.md');
$page = 'EditExtTamper';
$GLOBALS['page'] = $page;
kona3setConf('def_text_ext', 'md');
$_REQUEST['edit_ext'] = 'php';
$_REQUEST['page_mode'] = '';
$_REQUEST['edit_txt'] = 'Hello Tamper page';
$_REQUEST['a_hash'] = kona3getPageHash('');
$txt = '';
$a_hash = kona3getPageHash('');
$result = FALSE;
kona3_trywrite($txt, $a_hash, 'form', $result);
test_eq(__LINE__, $result, TRUE, 'trywrite with tampered edit_ext should succeed');
test_assert(__LINE__, is_file($editable_data . '/EditExtTamper.md'), 'tampered edit_ext=php is normalized and saved as .md');
test_assert(__LINE__, !file_exists($editable_data . '/EditExtTamper.php.md'), 'EditExtTamper.php.md is not created');
test_assert(__LINE__, !file_exists($editable_data . '/EditExtTamper.php'), 'raw .php file is not created');
$editable_cleanup(['EditExtTamper']);
@unlink($editable_data . '/EditExtTamper.php.md');
// 欠落 (空文字) でも同様に解決される
$_REQUEST['edit_ext'] = '';
$_REQUEST['edit_txt'] = 'Hello NoExt page';
$_REQUEST['a_hash'] = kona3getPageHash('');
$txt = '';
$a_hash = kona3getPageHash('');
$result = FALSE;
kona3_trywrite($txt, $a_hash, 'form', $result);
test_eq(__LINE__, $result, TRUE, 'trywrite with empty edit_ext should succeed');
test_assert(__LINE__, is_file($editable_data . '/EditExtTamper.md'), 'empty edit_ext is normalized and saved as .md');
$editable_cleanup(['EditExtTamper']);

// --- 13. kona3_trywrite(): ページ名の .txt は明示として固定される (agy 指摘3) ---
$editable_cleanup(['EditExtMemo.txt', 'EditExtMemo']);
@unlink($editable_data . '/EditExtMemo.md');
$page = 'EditExtMemo.txt';
$GLOBALS['page'] = $page;
$_REQUEST['edit_ext'] = 'txt';
$_REQUEST['page_mode'] = 'Markdown';
$_REQUEST['edit_txt'] = 'Hello Memo page';
$_REQUEST['a_hash'] = kona3getPageHash('');
$txt = '';
$a_hash = kona3getPageHash('');
$result = FALSE;
kona3_trywrite($txt, $a_hash, 'form', $result);
test_eq(__LINE__, $result, TRUE, 'trywrite of explicit .txt page should succeed');
test_assert(__LINE__, is_file($editable_data . '/EditExtMemo.txt'), 'explicit .txt page is saved as .txt even in Markdown mode');
test_assert(__LINE__, !file_exists($editable_data . '/EditExtMemo.md'), 'explicit .txt page is not converted to .md');
// 既存の .txt ページを Markdown モードで更新しても拡張子は変わらない
$_REQUEST['edit_txt'] = 'Updated Memo page';
$_REQUEST['a_hash'] = kona3getPageHash('Hello Memo page');
$txt = 'Hello Memo page';
$a_hash = kona3getPageHash('Hello Memo page');
$result = FALSE;
kona3_trywrite($txt, $a_hash, 'form', $result);
test_eq(__LINE__, $result, TRUE, 'trywrite update of explicit .txt page should succeed');
test_eq(__LINE__, file_get_contents($editable_data . '/EditExtMemo.txt'), 'Updated Memo page', 'explicit .txt content is updated in place');
$editable_cleanup(['EditExtMemo.txt', 'EditExtMemo']);
@unlink($editable_data . '/EditExtMemo.md');

// 後始末
$page = 'FrontPage';
$GLOBALS['page'] = $page;
kona3setConf('def_text_ext', 'md');
echo "=== Editable Ext (#53) Test Completed ===\n";
