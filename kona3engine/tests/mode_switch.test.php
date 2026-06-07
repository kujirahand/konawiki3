<?php
require_once __DIR__ . '/test_common.inc.php';

// テスト用データ設定
$page = 'TestPage_ModeSwitch';
$data_dir = KONA3_DIR_DATA;
$meta_dir = $data_dir . '/.meta';

$path_txt = $data_dir . '/' . $page . '.txt';
$path_md = $data_dir . '/' . $page . '.md';
$path_meta = $meta_dir . '/' . $page . '.json';

// クリーンアップ関数
function cleanup_mode_switch_test() {
    global $path_txt, $path_md, $path_meta;
    if (file_exists($path_txt)) @unlink($path_txt);
    if (file_exists($path_md)) @unlink($path_md);
    if (file_exists($path_meta)) @unlink($path_meta);
}

// 初期化
cleanup_mode_switch_test();

echo "=== Mode Switch Resolution Test ===\n";

// 両方のファイルを作成
file_put_contents($path_txt, 'This is KonaNotation.');
file_put_contents($path_md, 'This is Markdown.');

// 1. メタデータなし、両方あり、デフォルト＝txt
kona3setConf('def_text_ext', 'txt');
$file = koan3getWikiFileText($page);
test_eq(__LINE__, basename($file), $page . '.txt', 'Default ext txt resolutions when both exist');

// 2. メタデータなし、両方あり、デフォルト＝md
kona3setConf('def_text_ext', 'md');
$file = koan3getWikiFileText($page);
test_eq(__LINE__, basename($file), $page . '.md', 'Default ext md resolutions when both exist');

// 3. メタデータあり (Markdown)、両方あり
kona3db_savePageMeta($page, ['mode' => 'Markdown']);
$file = koan3getWikiFileText($page);
test_eq(__LINE__, basename($file), $page . '.md', 'Prioritize Markdown mode when both exist');

// 4. メタデータあり (KonaNotation)、両方あり
kona3db_savePageMeta($page, ['mode' => 'KonaNotation']);
$file = koan3getWikiFileText($page);
test_eq(__LINE__, basename($file), $page . '.txt', 'Prioritize KonaNotation mode when both exist');

// 5. 片方だけ存在する場合 (md のみ)
cleanup_mode_switch_test();
file_put_contents($path_md, 'This is Markdown.');
kona3setConf('def_text_ext', 'txt'); // デフォルトは txt だが md しか存在しない
$file = koan3getWikiFileText($page);
test_eq(__LINE__, basename($file), $page . '.md', 'Fallback to md when only md exists');

// 6. 片方だけ存在する場合 (txt のみ)
cleanup_mode_switch_test();
file_put_contents($path_txt, 'This is KonaNotation.');
kona3setConf('def_text_ext', 'md'); // デフォルトは md だが txt しか存在しない
$file = koan3getWikiFileText($page);
test_eq(__LINE__, basename($file), $page . '.txt', 'Fallback to txt when only txt exists');

// クリーンアップ
cleanup_mode_switch_test();
echo "=== Mode Switch Resolution Test Completed ===\n";
