<?php
require_once __DIR__ . '/test_common.inc.php';
require_once __DIR__ . '/../plugins/mermaid.inc.php';

$test_dir = KONA3_DIR_PRIVATE . '/test_mermaid_' . time();
if (!is_dir($test_dir)) {
    mkdir($test_dir, 0777, true);
}

$full_mmd = $test_dir . '/diagram.mmd';
$full_svg = $test_dir . '/diagram.svg';

// SVG がない場合は再生成対象
@unlink($full_mmd);
@unlink($full_svg);
$needs_update = kona3plugins_mermaid_needs_regenerate($full_mmd, $full_svg);
test_eq(__LINE__, $needs_update, true, 'SVG がない場合は再生成する');

// SVG より MMD が古い場合は再生成しない
file_put_contents($full_mmd, 'graph TD; A-->B;');
file_put_contents($full_svg, '<svg>old</svg>');
touch($full_mmd, time() - 20);
touch($full_svg, time() - 10);
$needs_update = kona3plugins_mermaid_needs_regenerate($full_mmd, $full_svg);
test_eq(__LINE__, $needs_update, false, 'SVG の方が新しい場合は再生成しない');

// MMD が新しい場合は再生成する
touch($full_mmd, time() - 10);
touch($full_svg, time() - 20);
$needs_update = kona3plugins_mermaid_needs_regenerate($full_mmd, $full_svg);
test_eq(__LINE__, $needs_update, true, 'MMD の方が新しい場合は再生成する');

// 内容が変わらなければ MMD は更新しない
clearstatcache(true, $full_mmd);
$before_mtime = filemtime($full_mmd);
$source_updated = kona3plugins_mermaid_write_source($full_mmd, 'graph TD; A-->B;');
clearstatcache(true, $full_mmd);
$after_mtime = filemtime($full_mmd);
test_eq(__LINE__, $source_updated, false, '同じ内容なら MMD を書き換えない');
test_eq(__LINE__, $after_mtime, $before_mtime, '同じ内容なら更新日時は変わらない');

// 内容が変われば MMD を更新する
$source_updated = kona3plugins_mermaid_write_source($full_mmd, 'graph TD; A-->C;');
clearstatcache(true, $full_mmd);
$updated_content = file_get_contents($full_mmd);
$updated_mtime = filemtime($full_mmd);
test_eq(__LINE__, $source_updated, true, '内容が変われば MMD を書き換える');
test_eq(__LINE__, $updated_content, 'graph TD; A-->C;', 'MMD の内容が更新される');
test_assert(__LINE__, $updated_mtime >= $after_mtime, '内容変更後は更新日時が進む');

// ファイル名の正規化
$normalized = kona3plugins_mermaid_normalize_filename('file=bad/name.svg');
test_eq(__LINE__, $normalized, 'bad_name', 'file= と .svg を除去して安全なファイル名にする');

// SVG の保存と危険な SVG の拒否
global $kona3conf;
$valid_svg = '<svg xmlns="http://www.w3.org/2000/svg"><g><text>OK</text></g></svg>';
kona3logout();
$forbidden = kona3plugins_mermaid_save_svg($full_svg, $valid_svg);
test_eq(__LINE__, $forbidden['ok'], false, '未ログインなら SVG を保存しない');
test_eq(__LINE__, $forbidden['status'], 'forbidden', '未ログインなら forbidden を返す');
kona3login('mermaid_test', 'mermaid@example.com', 'normal');
$saved = kona3plugins_mermaid_save_svg($full_svg, $valid_svg);
test_eq(__LINE__, $saved['ok'], true, 'ブラウザで生成した SVG を保存する');
test_eq(__LINE__, file_get_contents($full_svg), $valid_svg, 'SVG の内容が保存される');
$invalid_svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
$rejected = kona3plugins_mermaid_save_svg($full_svg, $invalid_svg);
test_eq(__LINE__, $rejected['ok'], false, 'script を含む SVG は拒否する');
test_eq(__LINE__, $rejected['status'], 'invalid_svg', '危険な SVG の状態を返す');
kona3logout();

// 未ログイン時はファイル書き込みも保存用 Ajax URL の埋め込みもしない
$old_page = isset($kona3conf['page']) ? $kona3conf['page'] : 'FrontPage';
$old_url_index = isset($kona3conf['url.index']) ? $kona3conf['url.index'] : 'index.php';
$kona3conf['page'] = 'FrontPage';
$kona3conf['url.index'] = 'index.php';
unset($kona3conf['plugins.mermaid.init']);
$lazy_filename = 'lazy_mermaid_guest_' . time();
$lazy_svg = KONA3_DIR_DATA . '/' . $lazy_filename . '.svg';
$lazy_mmd = KONA3_DIR_DATA . '/' . $lazy_filename . '.mmd';
@unlink($lazy_svg);
@unlink($lazy_mmd);
$html = kona3plugins_mermaid_execute(['caption', $lazy_filename, 'graph TD; A-->B;']);
test_assert(__LINE__, strpos($html, 'htmlLabels: false') !== false, 'Mermaid は foreignObject を避けるため htmlLabels を無効にする');
test_assert(__LINE__, strpos($html, 'sanitizeMermaidSvgForSave') !== false, '保存前に foreignObject を通常の SVG text に変換する');
test_assert(__LINE__, strpos($html, "split(/\\\n/)") === false, 'JavaScript の正規表現が改行で壊れない');
test_assert(__LINE__, strpos($html, 'split(/\\n/)') !== false, 'JavaScript に改行エスケープを出力する');
test_assert(__LINE__, strpos($html, 'data-save-url=') === false, '未ログインなら SVG 保存用 Ajax URL を埋め込まない');
test_assert(__LINE__, strpos($html, 'data-edit-token=') === false, '未ログインなら SVG 保存用 CSRF トークンを埋め込まない');
test_eq(__LINE__, file_exists($lazy_mmd), false, '未ログインなら MMD ソースを保存しない');

// ログイン時はブラウザ生成 SVG の保存用 Ajax URL を埋め込む
kona3login('mermaid_test', 'mermaid@example.com', 'normal');
$lazy_filename = 'lazy_mermaid_login_' . time();
$lazy_svg = KONA3_DIR_DATA . '/' . $lazy_filename . '.svg';
$lazy_mmd = KONA3_DIR_DATA . '/' . $lazy_filename . '.mmd';
@unlink($lazy_svg);
@unlink($lazy_mmd);
$html = kona3plugins_mermaid_execute(['caption', $lazy_filename, 'graph TD; A-->B;']);
test_assert(__LINE__, strpos($html, 'data-save-url=') !== false, 'SVG 保存用 Ajax URL を埋め込む');
test_assert(__LINE__, strpos($html, 'data-edit-token=') !== false, 'SVG 保存用 CSRF トークンを埋め込む');
test_assert(__LINE__, strpos($html, 'mermaid_ajax') !== false, 'mermaid_ajax アクションを使う');
test_eq(__LINE__, file_exists($lazy_mmd), true, 'MMD ソースは同期保存する');
test_eq(__LINE__, file_exists($lazy_svg), false, 'SVG はブラウザからの Ajax 保存まで作成しない');
kona3logout();

$kona3conf['page'] = $old_page;
$kona3conf['url.index'] = $old_url_index;
@unlink($lazy_mmd);
@unlink($lazy_svg);

@unlink($full_mmd);
@unlink($full_svg);
@rmdir($test_dir);
