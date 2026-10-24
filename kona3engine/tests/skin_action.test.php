<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/action/skin.inc.php';

// スキンファイルの出力内容 (Content-Length は出力内容の長さで送る)

$css_path = KONA3_DIR_SKIN . '/def/kona3.css';
$css = file_get_contents($css_path);
$body = kona3_skin_buildBody($css_path, '.css', 'kona3.css', 'def', 'Sat, 24 Oct 2026 00:00:00 GMT');

// CSSは情報コメントが先頭に付き、ファイルの内容が末尾まで欠けずに続くこと
test_assert(__LINE__, strpos($body, "/**\n+[konawiki3]\n") === 0, "css: info comment is prepended");
test_assert(__LINE__, substr($body, -strlen($css)) === $css, "css: whole file content follows the comment");
test_assert(__LINE__, strlen($body) > filesize($css_path), "css: output is longer than the file (Content-Length must not be filesize)");

// CSS以外はファイルの内容そのまま
$ico_path = KONA3_DIR_ENGINE . '/resource/favicon.ico';
$ico = kona3_skin_buildBody($ico_path, '.ico', 'favicon.ico', 'def', '');
test_eq(__LINE__, $ico, file_get_contents($ico_path), "non-css: output equals file content");
