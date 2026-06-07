<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/action/show.inc.php';
require_once dirname(__DIR__) . '/plugins/alias.inc.php';

$target = kona3show_find_alias_target("!!alias(TargetPage)\n");
test_eq(__LINE__, $target, "TargetPage", "alias target is detected");

$target = kona3show_find_alias_target("# title\n\n!!alias( Japanese/Page )\n");
test_eq(__LINE__, $target, "Japanese/Page", "alias target is trimmed");

$target = kona3show_find_alias_target("!!alias()\n");
test_eq(__LINE__, $target, FALSE, "empty alias target is ignored");

$target = kona3show_find_alias_target("本文\n");
test_eq(__LINE__, $target, FALSE, "non alias page is ignored");

$html = kona3plugins_alias_execute(["TargetPage"]);
test_assert(__LINE__, strpos($html, "TargetPage") !== FALSE, "alias plugin renders target label");
test_assert(__LINE__, strpos($html, "href=") !== FALSE, "alias plugin renders a link");
