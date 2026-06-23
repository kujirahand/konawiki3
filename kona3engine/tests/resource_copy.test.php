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

// 1. Initial Copy Test
kona3index_copyResourceFiles($temp_dir);
test_assert(__LINE__, file_exists($test_sw), "sw.js is copied to temp_dir");
test_assert(__LINE__, file_exists($test_fav), "favicon.ico is copied to temp_dir");

// 2. favicon.ico should NOT be overwritten if it already exists
file_put_contents($test_fav, "dummy_favicon");
kona3index_copyResourceFiles($temp_dir);
test_eq(__LINE__, file_get_contents($test_fav), "dummy_favicon", "favicon.ico is not overwritten if exists");

// 3. sw.js should be overwritten if the source is newer
$src_sw = KONA3_DIR_ENGINE . '/resource/sw.js';
$src_time = filemtime($src_sw);
file_put_contents($test_sw, "dummy_sw");
touch($test_sw, $src_time - 100); // make it older
kona3index_copyResourceFiles($temp_dir);
test_ne(__LINE__, file_get_contents($test_sw), "dummy_sw", "sw.js is overwritten because source is newer");

// 4. sw.js should NOT be overwritten if the destination is newer
file_put_contents($test_sw, "dummy_sw_newer");
touch($test_sw, $src_time + 100); // make it newer
kona3index_copyResourceFiles($temp_dir);
test_eq(__LINE__, file_get_contents($test_sw), "dummy_sw_newer", "sw.js is not overwritten because dest is newer");

// Cleanup
if (file_exists($test_sw)) { unlink($test_sw); }
if (file_exists($test_fav)) { unlink($test_fav); }
if (file_exists($temp_dir)) { rmdir($temp_dir); }

echo "resource_copy.test.php: ALL OK\n";
