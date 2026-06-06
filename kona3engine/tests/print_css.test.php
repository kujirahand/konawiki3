<?php
require_once __DIR__ . '/test_common.inc.php';

// Test that print media rules are present in drawer.css to hide the hamburger icon during printing.
$drawer_css_path = dirname(__DIR__) . '/resource/drawer.css';

test_assert(__LINE__, file_exists($drawer_css_path), "drawer.css exists");

$content = file_get_contents($drawer_css_path);

test_assert(__LINE__, strpos($content, '@media print') !== false, "drawer.css contains @media print");
test_assert(__LINE__, strpos($content, '#hamburger_icon') !== false, "drawer.css contains #hamburger_icon");
test_assert(__LINE__, strpos($content, 'display: none') !== false, "drawer.css contains display: none");

// Check the exact print style definition
$expected_pattern = '/@media\s+print\s*\{\s*#drawer_wrapper,\s*#hamburger_icon,\s*#drawer_background\s*\{\s*display:\s*none\s*!important;\s*\}\s*\}/s';
test_assert(__LINE__, preg_match($expected_pattern, $content) === 1, "drawer.css has correct print styles for hamburger menu");

// Test that print media rules are present in kona3def.css to convert highlights to underline.
$kona3def_css_path = dirname(__DIR__) . '/resource/kona3def.css';
test_assert(__LINE__, file_exists($kona3def_css_path), "kona3def.css exists");
$kona3def_content = file_get_contents($kona3def_css_path);

test_assert(__LINE__, strpos($kona3def_content, '@media print') !== false, "kona3def.css contains @media print");
test_assert(__LINE__, strpos($kona3def_content, 'strong.strong1') !== false, "kona3def.css contains strong.strong1");
test_assert(__LINE__, strpos($kona3def_content, 'strong.strong2') !== false, "kona3def.css contains strong.strong2");
test_assert(__LINE__, strpos($kona3def_content, 'text-decoration: underline') !== false, "kona3def.css contains text-decoration: underline");
test_assert(__LINE__, strpos($kona3def_content, '.resmark') !== false, "kona3def.css contains .resmark inside print styles");
test_assert(__LINE__, strpos($kona3def_content, 'color: #111') !== false, "kona3def.css contains color: #111 for printing");
test_assert(__LINE__, strpos($kona3def_content, '.error_box') !== false, "kona3def.css contains .error_box");
test_assert(__LINE__, strpos($kona3def_content, 'background-color: #fde8e8') !== false, "kona3def.css contains dark red background for error_box");



