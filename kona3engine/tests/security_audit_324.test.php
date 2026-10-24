<?php
require_once __DIR__ . '/test_common.inc.php';

// --- セキュリティ監査 (#324) の回帰テスト ---
// プラグイン (comment / qrcode / color_talk / include / file / filecode) と
// アクション (search / data / attach / emailLogs / update_page_history /
// versionup / user / plugin / skin / resource) で見つけた問題の再発防止。

require_once dirname(__DIR__) . '/plugins/qrcode.inc.php';
require_once dirname(__DIR__) . '/plugins/color_talk.inc.php';
require_once dirname(__DIR__) . '/plugins/comment.inc.php';
require_once dirname(__DIR__) . '/plugins/include.inc.php';
require_once dirname(__DIR__) . '/plugins/file.inc.php';
require_once dirname(__DIR__) . '/plugins/filecode.inc.php';
require_once dirname(__DIR__) . '/action/skin.inc.php';

global $kona3conf;

// --- qrcode: 引数が <script> 内のJS文字列を抜け出せないこと ---
$qr_html = kona3plugins_qrcode_execute(['x</script><svg onload=alert`1`//', 'size=64']);
test_assert(__LINE__, strpos($qr_html, 'x</script>') === FALSE, 'qrcode: </script> がそのまま出力されない');
test_assert(__LINE__, strpos($qr_html, '<svg onload') === FALSE, 'qrcode: 注入したsvgが出力されない');
test_assert(__LINE__, strpos($qr_html, '\u003C') !== FALSE, 'qrcode: テキストはJSONとしてエンコードされる');
$qr_html2 = kona3plugins_qrcode_execute(['https://example.com/', 'size=32']);
test_assert(__LINE__, strpos($qr_html2, '"https://example.com/"') !== FALSE, 'qrcode: 通常のURLはJSON文字列として埋め込まれる');
test_assert(__LINE__, strpos($qr_html2, 'width: 32') !== FALSE, 'qrcode: size指定が効く');

// --- color_talk: 色指定が style 属性を抜け出せないこと ---
test_assert(__LINE__, kona3plugins_color_talk_isValidColor('red'), 'color_talk: 色名は有効');
test_assert(__LINE__, kona3plugins_color_talk_isValidColor('#fff'), 'color_talk: #fffは有効');
test_assert(__LINE__, kona3plugins_color_talk_isValidColor('#FF0000'), 'color_talk: #FF0000は有効');
test_assert(__LINE__, kona3plugins_color_talk_isValidColor('rgb(1, 2, 3)'), 'color_talk: rgb()は有効');
test_assert(__LINE__, !kona3plugins_color_talk_isValidColor("'><svg onload='x"), 'color_talk: 属性を抜ける値は無効');
test_assert(__LINE__, !kona3plugins_color_talk_isValidColor('red; background:url(x)'), 'color_talk: ; を含む値は無効');
test_assert(__LINE__, !kona3plugins_color_talk_isValidColor(''), 'color_talk: 空は無効');
$ct_html = kona3plugins_color_talk_execute(["S='><svg onload='alert`1`", 'S: hello']);
test_assert(__LINE__, strpos($ct_html, '<svg') === FALSE, 'color_talk: 注入したsvgが出力されない');
test_assert(__LINE__, strpos($ct_html, "style='color:") === FALSE, 'color_talk: 不正な色はstyleに使われない');
$ct_html2 = kona3plugins_color_talk_execute(['S=red', 'S: hello']);
test_assert(__LINE__, strpos($ct_html2, "style='color: red;'") !== FALSE, 'color_talk: 正しい色は適用される');

// --- comment: 掲示板管理パスワードの既定値では削除できないこと ---
$salt = 'salt324';
$row324 = ['delkey' => '!!sha256::' . $salt . '::' . kona3plugins_comment_getHash('postpw', $salt)];
$saved_bbs_pw = isset($kona3conf['bbs_admin_password']) ? $kona3conf['bbs_admin_password'] : null;
unset($kona3conf['bbs_admin_password']);
test_assert(__LINE__, kona3plugins_comment_checkDeletePassword($row324, 'postpw'), 'comment削除: 投稿者パスワードでは削除できる');
test_assert(__LINE__, !kona3plugins_comment_checkDeletePassword($row324, 'BBS#admin!PassWord!!'), 'comment削除: 旧既定値では削除できない (未設定時)');
$kona3conf['bbs_admin_password'] = 'BBS#admin!PassWord!!';
test_eq(__LINE__, kona3plugins_comment_getBbsAdminPassword(), '', 'comment削除: 旧既定値が残っていても未設定として扱う');
test_assert(__LINE__, !kona3plugins_comment_checkDeletePassword($row324, 'BBS#admin!PassWord!!'), 'comment削除: 旧既定値では削除できない (設定残存時)');
$kona3conf['bbs_admin_password'] = 'MySecret#324';
test_eq(__LINE__, kona3plugins_comment_getBbsAdminPassword(), 'MySecret#324', 'comment削除: 管理者が設定した値は有効');
test_assert(__LINE__, kona3plugins_comment_checkDeletePassword($row324, 'MySecret#324'), 'comment削除: 設定した管理パスワードで削除できる');
test_assert(__LINE__, !kona3plugins_comment_checkDeletePassword($row324, 'wrong'), 'comment削除: 誤ったパスワードでは削除できない');
$conf_items = kona3conf_getConfigItems();
test_eq(__LINE__, $conf_items['Basic']['bbs_admin_password']['default'], '', 'comment削除: bbs_admin_passwordの既定値は空');
if ($saved_bbs_pw === null) {
    unset($kona3conf['bbs_admin_password']);
} else {
    $kona3conf['bbs_admin_password'] = $saved_bbs_pw;
}

// --- include: 自己参照でも無限再帰しないこと ---
$self_page = 'SelfRef324';
$self_file = kona3getWikiFile($self_page, true, '.txt');
file_put_contents($self_file, "#include($self_page)\n");
global $kona3_include_depth;
$kona3_include_depth = 0;
$self_html = kona3plugins_include_execute([$self_page]);
test_assert(__LINE__, strpos($self_html, 'include depth limit exceeded') !== FALSE, 'include: 自己参照は深さ制限で止まる');
test_eq(__LINE__, $kona3_include_depth, 0, 'include: 変換後は深さカウンタが戻る');
@unlink($self_file);

// --- file / filecode: data内のシンボリックリンクで外のファイルを読めないこと ---
$secret_file = KONA3_DIR_PRIVATE . '/secret324.txt';
file_put_contents($secret_file, 'SECRET324_BODY');
$link_file = KONA3_DIR_DATA . '/link324.txt';
$linkcode_file = KONA3_DIR_DATA . '/linkcode324.txt';
test_assert(__LINE__, @symlink($secret_file, $link_file), 'file: テスト用シンボリックリンクを作成');
test_assert(__LINE__, @symlink($secret_file, $linkcode_file), 'filecode: テスト用シンボリックリンクを作成');
$file_html = kona3plugins_file_execute(['link324.txt']);
test_assert(__LINE__, strpos($file_html, 'SECRET324_BODY') === FALSE, 'file: シンボリックリンク先の秘密は読めない');
$fc_html = kona3plugins_filecode_execute(['linkcode324.txt']);
test_assert(__LINE__, strpos($fc_html, 'SECRET324_BODY') === FALSE, 'filecode: シンボリックリンク先の秘密は読めない');
@unlink($link_file);
@unlink($linkcode_file);
// 通常ファイルは読める
$normal_file = KONA3_DIR_DATA . '/normal324.txt';
file_put_contents($normal_file, "* Normal324\nbody324");
$file_html2 = kona3plugins_file_execute(['normal324.txt']);
test_assert(__LINE__, strpos($file_html2, 'Normal324') !== FALSE, 'file: 通常ファイルは表示できる');
$fc_html2 = kona3plugins_filecode_execute(['normal324.txt']);
test_assert(__LINE__, strpos($fc_html2, 'Normal324') !== FALSE, 'filecode: 通常ファイルは表示できる');
@unlink($normal_file);
@unlink($secret_file);

// --- skin: 配信できる拡張子の制限 ---
test_assert(__LINE__, kona3_action_skin_isAllowedExt('.css'), 'skin: cssは許可');
test_assert(__LINE__, kona3_action_skin_isAllowedExt('.JS'), 'skin: jsは許可 (大文字も)');
test_assert(__LINE__, kona3_action_skin_isAllowedExt('.png'), 'skin: pngは許可');
test_assert(__LINE__, !kona3_action_skin_isAllowedExt('.php'), 'skin: phpは拒否 (テンプレートのソースを開示しない)');
test_assert(__LINE__, !kona3_action_skin_isAllowedExt(''), 'skin: 拡張子なしは拒否');
test_assert(__LINE__, !kona3_action_skin_isAllowedExt('.html'), 'skin: htmlは拒否');

// --- テンプレートエンジン: パラメータで include 先を差し替えられないこと ---
global $DIR_TEMPLATE, $DIR_TEMPLATE_CACHE, $FW_TEMPLATE_PARAMS;
$saved_tpl_dir = $DIR_TEMPLATE;
$saved_tpl_cache = $DIR_TEMPLATE_CACHE;
$tpl_dir324 = KONA3_DIR_PRIVATE . '/test_templates324';
$cache_dir324 = KONA3_DIR_PRIVATE . '/test_cache324';
@mkdir($tpl_dir324, 0777, true);
@mkdir($cache_dir324, 0777, true);
$DIR_TEMPLATE = $tpl_dir324;
$DIR_TEMPLATE_CACHE = $cache_dir324;
file_put_contents($tpl_dir324 . '/victim324.html', 'Hello, {{$name}}!');
$evil_file = $tpl_dir324 . '/evil324.php';
file_put_contents($evil_file, '<?php echo "EXTRACT_PWNED324"; ?>');
for ($i = 0; $i < 2; $i++) { // 1回目はコンパイル経路、2回目はキャッシュ経路
    ob_start();
    template_render('victim324.html', [
        'name' => 'SAFE324',
        'file_cache' => $evil_file,
        '__file_cache' => $evil_file,
        '__fw_contents' => '<?php echo "EXTRACT_PWNED324"; ?>',
    ]);
    $tpl_out = ob_get_clean();
    test_assert(__LINE__, strpos($tpl_out, 'Hello, SAFE324!') !== FALSE, "テンプレート: 通常の変数は表示される (試行$i)");
    test_assert(__LINE__, strpos($tpl_out, 'EXTRACT_PWNED324') === FALSE, "テンプレート: パラメータで任意ファイルを実行できない (試行$i)");
}
$DIR_TEMPLATE = $saved_tpl_dir;
$DIR_TEMPLATE_CACHE = $saved_tpl_cache;
array_map('unlink', glob($tpl_dir324 . '/*'));
array_map('unlink', glob($cache_dir324 . '/*'));
@rmdir($tpl_dir324);
@rmdir($cache_dir324);

// --- アクション側のガード (ソース確認) ---
$action_dir = dirname(__DIR__) . '/action';
$src_search = file_get_contents($action_dir . '/search.inc.php');
test_assert(__LINE__, strpos($src_search, 'kona3show_check_private') !== FALSE, 'search: 非公開Wikiのチェックがある');
$src_data = file_get_contents($action_dir . '/data.inc.php');
test_assert(__LINE__, strpos($src_data, 'Content-Security-Policy: sandbox') !== FALSE, 'data: SVGにCSP sandboxを付ける');
$src_attach = file_get_contents($action_dir . '/attach.inc.php');
test_assert(__LINE__, strpos($src_attach, "kona3_checkEditToken('edit_token')") !== FALSE, 'attach: アップロードにCSRFトークンを要求する');
$tpl_attach = file_get_contents(dirname(__DIR__) . '/template/attach.html');
test_assert(__LINE__, strpos($tpl_attach, 'name="edit_token"') !== FALSE, 'attach: フォームにedit_tokenがある');
$src_email = file_get_contents($action_dir . '/emailLogs.inc.php');
test_assert(__LINE__, strpos($src_email, "kona3_checkEditToken('edit_token')") !== FALSE, 'emailLogs: 全削除にCSRFトークンを要求する');
$src_uph = file_get_contents($action_dir . '/update_page_history.inc.php');
$pos_token = strpos($src_uph, "kona3_checkEditToken('edit_token')");
$pos_delete = strpos($src_uph, 'DELETE FROM page_history');
test_assert(__LINE__, $pos_token !== FALSE && $pos_delete !== FALSE && $pos_token < $pos_delete, 'update_page_history: 全削除の前にCSRFトークンを確認する');
$src_versionup = file_get_contents($action_dir . '/versionup.inc.php');
test_assert(__LINE__, strpos($src_versionup, 'kona3isAdmin()') !== FALSE, 'versionup: 管理者のみ実行できる');
$src_user = file_get_contents($action_dir . '/user.inc.php');
test_assert(__LINE__, strpos($src_user, "kona3getConf('wiki_private')") !== FALSE, 'user: 非公開Wikiのチェックがある');
$src_plugin = file_get_contents($action_dir . '/plugin.inc.php');
test_assert(__LINE__, strpos($src_plugin, "kona3getConf('wiki_private')") !== FALSE, 'plugin: 非公開Wikiのチェックがある');
$src_resource = file_get_contents($action_dir . '/resource.inc.php');
test_assert(__LINE__, strpos($src_resource, 'X-Content-Type-Options: nosniff') !== FALSE, 'resource: nosniffを付ける');
$src_lib = file_get_contents(dirname(__DIR__) . '/kona3lib.inc.php');
$pos_notice = strpos($src_lib, 'function kona3_getVersionupNotice');
$notice_seg = substr($src_lib, $pos_notice, 300);
test_assert(__LINE__, strpos($notice_seg, 'kona3isAdmin()') !== FALSE, 'versionup案内: 管理者にだけ表示する');
