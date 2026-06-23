<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/action/versionup.inc.php';

// Version up migration test (3.3 to 3.4)

$json_path = KONA3_PAGE_ID_JSON;
$bak_path = $json_path . '.bak';

// 1. Back up existing files if they exist
$original_json_data = null;
$original_bak_data = null;
if (file_exists($json_path)) {
    $original_json_data = file_get_contents($json_path);
    unlink($json_path);
}
if (file_exists($bak_path)) {
    $original_bak_data = file_get_contents($bak_path);
    unlink($bak_path);
}

// Ensure output files do not exist initially
$file1 = KONA3_DIR_DATA . "/99991.md";
$file2 = KONA3_DIR_DATA . "/99992.md";
$file3 = KONA3_DIR_DATA . "/99993.md";
if (file_exists($file1)) { unlink($file1); }
if (file_exists($file2)) { unlink($file2); }
if (file_exists($file3)) { unlink($file3); }

try {
    // 2. Write dummy JSON
    $dummy_data = [
        "TestMigrationPage1" => 99991,
        "TestMigrationPage2" => 99992,
        "hoge/実行権限" => 99993
    ];
    file_put_contents($json_path, json_encode($dummy_data, JSON_UNESCAPED_UNICODE));

    // 3. Execute migration
    $res = kona3_ver33to34();
    test_assert(__LINE__, $res === TRUE, "kona3_ver33to34 should return TRUE on success when files are created");

    // 4. Verify created alias files
    test_assert(__LINE__, file_exists($file1), "Alias file for 99991 should be created");
    test_assert(__LINE__, file_exists($file2), "Alias file for 99992 should be created");
    test_assert(__LINE__, file_exists($file3), "Alias file for 99993 should be created");

    $content1 = file_get_contents($file1);
    $content2 = file_get_contents($file2);
    $content3 = file_get_contents($file3);
    test_eq(__LINE__, $content1, "!!alias(TestMigrationPage1)", "Alias file 1 contents match");
    test_eq(__LINE__, $content2, "!!alias(TestMigrationPage2)", "Alias file 2 contents match");
    test_eq(__LINE__, $content3, "!!alias(hoge/実行権限)", "Alias file 3 contents match with Japanese page and slashes");

    // 4.5. Verify db mapping
    test_eq(__LINE__, kona3db_getPageNameById(99991), "TestMigrationPage1", "Database page name for 99991 is restored");
    test_eq(__LINE__, kona3db_getPageNameById(99992), "TestMigrationPage2", "Database page name for 99992 is restored");
    test_eq(__LINE__, kona3db_getPageNameById(99993), "hoge/実行権限", "Database page name for 99993 is restored");

    // 5. Verify JSON is renamed to JSON.bak
    test_assert(__LINE__, !file_exists($json_path), "Original JSON should be removed");
    test_assert(__LINE__, file_exists($bak_path), "Original JSON should be renamed to JSON.bak");

    // 6. Test no-overwrite behavior
    // Re-create JSON and write custom content to 99991.md to verify it won't be overwritten
    file_put_contents($json_path, json_encode($dummy_data, JSON_UNESCAPED_UNICODE));
    unlink($bak_path); // delete bak to allow rerun
    file_put_contents($file1, "Existing content - do not overwrite");

    $res2 = kona3_ver33to34();
    test_assert(__LINE__, $res2 === FALSE, "kona3_ver33to34 rerun should return FALSE when nothing new is created");
    test_assert(__LINE__, !file_exists($json_path), "Original JSON should be removed on rerun");
    test_assert(__LINE__, file_exists($bak_path), "Original JSON should be renamed to JSON.bak on rerun");
    
    $content1_rerun = file_get_contents($file1);
    test_eq(__LINE__, $content1_rerun, "Existing content - do not overwrite", "Existing file should not be overwritten");

} finally {
    // DB cleanup
    db_exec("DELETE FROM pages WHERE page_id IN (99991, 99992, 99993)");

    // Cleanup generated files
    if (file_exists($file1)) { unlink($file1); }
    if (file_exists($file2)) { unlink($file2); }
    if (file_exists($file3)) { unlink($file3); }
    if (file_exists($json_path)) { unlink($json_path); }
    if (file_exists($bak_path)) { unlink($bak_path); }

    // Restore original files
    if ($original_json_data !== null) {
        file_put_contents($json_path, $original_json_data);
    }
    if ($original_bak_data !== null) {
        file_put_contents($bak_path, $original_bak_data);
    }
}
