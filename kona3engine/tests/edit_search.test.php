<?php
require_once __DIR__ . '/test_common.inc.php';

// Test for the in-editor text search (issue #153, phase 3)
// - 検索パネルはドロワー内にあり、ナビと Ctrl+F から開く
// - 前へ/次へで一致箇所へ移動し、件数 (現在位置/総数) を表示する

$root = dirname(__DIR__);
$edit_html = file_get_contents($root . '/template/edit.html');
$edit_js = file_get_contents($root . '/resource/edit.js');
$edit_css = file_get_contents($root . '/resource/edit.css');

// --- HTML ---
test_assert(__LINE__, strpos($edit_html, 'id="search_div"') !== false, 'search: edit.html has #search_div (#153)');
test_assert(__LINE__, strpos($edit_html, 'id="search_key"') !== false, 'search: edit.html has #search_key (#153)');
test_assert(__LINE__, strpos($edit_html, 'id="search_count"') !== false, 'search: edit.html has #search_count (#153)');
test_assert(__LINE__, strpos($edit_html, 'onclick="searchOnClick()"') !== false, 'search: drawer nav opens the search panel (#153)');
test_assert(__LINE__, strpos($edit_html, 'onclick="searchNext()"') !== false, 'search: next button is wired (#153)');
test_assert(__LINE__, strpos($edit_html, 'onclick="searchPrev()"') !== false, 'search: prev button is wired (#153)');
// 検索パネルは AI支援と同じ場所 (#edit_main 内のサイドペイン) に表示する (#381)
$main_pos = strpos($edit_html, 'id="edit_main"');
$search_pos = strpos($edit_html, 'id="search_div"');
$ai_pos = strpos($edit_html, 'id="ai_div"');
test_assert(__LINE__, $search_pos !== false && $search_pos > $main_pos && $search_pos < $ai_pos, 'search: panel is a side pane in #edit_main, before the AI pane (#381)');

// --- JS ---
foreach (['updateSearchCount', 'searchMove', 'searchNext', 'searchPrev', 'scrollEditorToPos', 'searchOnClick', 'searchRun', 'searchJumpRow', 'searchPaneSet', 'searchPaneClose', 'sidePaneSync'] as $fn) {
    test_assert(__LINE__, strpos($edit_js, 'function ' . $fn . '(') !== false, "search: edit.js has {$fn}() (#153/#381)");
}
$search_core = file_get_contents($root . '/resource/edit_search_core.js');
foreach (['computeSearchMatches', 'findSearchTarget', 'buildSearchResults'] as $fn) {
    test_assert(__LINE__, strpos($search_core, 'function ' . $fn . '(') !== false, "search: edit_search_core.js has {$fn}() (#381)");
}
test_assert(__LINE__, strpos($search_core, 'document.') === false, 'search: core does not touch the DOM (#381)');
test_assert(__LINE__, strpos($edit_js, "'#search_div'") !== false, 'search: panel list includes #search_div (#153)');
// Ctrl+F はブラウザ標準の検索に戻した (#381)
test_assert(__LINE__, strpos($edit_js, "e.key !== 'f' && e.key !== 'F'") === false, 'search: Ctrl+F is not intercepted (#381)');
test_assert(__LINE__, strpos($edit_js, 'searchOnClick(true)') === false, 'search: no forced-open shortcut path (#381)');
test_assert(__LINE__, strpos($edit_html, 'id="search_btn"') !== false, 'search: edit.html has a search button (#381)');
test_assert(__LINE__, strpos($edit_html, 'id="search_results"') !== false, 'search: edit.html has the result table (#381)');
test_assert(__LINE__, strpos($edit_js, "'tr[data-pos]'") !== false, 'search: clicking a row jumps to the match (#381)');
// 古い結果で移動しない (PR #382 review)
test_assert(__LINE__, strpos($edit_js, 'function searchMarkStale(') !== false, 'search: stale results are marked (#382 review)');
test_assert(__LINE__, preg_match('/function searchJumpRow\\(.*?searchListIsCurrent\\(\\).*?searchRun\\(\\)/s', $edit_js) === 1, 'search: a row click re-checks the text/keyword before jumping (#382 review)');
test_assert(__LINE__, strpos($edit_js, 'search_stale') !== false && strpos($edit_css, '#search_results.search_stale') !== false, 'search: stale table is dimmed (#382 review)');
// 検索ペインとAI支援ペインは同じ位置を共有する
test_assert(__LINE__, strpos($edit_js, "const sp = qs('#search_div')") !== false, 'search: opening the AI pane hides the search pane (#381)');
$edit_action = file_get_contents($root . '/action/edit.inc.php');
test_assert(__LINE__, strpos($edit_action, 'edit_search_core.js') !== false, 'search: core script is registered (#381)');
test_assert(__LINE__, strpos($edit_js, "addEventListener('input', updateSearchCount)") !== false, 'search: count updates while typing the query (#153)');

// --- CSS ---
test_assert(__LINE__, strpos($edit_css, '#search_count') !== false, 'search: edit.css styles #search_count (#153)');

// --- レビュー第1ラウンドのフォローアップ (Copilot/agy, PR #345) ---
// Enter 連打で本文に改行が入らないよう、フォーカスは検索入力欄に残す
test_assert(__LINE__, strpos($edit_js, 'function searchSyncState(') !== false, 'search: state sync resets the position on text/query change (#345 review)');
test_assert(__LINE__, strpos($edit_js, 'e.keyCode === 229') !== false, 'search: IME composition Enter is ignored (#345 review)');
test_assert(__LINE__, strpos($search_core, 'hay.length !== text.length') !== false, 'search: lowercase length guard exists (#345 review)');
// i18n / a11y
test_assert(__LINE__, strpos($edit_html, "'Prev'|lang") !== false, 'search: prev button uses lang (#345 review)');
test_assert(__LINE__, strpos($edit_html, "'Next'|lang") !== false, 'search: next button uses lang (#345 review)');
test_assert(__LINE__, strpos($edit_html, 'aria-live="polite"') !== false, 'search: count is aria-live (#345 review)');
$lang_ja = file_get_contents($root . '/lang/ja.inc.php');
$lang_en = file_get_contents($root . '/lang/en.inc.php');
test_assert(__LINE__, strpos($lang_ja, "'Prev' => '前へ'") !== false, 'search: ja lang has Prev (#345 review)');
test_assert(__LINE__, strpos($lang_en, "'Next' => 'Next'") !== false, 'search: en lang has Next (#345 review)');

// --- #381: node tests for the DOM-free core ---
$node = trim((string) @shell_exec('command -v node 2>/dev/null'));
if ($node !== '') {
    $out = [];
    $code = 0;
    exec(escapeshellarg($node) . ' ' . escapeshellarg($root . '/tests/js/edit_search_core.test.js') . ' 2>&1', $out, $code);
    test_assert(__LINE__, $code === 0, 'search: node behaviour tests pass (#381): ' . implode(' ', array_slice($out, -2)));
} else {
    echo "# search: node not found, JS behaviour tests skipped\n";
}
