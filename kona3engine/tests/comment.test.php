<?php
require_once __DIR__ . '/test_common.inc.php';
require_once __DIR__ . '/../plugins/comment.inc.php';

global $kona3conf, $FW_DB_INFO, $kona3_comment_todo_script;

$original_db_info = isset($FW_DB_INFO['main']) ? $FW_DB_INFO['main'] : null;
$tmp_db = tempnam(sys_get_temp_dir(), 'kona3_comment_');
$tmp_sql = tempnam(sys_get_temp_dir(), 'kona3_comment_sql_');
file_put_contents($tmp_sql, '/* comment plugin test */');
database_set($tmp_db, $tmp_sql);

$kona3conf['page'] = 'CommentTest';
$kona3conf['FrontPage'] = 'FrontPage';
$kona3conf['url.index'] = 'index.php';
$_SESSION = [];

$pdo = database_get();
kona3plugins_comment_init_db($pdo);
test_assert(__LINE__, db_table_exists('comment_list'), 'commentプラグイン: comment_listを作成');
test_assert(__LINE__, db_table_exists('comment_bbsid'), 'commentプラグイン: comment_bbsidを作成');

$pdo->exec('DROP TABLE comment_bbsid');
kona3plugins_comment_init_db($pdo);
test_assert(__LINE__, db_table_exists('comment_bbsid'), 'commentプラグイン: comment_bbsid欠落を復旧');

$pdo->exec('DROP TABLE comment_list');
$pdo->exec(
    'CREATE TABLE comment_list (' .
    'comment_id INTEGER PRIMARY KEY, bbs_id INTEGER DEFAULT 0, name TEXT DEFAULT "no name", ' .
    'body TEXT DEFAULT "", delkey TEXT DEFAULT "", res_id INTEGER DEFAULT 0, todo INTEGER DEFAULT 1, ' .
    'ctime INTEGER, mtime INTEGER)'
);
kona3plugins_comment_init_db($pdo);
test_assert(__LINE__, kona3plugins_comment_tableHasColumn($pdo, 'comment_list', 'user_id'), 'commentプラグイン: 既存comment_listにuser_id列を追加');

$bbs_id = kona3plugins_comment_getBbsId($pdo, 'CommentTest');
$stmt = $pdo->prepare(
    'INSERT INTO comment_list(bbs_id, name, body, delkey, ctime, mtime) VALUES(?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$bbs_id, 'Alice & Bob', "hello <world>\n>1", '', 100, 100]);

$html = kona3plugins_comment_execute([]);
test_assert(__LINE__, strpos($html, 'plugin_comment') !== FALSE, 'commentプラグイン: HTMLを生成');
test_assert(__LINE__, strpos($html, 'name=comment&amp;m=list') !== FALSE, 'commentプラグイン: コメント一覧リンクを表示');
test_assert(__LINE__, strpos($html, 'Alice &amp; Bob') !== FALSE, 'commentプラグイン: 名前をエスケープ');
test_assert(__LINE__, strpos($html, 'hello&nbsp;&lt;world&gt;<br><a href="#comment_id_1">&gt;1</a>') !== FALSE, 'commentプラグイン: 本文をエスケープして改行を表示');
test_assert(__LINE__, strpos($html, 'href="#comment_id_1"') !== FALSE, 'commentプラグイン: 返信リンクを生成');
test_assert(__LINE__, strpos($html, "href='index.php?CommentTest&amp;plugin&amp;name=comment&amp;m=del&amp;id=1'") !== FALSE, 'commentプラグイン: delリンクURLをHTMLエスケープ');
test_assert(__LINE__, strpos($html, "href='index.php?CommentTest&plugin&name=comment&m=del&id=1'") === FALSE, 'commentプラグイン: delリンクURLに未エスケープの&を出力しない');
test_assert(__LINE__, strpos($html, "<span class='todo'>todo</span>") !== FALSE, 'commentプラグイン: 未ログイン時はtodoをクリック不可にする');
test_assert(__LINE__, strpos($html, "onclick='chtodo") === FALSE, 'commentプラグイン: 未ログイン時はtodo操作を出力しない');
test_assert(__LINE__, strpos($html, 'id="kona3comment_name"') !== FALSE, 'commentプラグイン: 未ログイン時は名前入力を表示');
test_assert(__LINE__, strpos($html, 'id="kona3comment_password"') !== FALSE, 'commentプラグイン: 未ログイン時はパスワード入力を表示');
test_assert(__LINE__, preg_match('/name="edit_token" value="([^"]+)"/', $html, $m) === 1, 'commentプラグイン: edit_tokenをフォームに埋め込む');
$_POST['edit_token'] = $m[1];
test_assert(__LINE__, kona3_checkEditToken('edit_token'), 'commentプラグイン: 投稿フォームのedit_tokenを検証できる');
unset($_POST['edit_token']);

kona3login('Login User', 'login@example.com', 'normal', 1001);
$login_html = kona3plugins_comment_execute([]);
test_assert(__LINE__, strpos($login_html, 'id="kona3comment_name"') === FALSE, 'commentプラグイン: ログイン時は名前入力を省略');
test_assert(__LINE__, strpos($login_html, 'id="kona3comment_password"') === FALSE, 'commentプラグイン: ログイン時はパスワード入力を省略');
test_assert(__LINE__, strpos($login_html, 'name="name" value="Login User"') !== FALSE, 'commentプラグイン: ログインユーザー名をhiddenで送信');
test_assert(__LINE__, strpos($login_html, 'name: Login User') !== FALSE, 'commentプラグイン: ログインユーザー名を表示');
test_assert(__LINE__, strpos($login_html, "onclick='chtodo") !== FALSE, 'commentプラグイン: ログイン時はtodo操作を表示');
$_POST = [
    'edit_token' => kona3_getEditToken('edit_token'),
    'bbs_id' => $bbs_id,
    'body' => 'login comment',
];
kona3plugins_comment_action_write('CommentTest');
$row = $pdo->query("SELECT * FROM comment_list WHERE body='login comment'")->fetch();
test_assert(__LINE__, $row !== FALSE, 'commentプラグイン: ログイン時はname/password省略で投稿できる');
test_eq(__LINE__, $row['name'], 'Login User', 'commentプラグイン: ログイン投稿はユーザー名を保存');
test_eq(__LINE__, intval($row['user_id']), 1001, 'commentプラグイン: ログイン投稿はuser_idを保存');
test_assert(__LINE__, kona3plugins_comment_canDeleteAsLoginUser($row), 'commentプラグイン: ログインユーザーは自分のコメントを削除できる');
$del_form = kona3plugins_comment_renderDeleteForm($row['comment_id'], kona3_getEditToken('edit_token'), FALSE);
test_assert(__LINE__, strpos($del_form, lang('Really?')) !== FALSE, 'commentプラグイン: 削除前に確認を表示');
test_assert(__LINE__, strpos($del_form, "type='password'") === FALSE, 'commentプラグイン: 自分のコメント削除確認ではパスワード入力を省略');
$_POST = [
    'edit_token' => kona3_getEditToken('edit_token'),
    'id' => $row['comment_id'],
];
$_REQUEST = ['m' => 'del2', 'id' => $row['comment_id']];
kona3plugins_comment_action();
$deleted_row = kona3plugins_comment_getComment($pdo, $row['comment_id']);
test_assert(__LINE__, $deleted_row === FALSE, 'commentプラグイン: 自分のコメントは確認後にパスワードなしで削除');

$stmt->execute([$bbs_id, 'Other User', 'other comment', '', 100, 100]);
$other_id = $pdo->lastInsertId();
$pdo->prepare('UPDATE comment_list SET user_id=? WHERE comment_id=?')->execute([2002, $other_id]);
$other_row = kona3plugins_comment_getComment($pdo, $other_id);
test_assert(__LINE__, !kona3plugins_comment_canDeleteAsLoginUser($other_row), 'commentプラグイン: 他人のコメントは一般ログインユーザーでは削除不可');
$_REQUEST = [];
$_POST = [];
kona3logout();

kona3login('Admin User', 'admin@example.com', 'admin', 1);
test_assert(__LINE__, kona3plugins_comment_canDeleteAsLoginUser($other_row), 'commentプラグイン: 管理ユーザーは全コメントを削除できる');
$admin_del_form = kona3plugins_comment_renderDeleteForm($other_id, kona3_getEditToken('edit_token'), FALSE);
test_assert(__LINE__, strpos($admin_del_form, "type='password'") === FALSE, 'commentプラグイン: 管理ユーザーの削除確認ではパスワード入力を省略');
$_POST = [
    'edit_token' => kona3_getEditToken('edit_token'),
    'id' => $other_id,
];
$_REQUEST = ['m' => 'del2', 'id' => $other_id];
kona3plugins_comment_action();
$admin_deleted_row = kona3plugins_comment_getComment($pdo, $other_id);
test_assert(__LINE__, $admin_deleted_row === FALSE, 'commentプラグイン: 管理ユーザーは確認後にパスワードなしで削除');
$_REQUEST = [];
$_POST = [];
kona3logout();

$guest_del_form = kona3plugins_comment_renderDeleteForm(1, kona3_getEditToken('edit_token'), TRUE);
test_assert(__LINE__, strpos($guest_del_form, "type='password'") !== FALSE, 'commentプラグイン: 未ログイン削除確認ではパスワード入力を表示');

$guest_all_html = kona3plugins_comment_execute(['type=all']);
test_assert(__LINE__, strpos($guest_all_html, lang('Please login.')) !== FALSE, 'commentプラグイン: 未ログイン時は全コメント一覧を表示しない');
test_assert(__LINE__, strpos($guest_all_html, 'Comments (type=all)') === FALSE, 'commentプラグイン: 未ログイン時は全コメント一覧本体を隠す');

$login_only_html = kona3plugins_comment_execute(['loginOnly']);
test_assert(__LINE__, strpos($login_only_html, lang('Please login.')) !== FALSE, 'commentプラグイン: loginOnly未ログイン時はログイン案内を表示');
test_assert(__LINE__, strpos($login_only_html, 'href="index.php?CommentTest&amp;login"') !== FALSE, 'commentプラグイン: loginOnly未ログイン時はログインリンクを表示');
test_assert(__LINE__, strpos($login_only_html, 'id="edit_token"') !== FALSE, 'commentプラグイン: loginOnly未ログイン時もtodo用edit_tokenを埋め込む');
test_assert(__LINE__, strpos($login_only_html, 'id="kona3comment_body"') === FALSE, 'commentプラグイン: loginOnly未ログイン時は投稿フォームを隠す');
$login_only_eq_html = kona3plugins_comment_execute(['loginOnly=1']);
test_assert(__LINE__, strpos($login_only_eq_html, lang('Please login.')) !== FALSE, 'commentプラグイン: loginOnly=1未ログイン時はログイン案内を表示');

$kona3_comment_todo_script = null;
$script1 = _todo_script();
$script2 = _todo_script();
test_assert(__LINE__, strpos($script1, 'function chtodo') !== FALSE, 'commentプラグイン: todoスクリプトを生成');
test_assert(__LINE__, strpos($script1, 'event.preventDefault();') !== FALSE, 'commentプラグイン: todoリンクの既定動作を止める');
test_assert(__LINE__, strpos($script1, 'var comment_api = "index.php?CommentTest\u0026plugin\u0026name=comment";') !== FALSE, 'commentプラグイン: todo API URLをJavaScript文字列として生成');
test_assert(__LINE__, strpos($script1, '&amp;plugin') === FALSE, 'commentプラグイン: todo API URLをHTMLエスケープしない');
test_eq(__LINE__, $script2, '', 'commentプラグイン: todoスクリプトは重複出力しない');

$kona3_comment_todo_script = null;
$admin_html = _renderCommentAdminPage($pdo, 'all');
test_assert(__LINE__, strpos($admin_html, 'Comments (type=all)') !== FALSE, 'commentプラグイン: 全コメント管理ページのタイトルを表示');
test_assert(__LINE__, strpos($admin_html, 'name=comment&amp;m=list&amp;type=all') !== FALSE, 'commentプラグイン: 全コメント管理ページにallリンクを表示');
test_assert(__LINE__, strpos($admin_html, 'name=comment&amp;m=list&amp;type=todo') !== FALSE, 'commentプラグイン: 全コメント管理ページにtodoリンクを表示');
test_assert(__LINE__, strpos($admin_html, 'name="edit_token"') !== FALSE || strpos($admin_html, "name='edit_token'") !== FALSE, 'commentプラグイン: 全コメント管理ページにedit_tokenを埋め込む');
test_assert(__LINE__, strpos($admin_html, 'Alice &amp; Bob') !== FALSE, 'commentプラグイン: 全コメント管理ページにコメントを表示');
test_assert(__LINE__, strpos($admin_html, "href='index.php?CommentTest&amp;show#CommentBox'") !== FALSE, 'commentプラグイン: 全コメント管理ページのページリンクURLをHTMLエスケープ');
test_assert(__LINE__, strpos($admin_html, "href='index.php?CommentTest&show#CommentBox'") === FALSE, 'commentプラグイン: 全コメント管理ページのページリンクURLに未エスケープの&を出力しない');

if ($original_db_info === null) {
    unset($FW_DB_INFO['main']);
} else {
    $FW_DB_INFO['main'] = $original_db_info;
}
@unlink($tmp_db);
@unlink($tmp_sql);
