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
// パネルはドロワー内 (search_div が replace_div より前、ドロワー内に配置)
$drawer_pos = strpos($edit_html, 'id="edit_drawer"');
$search_pos = strpos($edit_html, 'id="search_div"');
test_assert(__LINE__, $search_pos !== false && $search_pos > $drawer_pos, 'search: panel is inside the drawer (#153)');

// --- JS ---
foreach (['computeSearchMatches', 'findSearchTarget', 'updateSearchCount', 'searchMove', 'searchNext', 'searchPrev', 'scrollEditorToPos', 'searchOnClick'] as $fn) {
    test_assert(__LINE__, strpos($edit_js, 'function ' . $fn . '(') !== false, "search: edit.js has {$fn}() (#153)");
}
test_assert(__LINE__, strpos($edit_js, "'#search_div'") !== false, 'search: panel list includes #search_div (#153)');
test_assert(__LINE__, strpos($edit_js, "openToolPanel('#search_div')") !== false, 'search: searchOnClick uses openToolPanel (#153)');
test_assert(__LINE__, strpos($edit_js, "e.key === 'f'") !== false, 'search: Ctrl+F shortcut is registered (#153)');
test_assert(__LINE__, strpos($edit_js, 'e.ctrlKey || e.metaKey') !== false, 'search: shortcut covers Cmd+F (#153)');
test_assert(__LINE__, strpos($edit_js, "addEventListener('input', updateSearchCount)") !== false, 'search: count updates while typing the query (#153)');

// --- CSS ---
test_assert(__LINE__, strpos($edit_css, '#search_count') !== false, 'search: edit.css styles #search_count (#153)');
