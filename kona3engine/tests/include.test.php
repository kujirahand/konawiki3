<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/plugins/include.inc.php';

$txt_page = 'IncludeTestTxt_' . time();
$md_page = 'IncludeTestMd_' . time();
$txt_file = kona3getWikiFile($txt_page, true, '.txt');
$md_file = kona3getWikiFile($md_page, true, '.md');

file_put_contents($txt_file, "* Include Test Txt\n\nTXT body");
file_put_contents($md_file, "# Include Test Md\n\nMD body");

$txt_html = kona3plugins_include_execute([$txt_page]);
test_assert(__LINE__, strpos($txt_html, 'Include Test Txt') !== false, "include should render txt page");
test_assert(__LINE__, strpos($txt_html, 'TXT body') !== false, "include should include txt body");

$md_html = kona3plugins_include_execute([$md_page]);
test_assert(__LINE__, strpos($md_html, 'Include Test Md') !== false, "include should render md page");
test_assert(__LINE__, strpos($md_html, 'MD body') !== false, "include should include md body");

$both_html = kona3plugins_include_execute(["{$txt_page}\n{$md_page}"]);
test_assert(__LINE__, strpos($both_html, 'Include Test Txt') !== false, "include should render txt page in list");
test_assert(__LINE__, strpos($both_html, 'Include Test Md') !== false, "include should render md page in list");

$multi_arg_html = kona3plugins_include_execute([$txt_page, $md_page]);
test_assert(__LINE__, strpos($multi_arg_html, 'Include Test Txt') !== false, "include should render txt page from multiple args");
test_assert(__LINE__, strpos($multi_arg_html, 'Include Test Md') !== false, "include should render md page from multiple args");

@unlink($txt_file);
@unlink($md_file);
