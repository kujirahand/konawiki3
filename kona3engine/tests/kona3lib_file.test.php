<?php
require_once __DIR__ . '/test_common.inc.php';

// --- kona3getWikiFile() のテスト ---

// 基本的なファイルパスの取得
$file = kona3getWikiFile("TestPage", TRUE, '.txt');
test_assert(__LINE__, strpos($file, "TestPage.txt") !== FALSE, "基本的なファイルパス");
test_assert(__LINE__, strpos($file, KONA3_DIR_DATA) !== FALSE, "データディレクトリを含む");

// 拡張子なしでの取得
$file = kona3getWikiFile("TestPage", FALSE);
test_assert(__LINE__, strpos($file, "TestPage") !== FALSE, "拡張子なしのファイルパス");
test_assert(__LINE__, substr($file, -4) !== ".txt", "拡張子が付いていない");

// .md 拡張子での取得
$file = kona3getWikiFile("MarkdownPage", TRUE, '.md');
test_assert(__LINE__, strpos($file, "MarkdownPage.md") !== FALSE, "Markdown拡張子のファイルパス");

// --- 階層構造のページ ---
$file = kona3getWikiFile("Category/SubPage", TRUE, '.txt');
test_assert(__LINE__, strpos($file, "Category") !== FALSE, "階層構造: Categoryを含む");
test_assert(__LINE__, strpos($file, "SubPage.txt") !== FALSE, "階層構造: SubPageを含む");
test_assert(__LINE__, strpos($file, "/") !== FALSE, "階層構造: スラッシュを含む");

// 深い階層構造
$file = kona3getWikiFile("A/B/C/D", TRUE, '.txt');
test_assert(__LINE__, strpos($file, "A") !== FALSE, "深い階層: Aを含む");
test_assert(__LINE__, strpos($file, "D.txt") !== FALSE, "深い階層: D.txtを含む");

// --- パストラバーサル対策のテスト ---

// .. を含むパス
$file = kona3getWikiFile("../../../etc/passwd", TRUE, '.txt');
test_assert(__LINE__, strpos($file, '..') === FALSE, ".. は削除される");
test_assert(__LINE__, strpos($file, KONA3_DIR_DATA) !== FALSE, "データディレクトリ内のパス");

// 複合的な攻撃パターン
$file = kona3getWikiFile("../../test/../../../etc/passwd", TRUE, '.txt');
test_assert(__LINE__, strpos($file, '..') === FALSE, "複数の .. は削除される");

// ページ名の中に .. を含む場合
$file = kona3getWikiFile("Test..Page", TRUE, '.txt');
test_assert(__LINE__, strpos($file, '..') === FALSE, "ページ名内の .. も削除される");

// --- 日本語ページ名のテスト ---
$file = kona3getWikiFile("テストページ", TRUE, '.txt');
test_assert(__LINE__, strlen($file) > 0, "日本語ページ名のファイルパス");
test_assert(__LINE__, strpos($file, KONA3_DIR_DATA) !== FALSE, "日本語ページ名でもデータディレクトリを含む");

// --- kona3getRelativePath() のテスト ---

// 通常のページ名
$path = kona3getRelativePath("TestPage");
test_eq(__LINE__, $path, "TestPage", "相対パスの取得");

// file: プレフィックス付き
$full_path = KONA3_DIR_DATA . "/TestPage.txt";
$path = kona3getRelativePath("file:" . $full_path);
test_eq(__LINE__, $path, "TestPage.txt", "file:プレフィックスの処理");

// データディレクトリのフルパス
$full_path = KONA3_DIR_DATA . "/MyPage";
$path = kona3getRelativePath($full_path);
test_eq(__LINE__, $path, "/MyPage", "フルパスから相対パスへの変換");

// 階層構造のパス
$path = kona3getRelativePath("Category/SubPage");
test_eq(__LINE__, $path, "Category/SubPage", "階層構造の相対パス");

// --- kona3getFileExt() のテスト ---

// 一般的な拡張子
$ext = kona3getFileExt("test.txt");
test_eq(__LINE__, $ext, "txt", ".txt の拡張子");

$ext = kona3getFileExt("test.md");
test_eq(__LINE__, $ext, "md", ".md の拡張子");

$ext = kona3getFileExt("image.png");
test_eq(__LINE__, $ext, "png", ".png の拡張子");

// 拡張子なし
$ext = kona3getFileExt("noext");
test_eq(__LINE__, $ext, "", "拡張子なしの場合は空文字");

// 複数のドット
$ext = kona3getFileExt("file.backup.txt");
test_eq(__LINE__, $ext, "txt", "複数ドットの場合は最後の拡張子");

// パス付きファイル名
$ext = kona3getFileExt("/path/to/file.jpg");
test_eq(__LINE__, $ext, "jpg", "パス付きファイル名の拡張子");

// --- kona3lock_save() と kona3lock_load() のテスト ---

// テストファイルのパス
$test_dir = KONA3_DIR_PRIVATE . "/test_lock";
if (!is_dir($test_dir)) {
    mkdir($test_dir, 0777, true);
}
$test_file = $test_dir . "/test_lock_" . time() . ".txt";

// 保存テスト
$content = "This is a test content.\nLine 2\nLine 3";
$result = kona3lock_save($test_file, $content);
test_eq(__LINE__, $result, TRUE, "kona3lock_save() でファイル保存成功");
test_eq(__LINE__, file_exists($test_file), TRUE, "保存後にファイルが存在する");

// 読み込みテスト
$loaded = kona3lock_load($test_file);
test_eq(__LINE__, $loaded, $content, "kona3lock_load() で保存した内容を読み込める");

// 上書き保存テスト
$new_content = "Updated content";
$result = kona3lock_save($test_file, $new_content);
test_eq(__LINE__, $result, TRUE, "上書き保存成功");

$loaded = kona3lock_load($test_file);
test_eq(__LINE__, $loaded, $new_content, "上書き後の内容を読み込める");

// 空のファイルの保存と読み込み
$empty_file = $test_dir . "/empty_" . time() . ".txt";
$result = kona3lock_save($empty_file, "");
test_eq(__LINE__, $result, TRUE, "空のファイルの保存成功");

$loaded = kona3lock_load($empty_file);
test_eq(__LINE__, $loaded, "", "空のファイルの読み込み");

// 存在しないファイルの読み込み
$nonexistent = $test_dir . "/nonexistent_" . time() . ".txt";
$loaded = kona3lock_load($nonexistent);
test_eq(__LINE__, $loaded, FALSE, "存在しないファイルはFALSEを返す");

// 日本語コンテンツの保存と読み込み
$japanese_file = $test_dir . "/japanese_" . time() . ".txt";
$japanese_content = "これは日本語のテストです。\n改行も含みます。";
$result = kona3lock_save($japanese_file, $japanese_content);
test_eq(__LINE__, $result, TRUE, "日本語コンテンツの保存成功");

$loaded = kona3lock_load($japanese_file);
test_eq(__LINE__, $loaded, $japanese_content, "日本語コンテンツの読み込み");

// 大きなファイルの保存と読み込み
$large_file = $test_dir . "/large_" . time() . ".txt";
$large_content = str_repeat("This is a line of text.\n", 1000);
$result = kona3lock_save($large_file, $large_content);
test_eq(__LINE__, $result, TRUE, "大きなファイルの保存成功");

$loaded = kona3lock_load($large_file);
test_eq(__LINE__, strlen($loaded), strlen($large_content), "大きなファイルの読み込み（サイズ確認）");
test_eq(__LINE__, $loaded, $large_content, "大きなファイルの内容確認");

// 特殊文字を含むコンテンツ
$special_file = $test_dir . "/special_" . time() . ".txt";
$special_content = "Special chars: <>&\"'\n\t\r\0";
$result = kona3lock_save($special_file, $special_content);
test_eq(__LINE__, $result, TRUE, "特殊文字を含むファイルの保存");

$loaded = kona3lock_load($special_file);
test_eq(__LINE__, $loaded, $special_content, "特殊文字を含むファイルの読み込み");

// クリーンアップ
@unlink($test_file);
@unlink($empty_file);
@unlink($japanese_file);
@unlink($large_file);
@unlink($special_file);

// --- kona3getWikiName() のテスト ---

// ファイル名からWiki名を取得
$wiki_name = kona3getWikiName(KONA3_DIR_DATA . "/TestPage.txt");
test_eq(__LINE__, $wiki_name, "TestPage", "ファイル名からWiki名を取得");

// 階層構造のファイル
$wiki_name = kona3getWikiName(KONA3_DIR_DATA . "/Category/SubPage.txt");
test_eq(__LINE__, $wiki_name, "Category/SubPage", "階層構造のWiki名");

// .md ファイル
$wiki_name = kona3getWikiName(KONA3_DIR_DATA . "/MarkdownPage.md");
test_eq(__LINE__, $wiki_name, "MarkdownPage", ".mdファイルのWiki名");

// --- エッジケースのテスト ---

// 非常に長いページ名
$long_name = str_repeat("A", 200);
$file = kona3getWikiFile($long_name, TRUE, '.txt');
test_assert(__LINE__, strlen($file) > 200, "長いページ名のファイルパス");

// スラッシュで始まるページ名
$file = kona3getWikiFile("/StartWithSlash", TRUE, '.txt');
test_assert(__LINE__, strpos($file, KONA3_DIR_DATA) !== FALSE, "スラッシュで始まるページ名");

// スラッシュで終わるページ名
$file = kona3getWikiFile("EndWithSlash/", TRUE, '.txt');
test_assert(__LINE__, strlen($file) > 0, "スラッシュで終わるページ名");

// 連続するスラッシュ
$file = kona3getWikiFile("Page//SubPage", TRUE, '.txt');
test_assert(__LINE__, strlen($file) > 0, "連続するスラッシュを含むページ名");
