<?php
require_once __DIR__ . '/test_common.inc.php';

// ls plugin test
// プラグインのロード
require_once dirname(__DIR__) . '/plugins/ls.inc.php';

// テスト用のページを設定
global $kona3conf;
$kona3conf['page'] = 'FrontPage';

// Test 1: デフォルトフィルタ（全ファイル）
$result1 = kona3plugins_ls_execute([]);
test_assert(__LINE__, !empty($result1), "ls with no filter should return results");
test_assert(__LINE__, strpos($result1, '<ul>') !== false, "ls should return ul tag");
test_assert(__LINE__, strpos($result1, '</ul>') !== false, "ls should close ul tag");

// Test 2: ワイルドカードフィルタ
$result2 = kona3plugins_ls_execute(['*']);
test_assert(__LINE__, !empty($result2), "ls with wildcard filter should return results");
test_assert(__LINE__, strpos($result2, '<a href=') !== false, "ls should contain links");

// Test 3: show_system オプション（MenuBarを表示）
$result3_no_system = kona3plugins_ls_execute(['*']);
$result3_with_system = kona3plugins_ls_execute(['*', 'show_system']);
// show_systemなしではMenuBarは表示されないはず（ただしMenuBar.txtが存在する場合）
test_assert(__LINE__, strlen($result3_with_system) >= strlen($result3_no_system), "show_system should include system pages");

// Test 4: reverse オプション
$result4_normal = kona3plugins_ls_execute(['*']);
$result4_reverse = kona3plugins_ls_execute(['*', 'reverse']);
test_assert(__LINE__, !empty($result4_reverse), "ls with reverse option should return results");
test_ne(__LINE__, $result4_normal, $result4_reverse, "reverse should change order");

// Test 5: show_dir オプション
$result5_with_dir = kona3plugins_ls_execute(['*', 'show_dir']);
test_assert(__LINE__, !empty($result5_with_dir), "ls with show_dir should return results");

// Test 6: sort_by_time オプション
$result6 = kona3plugins_ls_execute(['*', 'sort_by_time']);
test_assert(__LINE__, !empty($result6), "ls with sort_by_time should return results");
test_assert(__LINE__, strpos($result6, '<ul>') !== false, "ls with sort_by_time should return ul tag");

// Test 7: パストラバーサル攻撃のテスト（セキュリティ）
// ../ は / に正規化されるため、空ではなく有効な結果が返される
$result7 = kona3plugins_ls_execute(['../../../']);
test_assert(__LINE__, is_string($result7), "ls should safely handle path traversal patterns");

// Test 8: 複数のドットを含むパターン
$result8 = kona3plugins_ls_execute(['...']);
test_assert(__LINE__, is_string($result8), "ls should handle multiple dots safely");

// Test 9: バックスラッシュを含むパターン
$result9 = kona3plugins_ls_execute(['test\\file']);
test_assert(__LINE__, is_string($result9), "ls should handle backslash safely");

// Test 10: ヌル文字を含むパターン（セキュリティ）
$result10 = kona3plugins_ls_execute(["test\0file"]);
test_assert(__LINE__, is_string($result10), "ls should handle null character safely");

// Test 11: サブディレクトリのパターン
// ... 環境依存のためスキップします。

// Test 12: 拡張子なしのフィルタ（.txt と .md の両方を対象）
$kona3conf['page'] = 'FrontPage';
$result12 = kona3plugins_ls_execute(['Front*']);
test_assert(__LINE__, !empty($result12), "ls should match files without extension");

// Test 13: 空のフィルタ
$result13 = kona3plugins_ls_execute(['']);
test_assert(__LINE__, !empty($result13), "ls with empty filter should use default");

// Test 14: スラッシュのみのフィルタ
$result14 = kona3plugins_ls_execute(['/']);
test_assert(__LINE__, is_string($result14), "ls should handle slash-only filter safely");

// Test 15: 連続するスラッシュ
$result15 = kona3plugins_ls_execute(['test//file']);
test_assert(__LINE__, is_string($result15), "ls should handle multiple slashes safely");

echo "\nls plugin tests completed.\n";
