<?php
require_once __DIR__ . '/test_common.inc.php';

// login test
test_eq(__LINE__, kona3isLogin(), FALSE, "login test");
