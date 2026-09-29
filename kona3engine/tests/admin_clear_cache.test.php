<?php
require_once __DIR__ . '/test_common.inc.php';
@include_once KONA3_DIR_ENGINE . '/action/admin.inc.php'; // header() 警告を抑制

$dir = KONA3_DIR_CACHE . '/test_clear_cache_' . uniqid();
@mkdir($dir, 0777, true);
$targets = ['a.html.php', 'b.html.123_v3_x.php', 'c.html.php.99.tmp'];
$keeps = ['README.md', 'keep.sqlite', 'other.php'];
foreach (array_merge($targets, $keeps) as $f) {
    file_put_contents("$dir/$f", 'x');
}
$n = kona3admin_clearTemplateCache($dir);
test_eq(__LINE__, $n, 3, "clearTemplateCache: テンプレートキャッシュ(php と途中の tmp)のみ削除される");
test_assert(__LINE__, !file_exists("$dir/c.html.php.99.tmp"), "途中のtmpが削除されること");
test_assert(__LINE__, !file_exists("$dir/a.html.php"), "a.html.php が削除されること");
test_assert(__LINE__, !file_exists("$dir/b.html.123_v3_x.php"), "日時付きキャッシュが削除されること");
foreach (['README.md', 'keep.sqlite', 'other.php'] as $f) {
    test_assert(__LINE__, file_exists("$dir/$f"), "$f は残ること");
}
foreach (glob("$dir/*") as $f) { @unlink($f); }
@rmdir($dir);
