<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/action/update_page_history.inc.php';

// #46: 編集履歴が見られなくなっている
// - DB に履歴が残っているのに編集画面の履歴一覧に出ないケース
// - GET リンクの編集トークンが照合されず、履歴を操作できないケース
// を回帰テストする。

$tok = 'edit_token';

// --- GET で渡された編集トークンも照合できる (POST 優先) ---
$savedSession = isset($_SESSION) ? $_SESSION : [];
$savedPost = $_POST;
$savedGet = $_GET;
$_SESSION = [];
$_POST = [];
$_GET = [];
$getToken = kona3_getEditToken($tok, TRUE);
test_eq(__LINE__, kona3_checkEditTokenReason($tok), 'no_post_token', 'history token: POST/GET どちらもなければ no_post_token');
// 履歴の表示/削除リンクは GET で edit_token を渡す
$_GET = [$tok => $getToken];
test_eq(__LINE__, kona3_checkEditTokenReason($tok), 'ok', 'history token: GET の edit_token を照合できる (#46)');
test_assert(__LINE__, kona3_checkEditToken($tok), 'history token: GET の edit_token で checkEditToken が通る');
$_GET = [$tok => 'wrong-token'];
test_eq(__LINE__, kona3_checkEditTokenReason($tok), 'mismatch', 'history token: GET の誤った edit_token は mismatch');
$_GET = [$tok => [$getToken]];
test_eq(__LINE__, kona3_checkEditTokenReason($tok), 'no_post_token', 'history token: GET の配列トークンは未送信扱い');
// POST が送られた場合は POST を優先する (フォーム送信の従来動作)
$_GET = [$tok => $getToken];
$_POST = [$tok => 'wrong-token'];
test_eq(__LINE__, kona3_checkEditTokenReason($tok), 'mismatch', 'history token: POST がある場合は POST を優先する');
$_POST = [$tok => $getToken];
$_GET = [$tok => 'wrong-token'];
test_eq(__LINE__, kona3_checkEditTokenReason($tok), 'ok', 'history token: POST の正しいトークンが優先される');
$_SESSION = $savedSession;
$_POST = $savedPost;
$_GET = $savedGet;

// --- 通常の編集履歴は一覧取得できる ---
$page = "HistoryView_" . time();
kona3db_writePage($page, "history body v1", 0);
kona3db_writePage($page, "history body v2", 424242);
$history = kona3db_getPageHistory($page, 'TOKEN46');
test_assert(__LINE__, is_array($history), 'history: 一覧は配列');
test_eq(__LINE__, count($history), 2, 'history: 保存した2件の履歴が取得できる');
test_eq(__LINE__, $history[0]['body'], 'history body v2', 'history: 新しい履歴が先頭');
test_assert(__LINE__, strpos($history[0]['link'], 'q=history') !== FALSE, 'history: 表示リンクに q=history が含まれる');
test_assert(__LINE__, strpos($history[0]['link'], 'history_id=' . $history[0]['history_id']) !== FALSE, 'history: 表示リンクに history_id が含まれる');
test_assert(__LINE__, strpos($history[0]['link'], 'edit_token=TOKEN46') !== FALSE, 'history: 表示リンクに edit_token が含まれる');
test_assert(__LINE__, strpos($history[0]['delete_link'], 'cmd=history_delete') !== FALSE, 'history: 削除リンクに cmd=history_delete が含まれる');
test_assert(__LINE__, strpos($history[0]['delete_link'], 'edit_token=TOKEN46') !== FALSE, 'history: 削除リンクに edit_token が含まれる');
$byId = kona3db_getPageHistoryById($history[1]['history_id']);
test_eq(__LINE__, $byId['body'], 'history body v1', 'history: history_id で過去の本文を取得できる');

// 履歴のない (存在しない) ページは空配列
test_eq(__LINE__, kona3db_getPageHistory("HistoryNone_" . time(), 'TOKEN46'), [], 'history: 履歴がないページは空配列');

// --- レガシーIDで登録された履歴も一覧に出る ---
$legacyPage = "HistoryLegacy_" . time();
kona3db_writePage($legacyPage, "legacy page db body", 0);
$dbId = kona3db_getPageId($legacyPage, FALSE);
test_assert(__LINE__, $dbId > 0, 'history legacy: DB の page_id が作られる');
// pages に同名で登録されるより大きな未使用 ID をレガシーIDに見立てる
$legacyId = 880000 + (time() % 10000);
$owner = db_get1("SELECT page_id FROM pages WHERE page_id=?", [$legacyId]);
test_assert(__LINE__, !$owner, 'history legacy: レガシーID候補が未使用');
db_exec(
    "INSERT INTO page_history (page_id, user_id, body, hash, mtime) VALUES (?, ?, ?, ?, ?)",
    [$legacyId, 0, "legacy page legacy body", kona3getHash("legacy page legacy body"), time() - 3600]
);
$legacyRowId = db_get1(
    "SELECT history_id FROM page_history WHERE page_id=?",
    [$legacyId]
)['history_id'];

$legacyFile = KONA3_PAGE_ID_JSON;
$legacyBackup = file_exists($legacyFile) ? file_get_contents($legacyFile) : null;
file_put_contents($legacyFile, json_encode([$legacyPage => $legacyId]));
kona3db_loadLegacyPageIds(TRUE);
test_eq(__LINE__, kona3db_getPageIdsForHistory($legacyPage), [$dbId, $legacyId], 'history legacy: DB ID とレガシーIDの両方が候補になる');
$merged = kona3db_getPageHistory($legacyPage, 'TOKEN46');
$bodies = array_map(function ($v) { return $v['body']; }, $merged);
test_assert(__LINE__, in_array('legacy page db body', $bodies, TRUE), 'history legacy: DB ID の履歴が見える');
test_assert(__LINE__, in_array('legacy page legacy body', $bodies, TRUE), 'history legacy: レガシーID の履歴も見える (#46)');
test_eq(__LINE__, $merged[0]['history_id'], $legacyRowId, 'history legacy: 統合した一覧も history_id 降順');
// 後片付け (他のテストへレガシーJSONを持ち越さない)
db_exec("DELETE FROM page_history WHERE page_id=?", [$legacyId]);
if ($legacyBackup !== null) {
    file_put_contents($legacyFile, $legacyBackup);
} else {
    @unlink($legacyFile);
}
kona3db_loadLegacyPageIds(TRUE);
test_eq(__LINE__, kona3db_getPageIdsForHistory($legacyPage), [$dbId], 'history legacy: 後片付け後は DB ID のみが候補');

// レガシーIDが別ページに割り当て済みの場合は、その履歴を混ぜない
$otherPage = "HistoryOther_" . time();
kona3db_writePage($otherPage, "other page body", 0);
$otherId = kona3db_getPageId($otherPage, FALSE);
file_put_contents($legacyFile, json_encode([$legacyPage => $otherId]));
kona3db_loadLegacyPageIds(TRUE);
test_eq(__LINE__, kona3db_getPageIdsForHistory($legacyPage), [$dbId], 'history legacy: 別ページ所有の ID は候補にしない');
if ($legacyBackup !== null) {
    file_put_contents($legacyFile, $legacyBackup);
} else {
    @unlink($legacyFile);
}
kona3db_loadLegacyPageIds(TRUE);

// --- update_page_history は pages テーブルの ID で登録する ---
$upPage = "HistoryUpdate_" . time();
$upId = kona3_update_page_history_get_page_id($upPage, time());
test_assert(__LINE__, $upId > 0, 'update_page_history: page_id を取得できる');
test_eq(__LINE__, $upId, kona3db_getPageId($upPage, FALSE), 'update_page_history: ID が pages テーブルと一致する (#46)');
test_assert(__LINE__, !file_exists($legacyFile) || $legacyBackup !== null, 'update_page_history: レガシーJSONを新規作成しない');

// 対象ファイルの列挙は .txt と .md の両方を含む
$upDir = KONA3_DIR_DATA . '/HistoryFiles_' . time();
@mkdir($upDir . '/sub', 0777, TRUE);
file_put_contents($upDir . '/A.txt', 'a');
file_put_contents($upDir . '/B.md', 'b');
file_put_contents($upDir . '/C.json', 'c');
file_put_contents($upDir . '/sub/D.md', 'd');
$found = array_map('basename', kona3_update_page_history_files($upDir));
sort($found);
test_eq(__LINE__, $found, ['A.txt', 'B.md', 'D.md'], 'update_page_history: .txt/.md を再帰的に列挙する');
// 登録処理 (.md も登録でき、編集画面の履歴に出る)
ob_start();
$registered = kona3_update_page_history_process_file($upDir . '/B.md', KONA3_DIR_DATA);
ob_end_clean();
test_assert(__LINE__, $registered, 'update_page_history: .md ファイルを履歴に登録できる');
$upPageName = basename($upDir) . '/B';
$upHistory = kona3db_getPageHistory($upPageName, 'TOKEN46');
test_eq(__LINE__, count($upHistory), 1, 'update_page_history: 登録した .md の履歴が一覧に出る');
test_eq(__LINE__, $upHistory[0]['body'], 'b', 'update_page_history: 登録した本文が一致する');
@unlink($upDir . '/A.txt');
@unlink($upDir . '/B.md');
@unlink($upDir . '/C.json');
@unlink($upDir . '/sub/D.md');
@rmdir($upDir . '/sub');
@rmdir($upDir);
