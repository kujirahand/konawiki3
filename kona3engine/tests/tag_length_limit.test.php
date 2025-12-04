<?php
require_once __DIR__ . '/test_common.inc.php';

echo "=== Tag Length Limit Test ===\n";

// Test 1: Normal length tag (should not be truncated)
echo "Test 1: Normal length tag\n";
$normal_tag = "normal_tag";
kona3tags_addPageTag("TestPage1", $normal_tag);
$tags = kona3tags_getPageTags("TestPage1");
test_eq(__LINE__, $normal_tag, $tags[0], "Normal tag should remain unchanged");

// Test 2: 20 character tag (should not be truncated)
echo "Test 2: 20 character tag\n";
$tag_20 = str_repeat("a", 20);
kona3tags_addPageTag("TestPage2", $tag_20);
$tags = kona3tags_getPageTags("TestPage2");
test_eq(__LINE__, 20, mb_strlen($tags[0]), "20 character tag should remain 20 characters");

// Test 3: 21 character tag (should be truncated to 20)
echo "Test 3: 21 character tag (should be truncated)\n";
$tag_21 = str_repeat("b", 21);
kona3tags_addPageTag("TestPage3", $tag_21);
$tags = kona3tags_getPageTags("TestPage3");
test_eq(__LINE__, 20, mb_strlen($tags[0]), "21 character tag should be truncated to 20");
test_eq(__LINE__, str_repeat("b", 20), $tags[0], "Truncated tag should match expected value");

// Test 4: 50 character tag (should be truncated to 20)
echo "Test 4: 50 character tag (should be truncated)\n";
$tag_50 = str_repeat("c", 50);
kona3tags_addPageTag("TestPage4", $tag_50);
$tags = kona3tags_getPageTags("TestPage4");
test_eq(__LINE__, 20, mb_strlen($tags[0]), "50 character tag should be truncated to 20");

// Test 5: Multibyte characters (Japanese)
echo "Test 5: Multibyte character tag\n";
$tag_mb_21 = str_repeat("あ", 21); // 21 Japanese characters
kona3tags_addPageTag("TestPage5", $tag_mb_21);
$tags = kona3tags_getPageTags("TestPage5");
// With URL encoding, multibyte characters are preserved
test_eq(__LINE__, 20, mb_strlen($tags[0]), "21 multibyte character tag should be truncated to 20");
test_eq(__LINE__, str_repeat("あ", 20), $tags[0], "Truncated multibyte tag should match expected value");

// Test 6: Mixed ASCII and multibyte
echo "Test 6: Mixed ASCII and multibyte tag\n";
$tag_mixed = str_repeat("a", 10) . str_repeat("あ", 11); // 21 characters total
kona3tags_addPageTag("TestPage6", $tag_mixed);
$tags = kona3tags_getPageTags("TestPage6");
// With URL encoding, the tag should be truncated to 20 characters
test_eq(__LINE__, 20, mb_strlen($tags[0]), "21 mixed character tag should be truncated to 20");
test_eq(__LINE__, str_repeat("a", 10) . str_repeat("あ", 10), $tags[0], "Truncated mixed tag should match expected value");

// Test 7: Tag with spaces and special characters
echo "Test 7: Long tag with spaces\n";
$tag_spaces = str_repeat("tag ", 6); // 24 characters (6 * 4)
kona3tags_addPageTag("TestPage7", $tag_spaces);
$tags = kona3tags_getPageTags("TestPage7");
test_assert(__LINE__, mb_strlen($tags[0]) <= 20, "Tag with spaces should be truncated to 20 or less");

// Cleanup
echo "Test 8: Cleanup\n";
kona3tags_clearPageTags("TestPage1");
kona3tags_clearPageTags("TestPage2");
kona3tags_clearPageTags("TestPage3");
kona3tags_clearPageTags("TestPage4");
kona3tags_clearPageTags("TestPage5");
kona3tags_clearPageTags("TestPage6");
kona3tags_clearPageTags("TestPage7");
test_assert(__LINE__, TRUE, "Cleanup completed");

echo "\n=== Tag Length Limit Test Completed ===\n";
