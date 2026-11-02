<?php
require_once __DIR__ . '/test_common.inc.php';

// Test for the full-height editor and the Format/preview move into the tool drawer (issue #365)

$root = dirname(__DIR__);
$html = file_get_contents($root . '/template/edit.html');
$css = file_get_contents($root . '/resource/edit.css');

// --- form ---
test_assert(__LINE__, strpos($html, 'id="edit_form"') !== false, 'form has id="edit_form" (#365)');

// --- Format select sits under the drawer nav, always visible ---
$drawer_pos = strpos($html, 'id="edit_drawer"');
$settings_pos = strpos($html, 'id="settings_div"');
$mode_pos = strpos($html, 'id="page_mode"');
$nav_end = strpos($html, '</nav>');
$help_pos = strpos($html, 'id="help_div"');
test_assert(__LINE__, $drawer_pos !== false && $mode_pos > $drawer_pos, '#page_mode is inside the drawer (#365)');
test_assert(__LINE__, $nav_end !== false && $mode_pos > $nav_end && $help_pos !== false && $mode_pos < $help_pos, '#page_mode sits between the drawer nav and the panels, visible as soon as the drawer opens (#365 follow-up)');
test_assert(__LINE__, $settings_pos !== false && $mode_pos < $settings_pos, '#page_mode is no longer buried in #settings_div (#365 follow-up)');
test_assert(__LINE__, preg_match('/<select[^>]*id="page_mode"[^>]*form="edit_form"/', $html) === 1, '#page_mode has form="edit_form" (#365)');
test_assert(__LINE__, substr_count($html, 'id="page_mode"') === 1, 'only one #page_mode (#365)');

// --- statusbar ---
$sb_start = strpos($html, 'id="edit_statusbar"');
$sb_end = strpos($html, '</form>', $sb_start);
$sb = substr($html, $sb_start, $sb_end - $sb_start);
test_assert(__LINE__, strpos($sb, 'page_mode') === false, 'statusbar has no page_mode (#365)');
test_assert(__LINE__, strpos($sb, 'preview_btn') === false, 'statusbar has no preview_btn (#365)');
test_assert(__LINE__, strpos($sb, 'id="edit_counter"') !== false, 'statusbar keeps #edit_counter (#365)');
test_assert(__LINE__, strpos($sb, 'id="edit_info"') !== false, 'statusbar keeps #edit_info (#365)');

// --- preview button ---
test_assert(__LINE__, substr_count($html, 'id="preview_btn"') === 1, 'exactly one #preview_btn (#365)');
test_assert(__LINE__, strpos($html, 'id="preview_btn"') > $drawer_pos, '#preview_btn is inside the drawer (#365)');
test_assert(__LINE__, preg_match('/id="preview_btn"[^>]*onclick="togglePreview\(\)"/', $html) === 1, '#preview_btn calls togglePreview() (#365)');

// --- css ---
test_assert(__LINE__, strpos($css, '100dvh') !== false, 'edit.css uses 100dvh (#365)');
test_assert(__LINE__, preg_match('/textarea#edit_txt\s*\{[^}]*height:\s*100%/', $css) === 1, 'textarea height is 100% (#365)');
test_assert(__LINE__, strpos($css, 'calc(100vh - 240px)') === false, 'edit.css no longer uses calc(100vh - 240px) (#365)');
test_assert(__LINE__, strpos($css, '52vh') === false, 'edit.css no longer uses 52vh (#365)');
test_assert(__LINE__, preg_match('/\.k3hl-wrap\s*\{[^}]*height:\s*100%/', $css) === 1, '.k3hl-wrap has height: 100% (#365)');
