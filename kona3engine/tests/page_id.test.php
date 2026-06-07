<?php
require_once __DIR__ . '/test_common.inc.php';

// page_id test
test_assert(__LINE__, kona3db_decodeLegacyPageIds(FALSE) === [], "legacy page ID load failure is treated as an empty map");
test_assert(__LINE__, kona3db_decodeLegacyPageIds('') === [], "empty legacy page ID JSON is treated as an empty map");
test_assert(__LINE__, kona3db_decodeLegacyPageIds('{"LegacyPage":123}') === ["LegacyPage" => 123], "legacy page ID JSON is decoded");

$page = "PageIdTest_" . time();
$page_id_file = KONA3_PAGE_ID_JSON;
$before_exists = file_exists($page_id_file);
$before_mtime = $before_exists ? filemtime($page_id_file) : FALSE;
$before_hash = $before_exists ? hash_file('sha256', $page_id_file) : FALSE;

$page_id = kona3db_getPageId($page, TRUE);
test_assert(__LINE__, is_numeric($page_id) && $page_id > 0, "kona3db_getPageId creates an internal page ID");
test_eq(__LINE__, kona3db_getPageId($page, FALSE), $page_id, "kona3db_getPageId returns the same internal ID");
test_eq(__LINE__, kona3db_getPageNameById($page_id), $page, "kona3db_getPageNameById returns the created page name");
test_eq(__LINE__, file_exists($page_id_file), $before_exists, "kona3db_getPageId does not create .kona3_page_id.json");
if ($before_exists) {
    clearstatcache(TRUE, $page_id_file);
    test_eq(__LINE__, filemtime($page_id_file), $before_mtime, "kona3db_getPageId does not touch .kona3_page_id.json mtime");
    test_eq(__LINE__, hash_file('sha256', $page_id_file), $before_hash, "kona3db_getPageId does not modify .kona3_page_id.json");
}

$cached_page = "PageIdCacheTest_" . time();
$cached_id = kona3db_getPageId($cached_page, TRUE);
test_assert(__LINE__, is_numeric($cached_id) && $cached_id > 0, "kona3db_getPageId creates an internal ID after reverse lookup");
test_eq(__LINE__, kona3db_getPageNameById($cached_id), $cached_page, "kona3db_getPageNameById sees newly created internal IDs");
