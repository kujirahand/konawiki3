<?php
require_once __DIR__ . '/test_common.inc.php';

// Test for the renewed edit screen layout (issue #153, phase 1: Zen Drawer)
// - 編集専用トップバー (戻る/ページ名/保存状態/保存/ツール)
// - ツールパネル群は右ドロワー (#edit_drawer) へ集約し、同時に1つだけ開く
// - 編集画面では共通ヘッダー/フッターを出さない (edit_screen フラグ)
// - アウトライン編集の死にコードを削除 (text2html はAI機能で使用中のため残す)

$root = dirname(__DIR__);
$edit_html = file_get_contents($root . '/template/edit.html');
$edit_js = file_get_contents($root . '/resource/edit.js');
$edit_css = file_get_contents($root . '/resource/edit.css');
$header = file_get_contents($root . '/template/parts_header.html');
$footer = file_get_contents($root . '/template/parts_footer.html');
$lib = file_get_contents($root . '/kona3lib.inc.php');
$edit_php = file_get_contents($root . '/action/edit.inc.php');

// --- トップバー ---
test_assert(__LINE__, strpos($edit_html, 'id="edit_topbar"') !== false, 'edit.html has #edit_topbar (#153)');
test_assert(__LINE__, strpos($edit_html, 'id="edit_page_name"') !== false, 'edit.html shows the page name (#153)');
test_assert(__LINE__, strpos($edit_html, '{{$page_url}}') !== false, 'edit.html back link uses $page_url (#153)');
test_assert(__LINE__, strpos($edit_html, 'id="edit_save_state"') !== false, 'edit.html has #edit_save_state (#153)');
test_assert(__LINE__, strpos($edit_html, 'id="tools_btn"') !== false, 'edit.html has #tools_btn (#153)');
test_assert(__LINE__, strpos($edit_html, 'onclick="toggleEditDrawer()"') !== false, 'tools button toggles the drawer (#153)');

// 保存ボタンの id は従来のまま (edit.js が参照する)
foreach (['temporarily_save_btn', 'save_btn', 'git_save_btn'] as $id) {
    test_assert(__LINE__, strpos($edit_html, 'id="' . $id . '"') !== false, "edit.html keeps #{$id} (#153)");
}

// --- ドロワー ---
test_assert(__LINE__, strpos($edit_html, 'id="edit_drawer"') !== false, 'edit.html has #edit_drawer (#153)');
test_assert(__LINE__, strpos($edit_html, 'id="edit_drawer_scrim"') !== false, 'edit.html has #edit_drawer_scrim (#153)');
test_assert(__LINE__, strpos($edit_html, 'id="edit_statusbar"') !== false, 'edit.html has #edit_statusbar (#153)');
$drawer_pos = strpos($edit_html, 'id="edit_drawer"');
foreach (['help_div', 'tags_div', 'replace_div', 'history_div', 'misc_div', 'ai_div'] as $panel) {
    $pos = strpos($edit_html, 'id="' . $panel . '"');
    test_assert(__LINE__, $pos !== false && $pos > $drawer_pos, "panel #{$panel} is inside the drawer (#153)");
}
test_assert(__LINE__, strpos($edit_html, 'id="outline_div"') === false, 'edit.html no longer has #outline_div (#153)');

// --- edit_screen フラグ ---
test_assert(__LINE__, strpos($header, '{{ if !$edit_screen }}') !== false, 'parts_header.html hides site chrome on the edit screen (#153)');
test_assert(__LINE__, strpos($footer, '{{ if !$edit_screen }}') !== false, 'parts_footer.html hides site chrome on the edit screen (#153)');
test_assert(__LINE__, strpos($lib, "\$kona3conf['edit_screen']") !== false, 'kona3lib.inc.php always defines edit_screen (#153)');
test_assert(__LINE__, strpos($edit_php, "\$kona3conf['edit_screen'] = true") !== false, 'edit action enables edit_screen (#153)');
test_assert(__LINE__, strpos($edit_php, '"page_url"') !== false, 'edit action passes page_url (#153)');

// --- edit.js ---
foreach (['toggleEditDrawer', 'openEditDrawer', 'closeEditDrawer', 'showOnlyPanel'] as $fn) {
    test_assert(__LINE__, strpos($edit_js, 'function ' . $fn . '(') !== false, "edit.js has {$fn}() (#153)");
}
foreach (['change_outline', 'outline_build', 'outline_to_text', 'html2text', 'outline_mode'] as $dead) {
    test_assert(__LINE__, strpos($edit_js, $dead) === false, "edit.js no longer contains {$dead} (#153)");
}
test_assert(__LINE__, strpos($edit_js, 'function text2html(') !== false, 'edit.js keeps text2html() used by AI features (#153)');
test_assert(__LINE__, strpos($edit_js, '未保存の変更があります') !== false, 'handleChange shows a worded save state (#153)');
test_assert(__LINE__, strpos($edit_js, "'[c]'") === false, "handleChange no longer uses '[c]' (#153)");

// --- edit.css ---
test_assert(__LINE__, strpos($edit_css, '#edit_drawer') !== false, 'edit.css styles #edit_drawer (#153)');
test_assert(__LINE__, strpos($edit_css, 'translateX(105%)') !== false, 'edit.css slides the drawer off-canvas (#153)');
test_assert(__LINE__, strpos($edit_css, 'calc(100vh - 240px)') !== false, 'edit.css gives the textarea a viewport-based height (#153)');
test_assert(__LINE__, strpos($edit_css, '60vh') === false, 'edit.css no longer fixes the textarea at 60vh (#153)');
test_assert(__LINE__, strpos($edit_css, 'max-width: 1120px') !== false, 'edit.css widens #wikiedit (#153)');
