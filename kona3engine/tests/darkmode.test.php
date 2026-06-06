<?php
require_once __DIR__ . '/test_common.inc.php';

// Test dark mode implementation details

// 1. Check if darkmode.css exists and defines body.dark-theme overrides
$darkmode_css = dirname(__DIR__) . '/resource/darkmode.css';
test_assert(__LINE__, file_exists($darkmode_css), "darkmode.css exists");
$css_content = file_get_contents($darkmode_css);
test_assert(__LINE__, strpos($css_content, 'body.dark-theme') !== false, "darkmode.css defines body.dark-theme styles");
test_assert(__LINE__, strpos($css_content, 'background-color') !== false, "darkmode.css defines background-color styles");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme #wikiedit') !== false, "darkmode.css defines #wikiedit dark mode overrides");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme textarea#edit_txt') !== false, "darkmode.css defines textarea#edit_txt dark mode overrides");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme .kona3_login_bar') !== false, "darkmode.css defines .kona3_login_bar dark mode overrides");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme #wikimessage h2') !== false, "darkmode.css defines #wikimessage h2 dark mode overrides");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme #wikimessage h3') !== false, "darkmode.css defines #wikimessage h3 dark mode overrides");

// 2. Check if parts_header.html contains toggle button and inline JS to restore preference
$parts_header = dirname(__DIR__) . '/template/parts_header.html';
test_assert(__LINE__, file_exists($parts_header), "parts_header.html exists");
$header_content = file_get_contents($parts_header);
test_assert(__LINE__, strpos($header_content, 'id="dark_mode_toggle"') !== false, "parts_header.html contains dark_mode_toggle ID");
test_assert(__LINE__, strpos($header_content, 'localStorage.getItem(\'kona3_theme\')') !== false, "parts_header.html contains inline JS reading theme preference");

// 3. Check if drawer.js implements the toggle click handler
$drawer_js = dirname(__DIR__) . '/resource/drawer.js';
test_assert(__LINE__, file_exists($drawer_js), "drawer.js exists");
$js_content = file_get_contents($drawer_js);
test_assert(__LINE__, strpos($js_content, '#dark_mode_toggle') !== false, "drawer.js targets #dark_mode_toggle");
test_assert(__LINE__, strpos($js_content, 'localStorage.setItem(\'kona3_theme\'') !== false, "drawer.js saves theme preference");
