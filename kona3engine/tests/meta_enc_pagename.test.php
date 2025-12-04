<?php
/**
 * Test that kona3db_getPageMetaFile respects enc_pagename configuration
 */
require_once __DIR__ . '/test_common.inc.php';

echo "=== Meta File enc_pagename Test ===\n";

global $kona3conf;

// Test 1: enc_pagename = FALSE (default)
echo "Test 1: enc_pagename = FALSE\n";
$kona3conf['enc_pagename'] = FALSE;

$metaFile = kona3db_getPageMetaFile('TestPage');
test_assert(__LINE__, strpos($metaFile, 'TestPage.json') !== FALSE, "Simple page name without encoding");

$metaFile = kona3db_getPageMetaFile('Test/Page');
test_assert(__LINE__, strpos($metaFile, 'Test/Page.json') !== FALSE, "Hierarchical page name without encoding");

$metaFile = kona3db_getPageMetaFile('Test/Sub/Page');
test_assert(__LINE__, strpos($metaFile, 'Test/Sub/Page.json') !== FALSE, "Deep hierarchical page name without encoding");

// Test 2: enc_pagename = TRUE
echo "Test 2: enc_pagename = TRUE\n";
$kona3conf['enc_pagename'] = TRUE;

$metaFile = kona3db_getPageMetaFile('TestPage');
test_assert(__LINE__, strpos($metaFile, 'TestPage.json') !== FALSE, "Simple page name with encoding (no special chars)");

$metaFile = kona3db_getPageMetaFile('Test/Page');
// When enc_pagename is TRUE, "Test/Page" should become "Test%2FPage" - wait no, the slash is a directory separator
// Actually, looking at kona3getWikiFile, it splits by '/' first, then encodes each part
// So "Test/Page" would have parts ["Test", "Page"], each encoded, resulting in "Test/Page" still
test_assert(__LINE__, strpos($metaFile, 'Test/Page.json') !== FALSE, "Hierarchical page name parts encoded separately");

// Test 3: Special characters with enc_pagename = TRUE
echo "Test 3: Special characters with enc_pagename = TRUE\n";
$kona3conf['enc_pagename'] = TRUE;

$metaFile = kona3db_getPageMetaFile('Test Page');
// urlencode encodes spaces as '+', not '%20'
test_assert(__LINE__, strpos($metaFile, 'Test+Page.json') !== FALSE, "Space should be encoded as + (urlencode behavior)");

$metaFile = kona3db_getPageMetaFile('Test&Page');
test_assert(__LINE__, strpos($metaFile, 'Test%26Page.json') !== FALSE, "Ampersand should be encoded as %26");

$metaFile = kona3db_getPageMetaFile('日本語');
$expected = urlencode('日本語');
test_assert(__LINE__, strpos($metaFile, $expected . '.json') !== FALSE, "Japanese characters should be URL encoded");

// Test 4: Special characters with enc_pagename = FALSE
echo "Test 4: Special characters with enc_pagename = FALSE\n";
$kona3conf['enc_pagename'] = FALSE;

$metaFile = kona3db_getPageMetaFile('Test Page');
test_assert(__LINE__, strpos($metaFile, 'Test Page.json') !== FALSE, "Space should NOT be encoded when enc_pagename is FALSE");

$metaFile = kona3db_getPageMetaFile('日本語');
test_assert(__LINE__, strpos($metaFile, '日本語.json') !== FALSE, "Japanese characters should NOT be encoded when enc_pagename is FALSE");

// Test 5: Hierarchical with special characters
echo "Test 5: Hierarchical with special characters\n";
$kona3conf['enc_pagename'] = TRUE;

$metaFile = kona3db_getPageMetaFile('Parent Dir/Child Page');
// urlencode encodes spaces as '+', not '%20'
test_assert(__LINE__, strpos($metaFile, 'Parent+Dir/Child+Page.json') !== FALSE, "Hierarchical path with spaces encoded");

// Test 6: With file extension
echo "Test 6: With file extension\n";
$kona3conf['enc_pagename'] = TRUE;

$metaFile = kona3db_getPageMetaFile('TestPage.txt');
test_assert(__LINE__, strpos($metaFile, 'TestPage.json') !== FALSE, "Extension .txt should be removed");

$metaFile = kona3db_getPageMetaFile('TestPage.md');
test_assert(__LINE__, strpos($metaFile, 'TestPage.json') !== FALSE, "Extension .md should be removed");

// Reset to default
$kona3conf['enc_pagename'] = FALSE;

echo "\n=== Meta File enc_pagename Test Completed ===\n";
