# task runner for just
PHP := "/usr/bin/env php"
TEST_DIR := "`pwd`/kona3engine/tests"

test:
    {{PHP}} {{TEST_DIR}}/test_main.php go_test

