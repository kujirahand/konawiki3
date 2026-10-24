<?php
/**
 * Test for issue #16: freezeプラグイン
 *
 * ページ先頭に「#freeze」と書くと凍結され、書き換えには管理者が必要になる。
 * - kona3isFrozenPageText(): 本文テキストの凍結判定
 * - kona3pageIsFrozen(): 保存済みページの凍結判定
 * - kona3edit_frozenError(): 権限に応じた保存可否
 * - freezeプラグインの描画 (KonaWiki記法 / Markdown)
 */
require_once __DIR__ . '/test_common.inc.php';
require_once __DIR__ . '/../action/edit.inc.php';
require_once __DIR__ . '/../kona3parser_md.inc.php';

global $kona3conf;
$kona3conf['page'] = 'FrontPage';
$kona3conf['static_mode'] = FALSE;

echo "=== Freeze Test (issue #16) ===\n";

// -----------------------------------------------
// kona3isFrozenPageText
// -----------------------------------------------
echo "Test 1: frozen text detection\n";
test_eq(__LINE__, kona3isFrozenPageText("#freeze\n本文"), TRUE, 'freeze: 先頭行の #freeze で凍結');
test_eq(__LINE__, kona3isFrozenPageText("#freeze"), TRUE, 'freeze: #freeze だけの本文も凍結');
test_eq(__LINE__, kona3isFrozenPageText("#freeze\r\n本文"), TRUE, 'freeze: CRLFでも凍結');
test_eq(__LINE__, kona3isFrozenPageText("\xEF\xBB\xBF#freeze\n本文"), TRUE, 'freeze: BOM付きでも凍結');
test_eq(__LINE__, kona3isFrozenPageText("#freeze(メモ)\n本文"), TRUE, 'freeze: 引数付き #freeze(...) も凍結');
test_eq(__LINE__, kona3isFrozenPageText("#freeze;\n本文"), TRUE, 'freeze: 末尾の ; を許容');
test_eq(__LINE__, kona3isFrozenPageText("#freeze \n本文"), TRUE, 'freeze: 末尾の空白を許容');
test_eq(__LINE__, kona3isFrozenPageText("!!freeze\n本文"), TRUE, 'freeze: Markdownの !!freeze も凍結');
test_eq(__LINE__, kona3isFrozenPageText("!!freeze()\n本文"), TRUE, 'freeze: !!freeze() も凍結');
test_eq(__LINE__, kona3isFrozenPageText("♪freeze\n本文"), TRUE, 'freeze: ♪freeze も凍結');

echo "Test 2: non-frozen text\n";
test_eq(__LINE__, kona3isFrozenPageText("本文\n#freeze\n"), FALSE, 'freeze: 途中行の #freeze は凍結ではない');
test_eq(__LINE__, kona3isFrozenPageText("\n#freeze\n本文"), FALSE, 'freeze: 先頭が空行なら凍結ではない');
test_eq(__LINE__, kona3isFrozenPageText("#freezeXXX\n本文"), FALSE, 'freeze: #freezeXXX は別プラグイン名');
test_eq(__LINE__, kona3isFrozenPageText("# freezer\n本文"), FALSE, 'freeze: # freezer は見出しであって凍結ではない');
test_eq(__LINE__, kona3isFrozenPageText("#Freeze\n本文"), FALSE, 'freeze: 大文字の #Freeze は凍結ではない');
test_eq(__LINE__, kona3isFrozenPageText(""), FALSE, 'freeze: 空本文は凍結ではない');
test_eq(__LINE__, kona3isFrozenPageText("普通の本文"), FALSE, 'freeze: 通常本文は凍結ではない');

// -----------------------------------------------
// kona3pageIsFrozen (サンドボックスに実ファイルを作る)
// -----------------------------------------------
echo "Test 3: page file detection\n";
$frozen_page = 'FreezeTestFrozen';
$normal_page = 'FreezeTestNormal';
file_put_contents(KONA3_DIR_DATA . "/{$frozen_page}.txt", "#freeze\n凍結された本文");
file_put_contents(KONA3_DIR_DATA . "/{$normal_page}.md", "# 見出し\n通常の本文");
test_eq(__LINE__, kona3pageIsFrozen($frozen_page), TRUE, 'freeze: 保存済みの凍結ページを検出 (.txt)');
test_eq(__LINE__, kona3pageIsFrozen($normal_page), FALSE, 'freeze: 通常ページは凍結ではない (.md)');
test_eq(__LINE__, kona3pageIsFrozen('FreezeTestNoSuchPage'), FALSE, 'freeze: 存在しないページは凍結ではない');

// -----------------------------------------------
// kona3edit_frozenError (権限ごとの保存可否)
// -----------------------------------------------
echo "Test 4: save permission by role\n";
// 未ログイン
$_SESSION = [];
test_ne(__LINE__, kona3edit_frozenError($frozen_page, "書き換えた本文"), '', 'freeze: 未ログインは凍結ページを保存できない');
test_ne(__LINE__, kona3edit_frozenError($normal_page, "#freeze\n新たに凍結"), '', 'freeze: 未ログインは新たに凍結できない');
test_eq(__LINE__, kona3edit_frozenError($normal_page, "通常の書き換え"), '', 'freeze: 未ログインでも通常ページの判定は通る (ログイン自体は別途チェック)');
// 一般ユーザー
$_SESSION = [];
kona3login('freeze-tester', 'freeze@example.com', 'normal', 0);
test_ne(__LINE__, kona3edit_frozenError($frozen_page, "書き換えた本文"), '', 'freeze: 一般ユーザーは凍結ページを保存できない');
test_ne(__LINE__, kona3edit_frozenError($frozen_page, ""), '', 'freeze: 一般ユーザーは凍結ページを削除 (空保存) できない');
test_ne(__LINE__, kona3edit_frozenError($normal_page, "#freeze\n新たに凍結"), '', 'freeze: 一般ユーザーは新たに凍結できない');
test_eq(__LINE__, kona3edit_frozenError($normal_page, "通常の書き換え"), '', 'freeze: 一般ユーザーは通常ページを保存できる');
test_eq(__LINE__, kona3edit_frozenError($normal_page, "本文\n#freeze\n"), '', 'freeze: 途中行の #freeze では凍結扱いにならない');
// 管理者
$_SESSION = [];
kona3login('freeze-admin', 'admin@example.com', 'admin', 0);
test_eq(__LINE__, kona3edit_frozenError($frozen_page, "書き換えた本文"), '', 'freeze: 管理者は凍結ページを保存できる');
test_eq(__LINE__, kona3edit_frozenError($frozen_page, "凍結を解除した本文"), '', 'freeze: 管理者は凍結を解除できる');
test_eq(__LINE__, kona3edit_frozenError($normal_page, "#freeze\n新たに凍結"), '', 'freeze: 管理者は新たに凍結できる');
$_SESSION = [];

// -----------------------------------------------
// kona3_trywrite を通した保存チェック (結合レベル)
// -----------------------------------------------
echo "Test 4b: trywrite blocks non-admin saves (ajax)\n";
// 凍結ページへの保存: 一般ユーザーは弾かれ、ファイルが変わらない
$_SESSION = [];
kona3login('freeze-tester', 'freeze@example.com', 'normal', 0);
$GLOBALS['page'] = $frozen_page;
$kona3conf['page'] = $frozen_page;
$cur_txt = file_get_contents(KONA3_DIR_DATA . "/{$frozen_page}.txt");
$cur_hash = kona3getPageHash($cur_txt);
$_REQUEST = $_POST = [
    'edit_txt' => "#freeze\n書き換えた本文",
    'a_hash' => $cur_hash,
    'tags' => '',
    'edit_ext' => 'txt',
    'page_mode' => 'KonaNotation',
];
$tmp_txt = $cur_txt;
$tmp_hash = $cur_hash;
ob_start();
kona3_trywrite($tmp_txt, $tmp_hash, 'ajax', $tmp_result);
$out = ob_get_clean();
$json = json_decode($out, TRUE);
test_eq(__LINE__, isset($json['result']) ? $json['result'] : '', 'ng', 'trywrite: 凍結ページへの保存は ng');
test_eq(__LINE__, isset($json['code']) ? $json['code'] : '', 'frozen', 'trywrite: エラーコードは frozen');
test_eq(__LINE__, file_get_contents(KONA3_DIR_DATA . "/{$frozen_page}.txt"), $cur_txt, 'trywrite: 凍結ページのファイルは変わらない');
// 新規凍結の試み: 一般ユーザーは弾かれ、ファイルが変わらない
$GLOBALS['page'] = $normal_page;
$kona3conf['page'] = $normal_page;
$cur_txt2 = file_get_contents(KONA3_DIR_DATA . "/{$normal_page}.md");
$cur_hash2 = kona3getPageHash($cur_txt2);
$_REQUEST = $_POST = [
    'edit_txt' => "#freeze\n新たに凍結する本文",
    'a_hash' => $cur_hash2,
    'tags' => '',
    'edit_ext' => 'md',
    'page_mode' => 'Markdown',
];
$tmp_txt = $cur_txt2;
$tmp_hash = $cur_hash2;
ob_start();
kona3_trywrite($tmp_txt, $tmp_hash, 'ajax', $tmp_result);
$out = ob_get_clean();
$json = json_decode($out, TRUE);
test_eq(__LINE__, isset($json['code']) ? $json['code'] : '', 'frozen', 'trywrite: 新規凍結の試みもコード frozen で弾かれる');
test_eq(__LINE__, file_get_contents(KONA3_DIR_DATA . "/{$normal_page}.md"), $cur_txt2, 'trywrite: 新規凍結を試みてもファイルは変わらない');
$_SESSION = [];
$_REQUEST = $_POST = [];

// 後片付け
@unlink(KONA3_DIR_DATA . "/{$frozen_page}.txt");
@unlink(KONA3_DIR_DATA . "/{$normal_page}.md");

// -----------------------------------------------
// プラグインの描画
// -----------------------------------------------
echo "Test 5: plugin rendering\n";
include_once __DIR__ . '/../plugins/freeze.inc.php';
test_eq(__LINE__, kona3plugins_freeze_execute([]), '<!-- #freeze -->', 'freeze: プラグインはHTMLコメントだけを返す');

$html = konawiki_parser_convert("#freeze\n\nhello");
test_assert(__LINE__, strpos($html, '<!-- #freeze -->') !== FALSE, 'freeze: KonaWiki記法で #freeze がプラグインとして描画される');
test_assert(__LINE__, strpos($html, '[No Plugin:freeze]') === FALSE, 'freeze: KonaWiki記法でプラグインエラーにならない');
test_assert(__LINE__, strpos($html, 'hello') !== FALSE, 'freeze: 後続の本文も描画される');

$html_md = kona3markdown_parser_convert("#freeze\n\nhello");
test_assert(__LINE__, strpos($html_md, '<!-- #freeze -->') !== FALSE, 'freeze: Markdownで #freeze がプラグインとして描画される');
test_assert(__LINE__, strpos($html_md, '<h1') === FALSE, 'freeze: Markdownで #freeze が見出しにならない');
test_assert(__LINE__, strpos($html_md, 'hello') !== FALSE, 'freeze: Markdownでも後続の本文が描画される');

$html_md2 = kona3markdown_parser_convert("!!freeze\n\nhello");
test_assert(__LINE__, strpos($html_md2, '<!-- #freeze -->') !== FALSE, 'freeze: Markdownの !!freeze もプラグインとして描画される');

$html_h = kona3markdown_parser_convert("# Title\n\nhello");
test_assert(__LINE__, strpos($html_h, '<h1') !== FALSE, 'freeze: Markdownの通常の見出しは今までどおり見出しになる');

// Markdown では #freeze のプラグイン解釈は先頭行だけ (agyレビュー指摘で修正)。
// 2行目以降の #freeze は見出しとして残り、引用・言及できる。
$html_mid = kona3markdown_parser_convert("# Title\n\n#freeze\n\nhello");
test_assert(__LINE__, strpos($html_mid, '<!-- #freeze -->') === FALSE, 'freeze: Markdownの途中行の #freeze はプラグインにならない');
test_assert(__LINE__, strpos($html_mid, '>freeze') !== FALSE, 'freeze: Markdownの途中行の #freeze は見出しとして表示される');
$html_blank = kona3markdown_parser_convert("\n#freeze\n\nhello");
test_assert(__LINE__, strpos($html_blank, '<!-- #freeze -->') === FALSE, 'freeze: Markdownで先頭が空行なら #freeze はプラグインにならない');
