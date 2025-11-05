<?php
require_once __DIR__ . '/test_common.inc.php';

// page_id test
test_eq(__LINE__, kona3db_getPageId("FrontPage", TRUE), 1, "kona3db_getPageId ... Please setup wiki");
test_eq(__LINE__, kona3db_getPageNameById(1), "FrontPage", "kona3db_getPageNameById");
