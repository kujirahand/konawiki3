<?php
require_once __DIR__ . '/test_common.inc.php';

// page_id test
test_eq(__LINE__, kona3db_getPageId("FrontPage"), 1, "FrontPage");
test_eq(__LINE__, "hoge", "fuga", "error test");
