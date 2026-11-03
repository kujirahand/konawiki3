<?php
require_once __DIR__ . '/test_common.inc.php';

// Test for the editor replace side pane (issue #381)
$root = dirname(__DIR__);
$core_path = $root . '/resource/edit_replace_core.js';
$edit_html = file_get_contents($root . '/template/edit.html');
$edit_js = file_get_contents($root . '/resource/edit.js');
$edit_css = file_get_contents($root . '/resource/edit.css');
$edit_action = file_get_contents($root . '/action/edit.inc.php');
$core = file_exists($core_path) ? file_get_contents($core_path) : '';

test_assert(__LINE__, file_exists($core_path), 'replace: core file exists (#381)');
foreach (['replaceFindMatches', 'replaceExpand', 'buildReplaceResults', 'replaceAllText', 'replaceNextText', 'replaceAtText'] as $fn) {
    test_assert(__LINE__, strpos($core, 'function ' . $fn . '(') !== false, "replace: core has {$fn}() (#381)");
}
test_assert(__LINE__, strpos($core, 'document.') === false, 'replace: core does not touch the DOM (#381)');
test_assert(__LINE__, strpos($core, 'module.exports') !== false, 'replace: core exports for Node tests (#381)');

// --- HTML: side pane in #edit_main, same slot as search / AI ---
$main_pos = strpos($edit_html, 'id="edit_main"');
$drawer_pos = strpos($edit_html, 'id="edit_drawer"');
$replace_pos = strpos($edit_html, 'id="replace_div"');
$search_pos = strpos($edit_html, 'id="search_div"');
test_assert(__LINE__, $replace_pos !== false && $replace_pos > $main_pos && $replace_pos < $drawer_pos, 'replace: panel is a side pane in #edit_main (#381)');
test_assert(__LINE__, $replace_pos < $search_pos, 'replace: pane comes before the search pane (#381)');
foreach (['id="replace_key"', 'id="replace_val"', 'id="replace_regexp"', 'id="replace1_btn"', 'id="replaceA_btn"', 'id="replace_undo_btn"', 'id="replace_results"', 'id="replace_results_note"', 'replacePaneClose()', 'onclick="replaceOnClick()"'] as $needle) {
    test_assert(__LINE__, strpos($edit_html, $needle) !== false, "replace: edit.html has {$needle} (#381)");
}
test_assert(__LINE__, substr_count($edit_html, 'id="replace_key"') === 1, 'replace: exactly one keyword field (#381)');

// --- JS ---
foreach (['replaceOnClick', 'replaceRun', 'replaceLiveLater', 'replaceLiveRun', 'replaceListClear', 'replaceRow', 'replace1', 'replaceAll', 'replaceUndo', 'replacePaneSet', 'replacePaneClose', 'initReplacePane', 'replaceCommit'] as $fn) {
    test_assert(__LINE__, strpos($edit_js, 'function ' . $fn . '(') !== false, "replace: edit.js has {$fn}() (#381)");
}
test_assert(__LINE__, strpos($edit_js, "'#replace_div', '#history_div'") === false && strpos($edit_js, "'#tags_div', '#history_div'") !== false, 'replace: no longer a drawer panel (#381)');
// 3つのペインは同じ位置を共有する
test_assert(__LINE__, strpos($edit_js, "const rp = qs('#replace_div')") !== false, 'replace: other panes hide the replace pane (#381)');
test_assert(__LINE__, strpos($edit_js, 'replacePaneIsOpen()') !== false, 'replace: the editor makes room for the pane (#381)');
// リアルタイム: 遅延・IME・2文字以上・30件
test_assert(__LINE__, strpos($edit_js, 'REPLACE_LIVE_DELAY = 300') !== false && strpos($edit_js, 'setTimeout(replaceLiveRun, REPLACE_LIVE_DELAY)') !== false, 'replace: live preview is debounced (#381)');
test_assert(__LINE__, strpos($edit_js, 'REPLACE_LIVE_MIN_LEN = 2') !== false, 'replace: live preview needs 2+ characters (#381)');
test_assert(__LINE__, strpos($edit_js, 'REPLACE_LIVE_LIMIT = 30') !== false && strpos($edit_js, 'stopAtLimit: replaceListLive') !== false, 'replace: live preview stops after 30 matches (#381)');
test_assert(__LINE__, strpos($edit_js, "e.inputType === 'insertCompositionText'") !== false && strpos($edit_js, 'replaceComposing') !== false, 'replace: IME composition is respected (#381)');
test_assert(__LINE__, strpos($edit_js, "addEventListener('blur', () => { replaceComposing = false })") !== false, 'replace: a cancelled composition does not block the preview (#381)');
// 古い結果では置換しない
test_assert(__LINE__, preg_match('/function replaceResultsClick\(.*?replaceListIsCurrent\(\).*?replaceRun\(replaceListLive\)/s', $edit_js) === 1, 'replace: a row click re-checks the inputs/text first (#381)');
test_assert(__LINE__, strpos($edit_js, 'replaceAtText(edit_txt.value') !== false, 'replace: a row replaces only its own match (#381)');
// 元に戻す
test_assert(__LINE__, strpos($edit_js, 'replaceUndoBuffer = edit_txt.value') !== false, 'replace: undo buffer is kept before each replacement (#381)');
// 長さゼロの一致 (^ $) の表示 (#382 review)
test_assert(__LINE__, strpos($edit_js, "del.className = 'replace_zero'") !== false && strpos($edit_css, 'del.replace_zero::before') !== false, 'replace: a zero-length match is marked in the table (#382 review)');
test_assert(__LINE__, strpos($core, 'A zero-length match') !== false && strpos($core, 'if (m[0].length === 0) {\n        // an empty match') === false, 'replace: zero-length matches are not skipped (#382 review)');
test_assert(__LINE__, strpos($core, "m.input.substring(0, m.index)") !== false, 'replace: $` and $\' are expanded like String.replace (#382 review)');
// 結果の表示は textContent で組み立てる (XSS対策)
$run_src = substr($edit_js, strpos($edit_js, 'function replaceRun('), 3200);
test_assert(__LINE__, strpos($run_src, 'innerHTML') === false, 'replace: the table is built without innerHTML (#381)');
// Enter で誤って置換しない
test_assert(__LINE__, strpos($edit_js, 'replace1() }') === false, 'replace: Enter in the fields does not replace (#381)');
// script registration
$p_core = strpos($edit_action, 'edit_replace_core.js');
$p_edit = strpos($edit_action, "'edit.js'");
test_assert(__LINE__, $p_core !== false && $p_edit !== false && $p_core < $p_edit, 'replace: core is registered before edit.js (#381)');

// --- CSS ---
foreach (['#replace_results del', '#replace_results ins', '#replace_results.search_stale', '.replace_regexp_label'] as $needle) {
    test_assert(__LINE__, strpos($edit_css, $needle) !== false, "replace: edit.css styles {$needle} (#381)");
}

// --- node tests / syntax ---
$node = trim((string) @shell_exec('command -v node 2>/dev/null'));
if ($node !== '') {
    $out = [];
    $code = 0;
    exec(escapeshellarg($node) . ' --check ' . escapeshellarg($core_path) . ' 2>&1', $out, $code);
    test_assert(__LINE__, $code === 0, 'replace: core is valid JS (#381)');
    $out = [];
    exec(escapeshellarg($node) . ' ' . escapeshellarg($root . '/tests/js/edit_replace_core.test.js') . ' 2>&1', $out, $code);
    test_assert(__LINE__, $code === 0, 'replace: node behaviour tests pass (#381): ' . implode(' ', array_slice($out, -2)));
} else {
    echo "# replace: node not found, JS behaviour tests skipped\n";
}
