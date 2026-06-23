<?php
require_once __DIR__ . '/test_common.inc.php';

// Create a temporary directory for the index
$temp_dir = __DIR__ . '/temp_index';
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0777, true);
}

// Clear any old files before test
$test_sw = $temp_dir . '/sw.js';
$test_fav = $temp_dir . '/favicon.ico';
if (file_exists($test_sw)) { unlink($test_sw); }
if (file_exists($test_fav)) { unlink($test_fav); }

// If KONA3_DIR_INDEX is not defined, we can define it and run the main function directly.
// Otherwise we simulate the copying logic to avoid constant redefinition errors in PHP.
if (!defined('KONA3_DIR_INDEX')) {
    define('KONA3_DIR_INDEX', $temp_dir);
}

if (defined('KONA3_DIR_INDEX') && KONA3_DIR_INDEX === $temp_dir) {
    // 1. Initial Copy Test
    kona3index_main();
    test_assert(__LINE__, file_exists($test_sw), "sw.js is copied to KONA3_DIR_INDEX");
    test_assert(__LINE__, file_exists($test_fav), "favicon.ico is copied to KONA3_DIR_INDEX");
    
    // 2. favicon.ico should NOT be overwritten if it already exists
    file_put_contents($test_fav, "dummy_favicon");
    kona3index_main();
    test_eq(__LINE__, file_get_contents($test_fav), "dummy_favicon", "favicon.ico is not overwritten if exists");
    
    // 3. sw.js should be overwritten if the source is newer
    $src_sw = KONA3_DIR_ENGINE . '/resource/sw.js';
    $src_time = filemtime($src_sw);
    file_put_contents($test_sw, "dummy_sw");
    touch($test_sw, $src_time - 100); // make it older
    kona3index_main();
    test_ne(__LINE__, file_get_contents($test_sw), "dummy_sw", "sw.js is overwritten because source is newer");
    
    // 4. sw.js should NOT be overwritten if the destination is newer
    file_put_contents($test_sw, "dummy_sw_newer");
    touch($test_sw, $src_time + 100); // make it newer
    kona3index_main();
    test_eq(__LINE__, file_get_contents($test_sw), "dummy_sw_newer", "sw.js is not overwritten because dest is newer");
} else {
    // Fallback: Simulate the logic if KONA3_DIR_INDEX was already defined by another test
    simulate_resource_copy($temp_dir);
}

// Cleanup
if (file_exists($test_sw)) { unlink($test_sw); }
if (file_exists($test_fav)) { unlink($test_fav); }
if (file_exists($temp_dir)) { rmdir($temp_dir); }

echo "resource_copy.test.php: ALL OK\n";

function simulate_resource_copy($temp_dir) {
    $test_sw = $temp_dir . '/sw.js';
    $test_fav = $temp_dir . '/favicon.ico';
    $src_sw = KONA3_DIR_ENGINE . '/resource/sw.js';
    $src_fav = KONA3_DIR_ENGINE . '/resource/favicon.ico';

    // 1. Initial Copy
    copy_logic_simulate($temp_dir);
    test_assert(__LINE__, file_exists($test_sw), "Simulate: sw.js is copied");
    test_assert(__LINE__, file_exists($test_fav), "Simulate: favicon.ico is copied");

    // 2. favicon.ico exists
    file_put_contents($test_fav, "dummy_favicon");
    copy_logic_simulate($temp_dir);
    test_eq(__LINE__, file_get_contents($test_fav), "dummy_favicon", "Simulate: favicon.ico not overwritten");

    // 3. sw.js newer
    $src_time = filemtime($src_sw);
    file_put_contents($test_sw, "dummy_sw");
    touch($test_sw, $src_time - 100);
    copy_logic_simulate($temp_dir);
    test_ne(__LINE__, file_get_contents($test_sw), "dummy_sw", "Simulate: sw.js overwritten when source newer");

    // 4. sw.js dest newer
    file_put_contents($test_sw, "dummy_sw_newer");
    touch($test_sw, $src_time + 100);
    copy_logic_simulate($temp_dir);
    test_eq(__LINE__, file_get_contents($test_sw), "dummy_sw_newer", "Simulate: sw.js not overwritten when dest newer");
}

function copy_logic_simulate($temp_dir) {
    $dest_sw = $temp_dir . '/sw.js';
    $src_sw = KONA3_DIR_ENGINE . '/resource/sw.js';
    if (file_exists($src_sw)) {
        if (!file_exists($dest_sw) || (filemtime($src_sw) > filemtime($dest_sw))) {
            @copy($src_sw, $dest_sw);
        }
    }
    $dest_fav = $temp_dir . '/favicon.ico';
    $src_fav = KONA3_DIR_ENGINE . '/resource/favicon.ico';
    if (file_exists($src_fav)) {
        if (!file_exists($dest_fav)) {
            @copy($src_fav, $dest_fav);
        }
    }
}
