<?php
require_once __DIR__ . '/test_common.inc.php';

// --- ページ名・タイトル名の文字数制限 (issue #133) ---
// 確認した従来の仕様: ページ名には一切の長さ制限がなく、
// ファイル名の上限 (255バイト) を超える名前は保存時に分かりにくい
// エラーになっていた。ここでは導入した制限を検証する。
//   (1) 全体: 200文字 (設定 page_name_max_length、0で無制限)
//   (2) 各階層: 保存されるファイル名として245バイト以下 (設定によらない)

global $kona3conf;
$saved_enc = $kona3conf['enc_pagename'] ?? null;
$saved_max_exists = isset($kona3conf['page_name_max_length']);
$saved_max = $saved_max_exists ? $kona3conf['page_name_max_length'] : null;
$kona3conf['enc_pagename'] = FALSE;
unset($kona3conf['page_name_max_length']);

// 設定の既定値
test_eq(__LINE__, kona3conf_getConfigItems()['Options']['page_name_max_length']['default'], 200, "page_name_max_length の既定値は200");
test_eq(__LINE__, kona3getPageNameMaxLength(), 200, "未設定時の上限は200文字");

// 通常のページ名
test_eq(__LINE__, kona3validatePageName('FrontPage'), '', "通常のページ名はOK");
test_eq(__LINE__, kona3validatePageName('カテゴリ/子ページ'), '', "階層ページ名はOK");
test_assert(__LINE__, kona3isValidPageName('FrontPage'), "kona3isValidPageName: 通常名はTRUE");

// 空のページ名
test_ne(__LINE__, kona3validatePageName(''), '', "空のページ名はNG");
test_assert(__LINE__, !kona3isValidPageName(''), "kona3isValidPageName: 空はFALSE");

// (1) 全体の文字数: 境界値 (ASCII、各階層のバイト数には掛からない長さ)
test_eq(__LINE__, kona3validatePageName(str_repeat('A', 200)), '', "200文字はOK");
test_ne(__LINE__, kona3validatePageName(str_repeat('A', 201)), '', "201文字はNG");

// (1) 全体の文字数: 日本語はバイト数ではなく文字数で数える
test_eq(__LINE__, kona3validatePageName(str_repeat('あ', 66)), '', "日本語66文字 (198バイト) はOK");
test_eq(__LINE__, kona3validatePageName(str_repeat('あ', 67)), '', "日本語67文字 (201バイト) は文字数・バイト数とも上限以内");

// (2) 各階層のバイト数: 日本語1文字=3バイトなので81文字=243バイトはOK、82文字=246バイトはNG
test_eq(__LINE__, kona3validatePageName(str_repeat('あ', 81)), '', "日本語81文字 (243バイト) はOK");
test_ne(__LINE__, kona3validatePageName(str_repeat('あ', 82)), '', "日本語82文字 (246バイト) はファイル名の上限を超えるのでNG");

// (2) 階層ごとに判定すること: 1階層でも長ければNG、短い階層の組み合わせはOK
test_ne(__LINE__, kona3validatePageName('短い/' . str_repeat('あ', 82)), '', "長い階層を含むページ名はNG");
$kona3conf['page_name_max_length'] = 0;
test_eq(__LINE__, kona3getPageNameMaxLength(), 0, "設定0で文字数制限は無制限になる");
$hier = str_repeat('A', 100) . '/' . str_repeat('B', 100) . '/' . str_repeat('C', 100);
test_eq(__LINE__, kona3validatePageName($hier), '', "文字数制限なし+各階層100バイトなら合計302文字でもOK");
test_ne(__LINE__, kona3validatePageName(str_repeat('A', 246)), '', "文字数制限なしでも1階層246バイトはNG");
test_eq(__LINE__, kona3validatePageName(str_repeat('A', 245)), '', "1階層245バイトはOK");

// 設定で上限を変えられること
$kona3conf['page_name_max_length'] = 10;
test_eq(__LINE__, kona3getPageNameMaxLength(), 10, "設定で上限を10文字に変更できる");
test_eq(__LINE__, kona3validatePageName(str_repeat('A', 10)), '', "カスタム上限10文字はOK");
test_ne(__LINE__, kona3validatePageName(str_repeat('A', 11)), '', "カスタム上限11文字はNG");
$kona3conf['page_name_max_length'] = 'abc';
test_eq(__LINE__, kona3getPageNameMaxLength(), 200, "数値でない設定値は既定値200として扱う");
$kona3conf['page_name_max_length'] = -1;
test_eq(__LINE__, kona3getPageNameMaxLength(), 0, "負の設定値は無制限 (0) として扱う");

// (2) enc_pagename 有効時はエンコード後の名前で判定すること
//     日本語1文字は urlencode で %XX%XX%XX の9バイトになる
unset($kona3conf['page_name_max_length']);
$kona3conf['enc_pagename'] = TRUE;
test_eq(__LINE__, kona3validatePageName(str_repeat('あ', 27)), '', "エンコード時: 日本語27文字 (243バイト) はOK");
test_ne(__LINE__, kona3validatePageName(str_repeat('あ', 28)), '', "エンコード時: 日本語28文字 (252バイト) はNG");

// 後始末 (他のテストへ影響させない)
$kona3conf['enc_pagename'] = $saved_enc;
if ($saved_max_exists) {
    $kona3conf['page_name_max_length'] = $saved_max;
} else {
    unset($kona3conf['page_name_max_length']);
}
