<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/action/go.inc.php';

test_eq(__LINE__, kona3go_getRedirectURL(''), 'index.php', "empty go target redirects to index.php");
test_eq(__LINE__, kona3go_getRedirectURL('FrontPage'), 'index.php?FrontPage&show', "go.php?FrontPage redirects to show action");
test_eq(__LINE__, kona3go_getRedirectURL('Category/SubPage'), 'index.php?Category%2FSubPage&show', "go.php?sub page redirects to show action");
test_eq(__LINE__, kona3go_getRedirectURL('日本語ページ'), 'index.php?' . urlencode('日本語ページ') . '&show', "go.php?Japanese page redirects to show action");
