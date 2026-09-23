<?php
require_once __DIR__ . '/test_common.inc.php';

// 改行コード正規化のテスト (issue #251)
$page = 'TestPage_NewlineCode';
$data_dir = KONA3_DIR_DATA;
$path_txt = $data_dir . '/' . $page . '.txt';

if (!function_exists('cleanup_newline_code_test')) {
    function cleanup_newline_code_test() {
        global $path_txt;
        if (file_exists($path_txt)) @unlink($path_txt);
        $meta = kona3db_getPageMetaFile('TestPage_NewlineCode');
        if (file_exists($meta)) @unlink($meta);
    }
}

// Load edit action
require_once dirname(__DIR__) . '/action/edit.inc.php';

echo "=== Newline Code Normalization Test ===\n";

cleanup_newline_code_test();
$old_mode = kona3setConf('newline_code', 'LF');

// --- ユニットテスト: kona3_normalizeNewlineCode() ---

// LF 設定: CRLF/CR -> LF
kona3setConf('newline_code', 'LF');
test_eq(__LINE__,
    kona3_normalizeNewlineCode("a\r\nb\rc\n", $path_txt),
    "a\nb\nc\n", "LF mode converts CRLF/CR to LF");

// CRLF 設定: LF -> CRLF
kona3setConf('newline_code', 'CRLF');
test_eq(__LINE__,
    kona3_normalizeNewlineCode("a\nb\r\nc", $path_txt),
    "a\r\nb\r\nc", "CRLF mode converts LF to CRLF");

// original 設定: 既存ファイルがCRLFならCRLFを維持
kona3setConf('newline_code', 'original');
file_put_contents($path_txt, "old\r\ncontent\r\n");
test_eq(__LINE__,
    kona3_normalizeNewlineCode("new\ncontent\n", $path_txt),
    "new\r\ncontent\r\n", "original mode keeps existing CRLF file as CRLF");

// original 設定: 既存ファイルがLFならLFを維持
file_put_contents($path_txt, "old\ncontent\n");
test_eq(__LINE__,
    kona3_normalizeNewlineCode("new\r\ncontent\r\n", $path_txt),
    "new\ncontent\n", "original mode keeps existing LF file as LF");

// original 設定: 新規ファイルはLF
cleanup_newline_code_test();
test_eq(__LINE__,
    kona3_normalizeNewlineCode("new\r\ncontent\r\n", $path_txt),
    "new\ncontent\n", "original mode uses LF for new files");

// --- 統合テスト: kona3_trywrite() 経由の保存 ---

// textarea送信を想定したCRLFテキストを保存 (既存ファイルはLF)
function trywrite_page($content) {
    global $page, $path_txt;
    $_REQUEST['edit_ext'] = 'txt';
    $_REQUEST['page_mode'] = 'KonaNotation';
    $_REQUEST['edit_txt'] = $content;
    $_REQUEST['a_hash'] = kona3getPageHash(@file_get_contents($path_txt));
    $_REQUEST['a_mode'] = 'trywrite';
    $txt = @file_get_contents($path_txt);
    if ($txt === FALSE) $txt = "";
    $a_hash = kona3getPageHash($txt);
    $result = FALSE;
    kona3_trywrite($txt, $a_hash, 'ajax', $result);
    return $result;
}

// 1. 既定(original): LFファイル编辑 -> LFのまま (バグ修正の本命)
kona3setConf('newline_code', 'original');
cleanup_newline_code_test();
file_put_contents($path_txt, "line1\nline2\n");
$ok = trywrite_page("line1\nline2 edited\nline3\n");
test_eq(__LINE__, $ok, TRUE, "trywrite(original, LF file) should succeed");
test_eq(__LINE__,
    file_get_contents($path_txt),
    "line1\nline2 edited\nline3\n",
    "trywrite(original, LF file) should save with LF");

// 2. LF 設定: CRLF送信でもLFで保存
kona3setConf('newline_code', 'LF');
cleanup_newline_code_test();
file_put_contents($path_txt, "line1\r\nline2\r\n");
$ok = trywrite_page("line1\r\nline2 edited\r\nline3\r\n");
test_eq(__LINE__, $ok, TRUE, "trywrite(LF, CRLF file) should succeed");
test_eq(__LINE__,
    file_get_contents($path_txt),
    "line1\nline2 edited\nline3\n",
    "trywrite(LF) should unify saved content to LF");

// 3. CRLF 設定: LF送信でもCRLFで保存
kona3setConf('newline_code', 'CRLF');
cleanup_newline_code_test();
file_put_contents($path_txt, "line1\nline2\n");
$ok = trywrite_page("line1\nline2 edited\nline3\n");
test_eq(__LINE__, $ok, TRUE, "trywrite(CRLF) should succeed");
test_eq(__LINE__,
    file_get_contents($path_txt),
    "line1\r\nline2 edited\r\nline3\r\n",
    "trywrite(CRLF) should unify saved content to CRLF");

// 4. original 設定: CRLFファイルはCRLFを維持
kona3setConf('newline_code', 'original');
cleanup_newline_code_test();
file_put_contents($path_txt, "line1\r\nline2\r\n");
$ok = trywrite_page("line1\r\nline2 edited\r\nline3\r\n");
test_eq(__LINE__, $ok, TRUE, "trywrite(original, CRLF file) should succeed");
test_eq(__LINE__,
    file_get_contents($path_txt),
    "line1\r\nline2 edited\r\nline3\r\n",
    "trywrite(original, CRLF file) should keep CRLF");

// 後片付けと設定の復元
cleanup_newline_code_test();
kona3setConf('newline_code', $old_mode);
