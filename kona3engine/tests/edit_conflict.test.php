<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/action/edit.inc.php';

// Edit conflict handling (issue #153, phase 4): the ajax response must
// carry the server text and its hash, and the form flow must keep the
// user's text in the textarea instead of replacing it with diff text.

$root = dirname(__DIR__);

// --- kona3_make_diff (legacy merge format used by the form notice) ---
// '<<' marks lines only in the first text (the user's), '>>' lines
// only in the second text (the server's); identical lines pass through.
$diff = kona3_make_diff("same\nmine", "same");
test_assert(__LINE__, strpos($diff, 'same') !== false, 'conflict: identical line passes through in make_diff (#153)');
test_assert(__LINE__, strpos($diff, '<< mine') !== false, 'conflict: user-only line gets the << marker (#153)');
$diff = kona3_make_diff("same", "same\nserver");
test_assert(__LINE__, strpos($diff, '>> server') !== false, 'conflict: server-only line gets the >> marker (#153)');

// --- kona3_conflict: ajax returns a structured response ---
$server_txt = "server text\nsecond line";
ob_start();
kona3_conflict("my text", $server_txt, 'ajax');
$json = ob_get_clean();
$res = json_decode($json, true);
test_assert(__LINE__, is_array($res), 'conflict: ajax response is JSON (#153)');
test_assert(__LINE__, $res['result'] === 'ng', 'conflict: ajax result is ng (#153)');
test_assert(__LINE__, $res['code'] === 'conflict', 'conflict: ajax code is conflict (#153)');
test_assert(__LINE__, $res['server_text'] === $server_txt, 'conflict: ajax carries the server text (#153)');
test_assert(__LINE__, $res['server_hash'] === kona3getPageHash($server_txt), 'conflict: ajax carries the server hash (#153)');

// --- kona3_conflict: form keeps the user's text, diff goes aside ---
unset($GLOBALS['kona3_conflict_diff']);
$form_txt = "same\nserver";
$msg = kona3_conflict("same\nmine", $form_txt, 'form');
test_assert(__LINE__, strpos($msg, 'error') !== false, 'conflict: form returns an error message (#153)');
test_assert(__LINE__, $form_txt === "same\nmine", 'conflict: form keeps the user text for the textarea (#153)');
test_assert(__LINE__, isset($GLOBALS['kona3_conflict_diff']), 'conflict: form stores the diff separately (#153)');
test_assert(__LINE__, strpos($GLOBALS['kona3_conflict_diff'], 'server') !== false, 'conflict: form diff mentions the server lines (#153)');
unset($GLOBALS['kona3_conflict_diff']);

// --- wiring ---
$edit_js = file_get_contents($root . '/resource/edit.js');
$edit_html = file_get_contents($root . '/template/edit.html');
$edit_css = file_get_contents($root . '/resource/edit.css');
$edit_php = file_get_contents($root . '/action/edit.inc.php');
test_assert(__LINE__, strpos($edit_js, 'function handleEditConflict(') !== false, 'conflict: edit.js has handleEditConflict (#153)');
test_assert(__LINE__, strpos($edit_js, 'function computeLineDiff(') !== false, 'conflict: edit.js has computeLineDiff (#153)');
test_assert(__LINE__, strpos($edit_js, "code == 'conflict'") !== false, 'conflict: ajaxProc branches on the conflict code (#153)');
test_assert(__LINE__, strpos($edit_js, "'#conflict_div'") !== false, 'conflict: conflict panel is registered in showOnlyPanel (#153)');
test_assert(__LINE__, strpos($edit_js, 'clearConflictUI()') !== false, 'conflict: successful save clears the conflict UI (#153)');
test_assert(__LINE__, strpos($edit_js, 'timerAutoSaveOnTime') !== false && strpos($edit_js, 'handleEditConflict') !== false, 'conflict: autosave source is handled (#153)');
test_assert(__LINE__, strpos($edit_html, 'id="conflict_div"') !== false, 'conflict: edit.html has the conflict panel (#153)');
test_assert(__LINE__, strpos($edit_html, 'id="conflict_nav"') !== false, 'conflict: edit.html has the conflict nav button (#153)');
test_assert(__LINE__, strpos($edit_html, '$conflict_diff') !== false, 'conflict: edit.html renders the form diff block (#153)');
test_assert(__LINE__, strpos($edit_css, 'conflict_line_server') !== false, 'conflict: edit.css styles the diff lines (#153)');
test_assert(__LINE__, strpos($edit_php, '"conflict_diff"') !== false, 'conflict: action passes conflict_diff to the template (#153)');
$lang_ja = file_get_contents($root . '/lang/ja.inc.php');
$lang_en = file_get_contents($root . '/lang/en.inc.php');
test_assert(__LINE__, strpos($lang_ja, "'Edit conflict' => '編集の競合'") !== false, 'conflict: ja lang has Edit conflict (#153)');
test_assert(__LINE__, strpos($lang_en, "'Load server version' => 'Load server version'") !== false, 'conflict: en lang has Load server version (#153)');
