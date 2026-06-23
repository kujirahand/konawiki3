<?php
require_once __DIR__ . '/test_common.inc.php';

// Test offline cache implementation details

// 1. Check if parts_header.html contains offline cache buttons and inline JS for translation
$parts_header = dirname(__DIR__) . '/template/parts_header.html';
test_assert(__LINE__, file_exists($parts_header), "parts_header.html exists");
$header_content = file_get_contents($parts_header);

test_assert(__LINE__, strpos($header_content, 'id="offline_cache_toggle"') !== false, "parts_header.html contains offline_cache_toggle ID");
test_assert(__LINE__, strpos($header_content, 'id="offline_cache_clear"') !== false, "parts_header.html contains offline_cache_clear ID");
test_assert(__LINE__, strpos($header_content, 'window.KONA3_LANG =') !== false, "parts_header.html defines window.KONA3_LANG");

// 2. Check if lang files contain the localization keys
$en_lang_file = dirname(__DIR__) . '/lang/en.inc.php';
test_assert(__LINE__, file_exists($en_lang_file), "en.inc.php exists");
include $en_lang_file;
global $lang_data;
test_assert(__LINE__, isset($lang_data['Offline Cache']), "en.inc.php has Offline Cache key");
test_assert(__LINE__, isset($lang_data['Clear Cache']), "en.inc.php has Clear Cache key");
test_assert(__LINE__, isset($lang_data['Offline Cache Enabled']), "en.inc.php has Offline Cache Enabled key");
test_assert(__LINE__, isset($lang_data['Offline Cache Disabled']), "en.inc.php has Offline Cache Disabled key");
test_assert(__LINE__, isset($lang_data['Cache Cleared']), "en.inc.php has Cache Cleared key");
test_assert(__LINE__, isset($lang_data['Showing offline cache: saved at %s']), "en.inc.php has Showing offline cache key");

$ja_lang_file = dirname(__DIR__) . '/lang/ja.inc.php';
test_assert(__LINE__, file_exists($ja_lang_file), "ja.inc.php exists");
include $ja_lang_file;
test_assert(__LINE__, isset($lang_data['Offline Cache']), "ja.inc.php has Offline Cache key");
test_assert(__LINE__, $lang_data['Offline Cache'] === '📴 オフラインキャッシュ', "ja.inc.php has correct translation for Offline Cache");
test_assert(__LINE__, $lang_data['Showing offline cache: saved at %s'] === '（オフラインのためキャッシュを表示中: %s に保存）', "ja.inc.php has correct translation for Showing offline cache");

// 3. Check if sw.js exists and contains expected offline handling code
$sw_js = dirname(dirname(__DIR__)) . '/sw.js';
test_assert(__LINE__, file_exists($sw_js), "sw.js exists at repository root");
$sw_content = file_get_contents($sw_js);
test_assert(__LINE__, strpos($sw_content, 'kona3_offline_db') !== false, "sw.js contains offline DB name");
test_assert(__LINE__, strpos($sw_content, 'offline_cache') !== false, "sw.js contains check for offline_cache setting");
test_assert(__LINE__, strpos($sw_content, 'self.addEventListener(\'fetch\'') !== false, "sw.js registers fetch event listener");
test_assert(__LINE__, strpos($sw_content, 'OFFLINE_MSG') !== false, "sw.js contains OFFLINE_MSG translations");
test_assert(__LINE__, strpos($sw_content, 'shell_html') !== false, "sw.js queries shell_html setting");
test_assert(__LINE__, strpos($sw_content, '<!-- KONA3_CONTENT -->') !== false, "sw.js uses content placeholder");
test_assert(__LINE__, strpos($sw_content, 'kona3-assets-v1') !== false, "sw.js contains asset cache name");

// 4. Check if drawer.js implements the control handlers and registers Service Worker
$drawer_js = dirname(__DIR__) . '/resource/drawer.js';
test_assert(__LINE__, file_exists($drawer_js), "drawer.js exists");
$js_content = file_get_contents($drawer_js);

test_assert(__LINE__, strpos($js_content, '#offline_cache_toggle') !== false, "drawer.js targets #offline_cache_toggle");
test_assert(__LINE__, strpos($js_content, '#offline_cache_clear') !== false, "drawer.js targets #offline_cache_clear");
test_assert(__LINE__, strpos($js_content, 'navigator.serviceWorker.register') !== false, "drawer.js registers Service Worker");
test_assert(__LINE__, strpos($js_content, 'saveCurrentPage()') !== false, "drawer.js contains saveCurrentPage invocation");
test_assert(__LINE__, strpos($js_content, 'shell_html') !== false, "drawer.js saves shell_html in DB");
test_assert(__LINE__, strpos($js_content, '<!-- KONA3_CONTENT -->') !== false, "drawer.js replaces innerHTML with placeholder");
test_assert(__LINE__, strpos($js_content, 'kona3-assets-v1') !== false, "drawer.js contains asset cache name for cache clear");

echo "offline_cache.test.php: ALL OK\n";
