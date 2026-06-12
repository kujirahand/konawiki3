<?php
require_once __DIR__ . '/test_common.inc.php';

// Test text size & line height implementation details

// 1. Check if parts_header.html contains size/line height buttons and inline JS to restore preference
$parts_header = dirname(__DIR__) . '/template/parts_header.html';
test_assert(__LINE__, file_exists($parts_header), "parts_header.html exists");
$header_content = file_get_contents($parts_header);

test_assert(__LINE__, strpos($header_content, 'id="font_size_plus"') !== false, "parts_header.html contains font_size_plus ID");
test_assert(__LINE__, strpos($header_content, 'id="font_size_minus"') !== false, "parts_header.html contains font_size_minus ID");
test_assert(__LINE__, strpos($header_content, 'id="text_style_reset"') !== false, "parts_header.html contains text_style_reset ID");

test_assert(__LINE__, strpos($header_content, 'id="line_height_plus"') !== false, "parts_header.html contains line_height_plus ID");
test_assert(__LINE__, strpos($header_content, 'id="line_height_minus"') !== false, "parts_header.html contains line_height_minus ID");

// Check if parts_header.html contains lang() calls for localization
test_assert(__LINE__, strpos($header_content, "lang('Increase Font Size')") !== false, "parts_header.html contains Increase Font Size lang call");
test_assert(__LINE__, strpos($header_content, "lang('Decrease Font Size')") !== false, "parts_header.html contains Decrease Font Size lang call");
test_assert(__LINE__, strpos($header_content, "lang('Increase Line Height')") !== false, "parts_header.html contains Increase Line Height lang call");
test_assert(__LINE__, strpos($header_content, "lang('Decrease Line Height')") !== false, "parts_header.html contains Decrease Line Height lang call");
test_assert(__LINE__, strpos($header_content, "lang('Reset Font & Line Height')") !== false, "parts_header.html contains Reset Font & Line Height lang call");

test_assert(__LINE__, strpos($header_content, 'class="menu-separator"') !== false, "parts_header.html contains menu-separator class");

test_assert(__LINE__, strpos($header_content, 'localStorage.getItem(\'kona3_font_size\')') !== false, "parts_header.html contains inline JS reading font size");
test_assert(__LINE__, strpos($header_content, 'localStorage.getItem(\'kona3_line_height\')') !== false, "parts_header.html contains inline JS reading line height");
test_assert(__LINE__, strpos($header_content, 'kona3-text-style-override') !== false, "parts_header.html contains inline JS applying text style override");


// 2. Check if lang files contain the localization keys
$en_lang_file = dirname(__DIR__) . '/lang/en.inc.php';
test_assert(__LINE__, file_exists($en_lang_file), "en.inc.php exists");
include $en_lang_file;
global $lang_data;
test_assert(__LINE__, isset($lang_data['Increase Font Size']), "en.inc.php has Increase Font Size");
test_assert(__LINE__, isset($lang_data['Decrease Font Size']), "en.inc.php has Decrease Font Size");
test_assert(__LINE__, isset($lang_data['Increase Line Height']), "en.inc.php has Increase Line Height");
test_assert(__LINE__, isset($lang_data['Decrease Line Height']), "en.inc.php has Decrease Line Height");
test_assert(__LINE__, isset($lang_data['Reset Font & Line Height']), "en.inc.php has Reset Font & Line Height");

$ja_lang_file = dirname(__DIR__) . '/lang/ja.inc.php';
test_assert(__LINE__, file_exists($ja_lang_file), "ja.inc.php exists");
include $ja_lang_file;
test_assert(__LINE__, isset($lang_data['Increase Font Size']), "ja.inc.php has Increase Font Size");
test_assert(__LINE__, $lang_data['Increase Font Size'] === '⊕ 文字を大きく', "ja.inc.php has correct translation for Increase Font Size");
test_assert(__LINE__, $lang_data['Reset Font & Line Height'] === '⊚ 文字と行間をリセット', "ja.inc.php has correct translation for Reset Font & Line Height");


// 3. Check if drawer.css contains styles for .menu-separator
$drawer_css = dirname(__DIR__) . '/resource/drawer.css';
test_assert(__LINE__, file_exists($drawer_css), "drawer.css exists");
$css_content = file_get_contents($drawer_css);
test_assert(__LINE__, strpos($css_content, '.menu-separator') !== false, "drawer.css contains styling for menu-separator");


// 4. Check if drawer.js implements the control handlers
$drawer_js = dirname(__DIR__) . '/resource/drawer.js';
test_assert(__LINE__, file_exists($drawer_js), "drawer.js exists");
$js_content = file_get_contents($drawer_js);

test_assert(__LINE__, strpos($js_content, '#font_size_plus') !== false, "drawer.js targets #font_size_plus");
test_assert(__LINE__, strpos($js_content, '#font_size_minus') !== false, "drawer.js targets #font_size_minus");
test_assert(__LINE__, strpos($js_content, '#text_style_reset') !== false, "drawer.js targets #text_style_reset");

test_assert(__LINE__, strpos($js_content, '#line_height_plus') !== false, "drawer.js targets #line_height_plus");
test_assert(__LINE__, strpos($js_content, '#line_height_minus') !== false, "drawer.js targets #line_height_minus");

test_assert(__LINE__, strpos($js_content, 'localStorage.setItem(\'kona3_font_size\'') !== false, "drawer.js saves font size preference");
test_assert(__LINE__, strpos($js_content, 'localStorage.setItem(\'kona3_line_height\'') !== false, "drawer.js saves line height preference");
test_assert(__LINE__, strpos($js_content, 'kona3-text-style-override') !== false, "drawer.js applies style override");

echo "text_size.test.php: ALL OK\n";
