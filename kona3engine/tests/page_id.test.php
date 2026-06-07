<?php
require_once __DIR__ . '/test_common.inc.php';

// page_id test
$page = "PageIdTest_" . time();
$page_id = kona3db_getPageId($page, TRUE);
test_assert(__LINE__, is_numeric($page_id) && $page_id > 0, "kona3db_getPageId creates a page ID");
test_eq(__LINE__, kona3db_getPageId($page, FALSE), $page_id, "kona3db_getPageId returns the same ID for an existing page");
test_eq(__LINE__, kona3db_getPageNameById($page_id), $page, "kona3db_getPageNameById returns the created page name");

$cached_page = "PageIdCacheTest_" . time();
$cached_id = kona3db_getPageId($cached_page, TRUE);
test_assert(__LINE__, is_numeric($cached_id) && $cached_id > 0, "kona3db_getPageId creates a page ID after reverse lookup cache exists");
test_eq(__LINE__, kona3db_getPageNameById($cached_id), $cached_page, "kona3db_getPageNameById sees newly created IDs after cache invalidation");
