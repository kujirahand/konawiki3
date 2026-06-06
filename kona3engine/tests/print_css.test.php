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
