<?php
require_once __DIR__ . '/test_common.inc.php';

// Test hamburger menu improvements (#329, #330)
// #329: システムメニューとコンテンツメニューの間に区切り線を入れる (キャッシュクリアの下)
// #330: 文字サイズ/行間とキャッシュ設定をダイアログにまとめ、メニュー項目を減らす

$parts_header = dirname(__DIR__) . '/template/parts_header.html';
test_assert(__LINE__, file_exists($parts_header), "parts_header.html exists");
$header_content = file_get_contents($parts_header);

// --- メニュー本体 (最初の <ul> ... </ul>) を取り出す ---
$ul_start = strpos($header_content, '<ul>');
$ul_end = strpos($header_content, '</ul>');
test_assert(__LINE__, $ul_start !== false && $ul_end !== false, "parts_header.html contains hamburger menu <ul>");
$menu_html = substr($header_content, $ul_start, $ul_end - $ul_start);

// --- #330: メニュー項目はダイアログを開く1項目ずつに集約されている ---
test_assert(__LINE__, strpos($menu_html, 'id="text_style_open"') !== false, "menu contains text_style_open (#330)");
test_assert(__LINE__, strpos($menu_html, "lang('Font Size & Line Height')") !== false, "menu uses Font Size & Line Height lang label (#330)");
test_assert(__LINE__, strpos($menu_html, 'id="cache_settings_open"') !== false, "menu contains cache_settings_open (#330)");
test_assert(__LINE__, strpos($menu_html, "lang('Cache Settings')") !== false, "menu uses Cache Settings lang label (#330)");

// 旧来の調整項目がメニューの <li> としては残っていないこと (ダイアログ内へ移動)
foreach (['font_size_plus', 'font_size_minus', 'line_height_plus', 'line_height_minus', 'text_style_reset', 'offline_cache_toggle', 'offline_cache_clear'] as $moved_id) {
    test_assert(__LINE__, strpos($menu_html, 'id="' . $moved_id . '"') === false, "menu no longer lists {$moved_id} directly (#330)");
}

// --- #329: キャッシュ設定 (旧キャッシュクリア) の下に区切り線がある ---
$cache_pos = strpos($menu_html, 'id="cache_settings_open"');
test_assert(__LINE__, $cache_pos !== false, "menu contains cache_settings_open for separator check (#329)");
$sep_after_cache = ($cache_pos === false) ? false : strpos($menu_html, 'class="menu-separator"', $cache_pos);
test_assert(__LINE__, $sep_after_cache !== false, "menu-separator exists after Cache Settings (#329)");
// 区切り線はメニューの最後の要素である (この下がコンテンツメニュー GlobalBar)
test_assert(__LINE__, $sep_after_cache !== false && trim(substr($menu_html, $sep_after_cache + strlen('class="menu-separator" aria-hidden="true" role="separator"></li>'))) === '', "menu-separator after Cache Settings is the last menu item (#329)");

// --- #330: ダイアログ本体 ---
test_assert(__LINE__, strpos($header_content, '<dialog id="text_style_dialog"') !== false, "parts_header.html contains text_style_dialog (#330)");
test_assert(__LINE__, strpos($header_content, '<dialog id="cache_settings_dialog"') !== false, "parts_header.html contains cache_settings_dialog (#330)");
test_assert(__LINE__, strpos($header_content, 'class="kona3-settings-dialog"') !== false, "dialogs use kona3-settings-dialog class (#330)");

// ダイアログ内に旧来のコントロールが入っていること
foreach (['font_size_plus', 'font_size_minus', 'line_height_plus', 'line_height_minus', 'text_style_reset', 'text_style_close', 'font_size_value', 'line_height_value'] as $id) {
    test_assert(__LINE__, strpos($header_content, 'id="' . $id . '"') !== false, "text_style_dialog contains {$id} (#330)");
}
foreach (['offline_cache_toggle', 'offline_cache_clear', 'cache_settings_close'] as $id) {
    test_assert(__LINE__, strpos($header_content, 'id="' . $id . '"') !== false, "cache_settings_dialog contains {$id} (#330)");
}
// コントロールはボタン要素になっている (メニューのリンクではない)
test_assert(__LINE__, strpos($header_content, '<button type="button" id="font_size_plus"') !== false, "font_size_plus is a button in the dialog (#330)");
test_assert(__LINE__, strpos($header_content, '<button type="button" id="offline_cache_clear"') !== false, "offline_cache_clear is a button in the dialog (#330)");

// --- 言語ファイル ---
$en_lang_file = dirname(__DIR__) . '/lang/en.inc.php';
include $en_lang_file;
global $lang_data;
test_assert(__LINE__, isset($lang_data['Font Size & Line Height']), "en.inc.php has Font Size & Line Height");
test_assert(__LINE__, isset($lang_data['Font Size']), "en.inc.php has Font Size");
test_assert(__LINE__, isset($lang_data['Line Height']), "en.inc.php has Line Height");
test_assert(__LINE__, isset($lang_data['Cache Settings']), "en.inc.php has Cache Settings");

$ja_lang_file = dirname(__DIR__) . '/lang/ja.inc.php';
include $ja_lang_file;
test_assert(__LINE__, isset($lang_data['Font Size & Line Height']), "ja.inc.php has Font Size & Line Height");
test_assert(__LINE__, $lang_data['Font Size & Line Height'] === '🔤 文字サイズと行間', "ja.inc.php has correct translation for Font Size & Line Height");
test_assert(__LINE__, $lang_data['Cache Settings'] === '⚙ キャッシュの設定', "ja.inc.php has correct translation for Cache Settings");
test_assert(__LINE__, $lang_data['Font Size'] === '文字サイズ', "ja.inc.php has correct translation for Font Size");
test_assert(__LINE__, $lang_data['Line Height'] === '行間', "ja.inc.php has correct translation for Line Height");

// --- drawer.js: ダイアログ開閉と現在値表示 ---
$drawer_js = dirname(__DIR__) . '/resource/drawer.js';
test_assert(__LINE__, file_exists($drawer_js), "drawer.js exists");
$js_content = file_get_contents($drawer_js);
test_assert(__LINE__, strpos($js_content, 'showModal') !== false, "drawer.js opens dialogs with showModal (#330)");
test_assert(__LINE__, strpos($js_content, "'text_style_open'") !== false && strpos($js_content, "'text_style_dialog'") !== false, "drawer.js wires text_style_open to text_style_dialog (#330)");
test_assert(__LINE__, strpos($js_content, "'cache_settings_open'") !== false && strpos($js_content, "'cache_settings_dialog'") !== false, "drawer.js wires cache_settings_open to cache_settings_dialog (#330)");
test_assert(__LINE__, strpos($js_content, 'font_size_value') !== false, "drawer.js updates font_size_value display (#330)");
test_assert(__LINE__, strpos($js_content, 'line_height_value') !== false, "drawer.js updates line_height_value display (#330)");
// 既存の調整ハンドラが残っていること
test_assert(__LINE__, strpos($js_content, '#font_size_plus') !== false, "drawer.js still targets #font_size_plus");
test_assert(__LINE__, strpos($js_content, '#offline_cache_toggle') !== false, "drawer.js still targets #offline_cache_toggle");
test_assert(__LINE__, strpos($js_content, '#offline_cache_clear') !== false, "drawer.js still targets #offline_cache_clear");

// --- drawer.css: ダイアログのスタイル ---
$drawer_css = dirname(__DIR__) . '/resource/drawer.css';
test_assert(__LINE__, file_exists($drawer_css), "drawer.css exists");
$css_content = file_get_contents($drawer_css);
test_assert(__LINE__, strpos($css_content, '.kona3-settings-dialog') !== false, "drawer.css contains .kona3-settings-dialog styles (#330)");
test_assert(__LINE__, strpos($css_content, '.kona3-settings-dialog::backdrop') !== false, "drawer.css contains dialog backdrop styles (#330)");
test_assert(__LINE__, strpos($css_content, '.menu-separator') !== false, "drawer.css contains styling for menu-separator (#329)");

echo "hamburger_menu.test.php: ALL OK\n";
