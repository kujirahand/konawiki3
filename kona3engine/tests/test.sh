#!/bin/bash
SCRIPT_DIR=$(cd $(dirname $0); pwd)
/usr/bin/env php $SCRIPT_DIR/test_main.php go_test

