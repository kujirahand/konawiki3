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

// Include edit action for sync tests
require_once dirname(__DIR__) . '/action/edit.inc.php';

// --- Test: kona3edit_sync_aliases ---
$page_from = "TestAliasPageFrom";
$page_to = "TestAliasPageTo";

// Clear metadata beforehand
$metaFile = kona3db_getPageMetaFile($page_to);
if (file_exists($metaFile)) {
    @unlink($metaFile);
}

// 1. Add new alias
kona3edit_sync_aliases($page_from, FALSE, $page_to);

$meta = kona3db_loadPageMeta($page_to);
test_assert(__LINE__, is_array($meta), "meta is created for target page");
test_assert(__LINE__, isset($meta['aliases']) && is_array($meta['aliases']), "aliases array is created");
test_eq(__LINE__, count($meta['aliases']), 1, "aliases contains 1 element");
test_eq(__LINE__, $meta['aliases'][0], $page_from, "aliases element is $page_from");

// 2. Prevent duplicate alias
kona3edit_sync_aliases($page_from, FALSE, $page_to);
$meta = kona3db_loadPageMeta($page_to);
test_eq(__LINE__, count($meta['aliases']), 1, "aliases still contains 1 element (no duplicate)");

// 3. Remove alias
kona3edit_sync_aliases($page_from, $page_to, FALSE);
$meta = kona3db_loadPageMeta($page_to);
test_eq(__LINE__, count($meta['aliases']), 0, "aliases is empty after removal");

// Cleanup test metadata
if (file_exists($metaFile)) {
    @unlink($metaFile);
}

// --- Test: kona3getShortcutLink with aliases ---
$_GET['page'] = $page_to;
// Setup virtual metadata with an alias
$meta = ['aliases' => [$page_from]];
kona3db_savePageMeta($page_to, $meta);

// Test with 'en' language
$old_lang = kona3setConf('lang', 'en');
global $lang_data;
$lang_data = null; // reset cache
$link = kona3getShortcutLink();
test_assert(__LINE__, strpos($link, " &nbsp; (alias: <a href=\"") !== FALSE, "shortcut link contains alias format (en)");
test_assert(__LINE__, strpos($link, ">$page_from</a>)") !== FALSE, "shortcut link contains alias link (en)");

// Test with 'ja' language
kona3setConf('lang', 'ja');
$lang_data = null; // reset cache
$link = kona3getShortcutLink();
test_assert(__LINE__, strpos($link, " &nbsp; (別名: <a href=\"") !== FALSE, "shortcut link contains alias format (ja)");
test_assert(__LINE__, strpos($link, ">$page_from</a>)") !== FALSE, "shortcut link contains alias link (ja)");

// Restore language
kona3setConf('lang', $old_lang);
$lang_data = null;

// Cleanup test metadata
if (file_exists($metaFile)) {
    @unlink($metaFile);
}

