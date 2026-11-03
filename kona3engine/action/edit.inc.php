<?php

include_once dirname(__DIR__) . '/kona3lib.inc.php';
include_once dirname(__DIR__) . '/kona3db.inc.php';
include_once dirname(__DIR__) . '/kona3parser.inc.php';
include_once dirname(__DIR__) . '/kona3ai.inc.php';
include_once dirname(__DIR__) . '/kona3tags.inc.php';

header('X-Frame-Options: SAMEORIGIN');

if (!function_exists('str_ends_with')) {
    function str_ends_with($str, $end)
    {
        return (@substr_compare($str, $end, -strlen($end)) == 0);
    }
}

function kona3_action_edit()
{
    global $kona3conf, $page;

    // get parameters
    $ext = "txt";
    $page = $kona3conf["page"];
    $action = kona3getPageURL($page, "edit");
    $a_mode = kona3param('a_mode', '');
    $i_mode = kona3param('i_mode', 'form'); // or ajax
    $git_enabled = $kona3conf["git_enabled"];
    $page_id = kona3db_getPageId($page, FALSE);

    // check permission
    if (!kona3edit_checkPermission($page, $i_mode)) {
        return;
    }

    // check $cmd
    $cmd = kona3param("cmd");
    if ($cmd != '') {
        return edit_command($cmd);
    }
    // check $q
    $q = kona3param("q");
    if ($q == 'ai') {
        return kona3edit_ai_ajax($page);
    } else if ($q == 'ai_edit_template') {
        return kona3ai_edit_template();
    } else if ($q == 'keepalive') {
        // 編集中のセッション維持 (issue #64, 案3)
        return kona3edit_keepalive($page);
    } else if ($q == 'preview') {
        // プレビュー並行表示用に本文を描画する (issue #153)
        return kona3edit_preview_ajax($page);
    }

    // generate edit_token
    // (memo) 強制的に更新しないことで不要な書き込みエラーを防ぐ
    $edit_token = kona3_getEditToken("edit_token", FALSE);

    // load body
    $txt = "";
    if ($q == 'history') {
        // history mode
        $history_id = kona3param("history_id");
        $r = kona3db_getPageHistoryById($history_id);
        $txt = isset($r['body']) ? $r['body'] : '(empty)';
    } else {
        // normal mode
        // 編集対象は kona3getEditFile() が決める .txt / .md ファイルだけ (#53)。
        // 同名の生ファイル (拡張子なしや .php など) が data/ にあっても読み込まない
        // (読み込むと、保存先の .txt / .md ファイルと内容が食い違うため)
        $fname = kona3getEditFile($page, $ext);
        if (is_file($fname)) {
            // check filesize
            $sz = @filesize($fname);
            $max_edit_size = intval(kona3getConf('max_edit_size', '0'));
            if ($max_edit_size > 0) {
                $max_bytes = 1024 * 1024 * $max_edit_size;
                if ($sz > $max_bytes) {
                    kona3error(
                        'File Size overflow',
                        "<h1>File size overflow</h1>" .
                            "<p>max_edit_size: $max_edit_size MB</p>" .
                            "<p>File size: $sz B</p>"
                    );
                    exit;
                }
            }
            // load text
            $txt = kona3lock_load($fname);
            if ($txt === FALSE) {
                kona3error('Could not read file.', 'Could not read file.');
                exit;
            }
        }
    }
    $a_hash = kona3getPageHash($txt);

    // Check mode
    if ($a_mode !== '') {
        // check edit_token
        if (!kona3edit_checkEditToken($page, $i_mode)) {
            return;
        }
    }
    if ($a_mode == "trywrite" && $i_mode == "form" && $git_enabled) {
        // save & show with git
        $msg = kona3_trygit($txt, $a_hash, $i_mode);
    } else if ($a_mode == "trywrite") {
        // normal save
        $msg = kona3_trywrite($txt, $a_hash, $i_mode, $result);
    } else if ($a_mode == "trygit") {
        $msg = kona3_trygit($txt, $a_hash, $i_mode);
    } else {
        $msg = "";
        // ページ名が長すぎる場合は、編集画面を開いた時点で保存できないことを知らせる (issue #133)
        $too_long_msg = kona3getPageNameTooLongMessage($page);
        if ($too_long_msg !== '') {
            $msg = "<div class='error'>" . kona3text2html($too_long_msg) . "</div>";
        }
        // 凍結されたページでは、編集画面を開いた時点でその旨を知らせる (issue #16)
        if (kona3pageIsFrozen($page)) {
            $frozen_msg = kona3isAdmin()
                ? lang('This page is frozen. You are an administrator, so you can edit it.')
                : lang('This page is frozen. Only administrators can edit it.');
            $msg .= "<div class='error'>" . kona3text2html($frozen_msg) . "</div>";
        }
    }
    // Ajaxならテンプレート出力しない
    if ($i_mode == 'ajax') return;

    // include script (highlight core/layer must load before edit.js)
    $kona3conf['js'][] = kona3getResourceURL('edit_highlight_core.js', TRUE);
    $kona3conf['js'][] = kona3getResourceURL('edit_highlight.js', TRUE);
    $kona3conf['js'][] = kona3getResourceURL('edit_linenum.js', TRUE);
    $kona3conf['js'][] = kona3getResourceURL('edit_search_core.js', TRUE);
    $kona3conf['js'][] = kona3getResourceURL('edit_ai_core.js', TRUE);
    $kona3conf['js'][] = kona3getResourceURL('edit.js', TRUE);
    $kona3conf['css'][] = kona3getResourceURL('edit.css', TRUE);

    // history
    $history = kona3db_getPageHistory($page, $edit_token);

    // tags
    $tags = '';
    // メタ情報ファイルからタグを読み込む
    $meta = kona3db_loadPageMeta($page);
    if ($meta !== null && isset($meta['tags']) && is_array($meta['tags'])) {
        $tags = implode('/', $meta['tags']);
    }

    // new button
    $new_btn_url = kona3getPageURL($page, "new");
    $ai_settings = kona3edit_ai_get_provider_settings();
    $ai_provider = $ai_settings['provider'];
    $ai_enabled = $ai_settings['enabled'];
    $ai_edit_template_url = kona3getPageURL($page, "edit", "", "q=ai_edit_template&edit_token=$edit_token");
    $ai_edit_conf_url = kona3getPageURL($page, "editConf") . "#conf_category_AI";
    $ai_actions = [];
    $ai_actions_doc = []; // 本文全体・カーソル位置が対象
    $ai_actions_sel = []; // 選択範囲が対象 (#378)
    foreach (kona3edit_ai_get_actions() as $ai_action_id => $ai_action_def) {
        $item = ['id' => $ai_action_id, 'label' => lang($ai_action_def['label'])];
        $ai_actions[] = $item;
        if ($ai_action_def['input'] === 'selected') {
            $ai_actions_sel[] = $item;
        } else {
            $ai_actions_doc[] = $item;
        }
    }
    $ai_user_prompts = [];
    $ai_user_file = KONA3_DIR_DATA . "/ai_prompt.md";
    if (file_exists($ai_user_file)) {
        $ai_user_prompts = array_keys(kona3edit_ai_parse_user_prompts((string)@file_get_contents($ai_user_file)));
    }

    // page_mode
    $page_mode = (is_array($meta) && isset($meta['mode'])) ? $meta['mode'] : '';
    if ($page_mode === '') {
        $page_mode = ($ext == 'md') ? 'Markdown' : 'KonaNotation';
    }

    // セッション切れで保存できなかった本文の復旧 (issue #64, 案2)
    // 保存に失敗したときに退避した本文があれば、ファイルの内容ではなく
    // 退避した本文を編集画面に表示する。$a_hash はファイルの内容から
    // 計算したもののままにするので、復旧後の保存で競合判定が正しく働く。
    if ($a_mode === '' && $q !== 'history') {
        $recovery = kona3edit_getRecovery($page);
        if ($recovery !== null) {
            list($txt, $tags, $page_mode, $ext) =
                kona3edit_applyRecovery($recovery, $txt, $tags, $page_mode, $ext);
            $recovery_msg = "<div class='error'>" .
                kona3text2html(lang('Recovered unsaved text that could not be saved because the session expired. Please check and save again.')) .
                "</div>";
            $msg = $recovery_msg . $msg;
            // 退避はここでは消さない。表示しただけで消すと、保存する前に
            // 画面をリロードしたときに本文が失われてしまう。
            // 保存が成功したとき (kona3_trywrite) に破棄される。
        }
    }

    // 編集画面では共通ヘッダー/フッターを出さない (issue #153)
    $kona3conf['edit_screen'] = true;

    // show
    kona3template('edit.html', array(
        "action" => $action,
        "a_hash" => $a_hash,
        "page_title" => $page,
        "page_url" => kona3getPageURL($page, "show"),
        "edit_txt"  => $txt,
        "msg" => $msg,
        "conflict_diff" => isset($GLOBALS['kona3_conflict_diff']) ? $GLOBALS['kona3_conflict_diff'] : '',
        "history" => $history,
        "edit_token" => $edit_token,
        "tags" => $tags,
        "new_btn_url" => $new_btn_url,
        "ai_enabled" => $ai_enabled,
        "ai_provider" => $ai_provider,
        "ai_edit_template_url" => $ai_edit_template_url,
        "ai_edit_conf_url" => $ai_edit_conf_url,
        "ai_actions" => $ai_actions,
        "ai_actions_doc" => $ai_actions_doc,
        "ai_actions_sel" => $ai_actions_sel,
        "ai_user_prompts" => $ai_user_prompts,
        "editor_shortcut_temp_save" => kona3getConf('editor_shortcut_temp_save', 'Ctrl+S'),
        "editor_shortcut_new" => kona3getConf('editor_shortcut_new', 'Ctrl+Alt+N'),
        "editor_highlight_default" => kona3getConf('editor_highlight_default', TRUE) ? '1' : '0',
        "editor_line_number_default" => kona3getConf('editor_line_number_default', TRUE) ? '1' : '0',
        "ai_shortcut_complete" => kona3getConf('ai_shortcut_complete', 'Ctrl+Shift+H'),
        "ai_shortcut_spellcheck" => kona3getConf('ai_shortcut_spellcheck', 'Ctrl+Shift+J'),
        "ai_shortcut_user_prompt1" => kona3getConf('ai_shortcut_user_prompt1', 'Ctrl+Alt+1'),
        "ai_shortcut_user_prompt2" => kona3getConf('ai_shortcut_user_prompt2', 'Ctrl+Alt+2'),
        "edit_ext" => $ext,
        "page_mode" => $page_mode,
        "login_url" => kona3getPageURL($page, "login"),
    ));
}

function kona3edit_checkPermission($page, $i_mode)
{
    // check permission
    if (!kona3isLogin()) {
        // セッション切れで未ログインになっている場合は、送信された本文を
        // 退避してからエラーにする (issue #64, 案2)。
        // 再ログイン後に編集画面を開くと、退避した本文が復旧される。
        $stashed = kona3edit_stashRecovery($page);
        $please_login = lang("Please login.");
        $url = kona3getPageURL($page, 'login');
        $msg = "<a href=\"$url\">{$please_login}</a>";
        if ($stashed) {
            $msg .= "<br>" . kona3text2html(lang('Your unsaved text was kept. It will be restored when you open the edit page after logging in again.'));
        }
        if ($i_mode == 'ajax') {
            $msg = $please_login;
        }
        kona3_edit_err($msg, $i_mode, 'nologin');
        exit;
    }
    return true;
}

// 凍結ページの保存可否を判定する (issue #16)
// - 凍結されたページの書き換え (削除のための空保存も含む) は管理者だけが行える
// - 凍結されていないページを新たに凍結する操作も管理者だけが行える
// 戻り値: 保存できる場合は ''、できない場合は理由 (表示用文言)
function kona3edit_frozenError($page, $edit_txt)
{
    if (kona3isAdmin()) {
        return '';
    }
    if (kona3pageIsFrozen($page)) {
        return lang('This page is frozen. Only administrators can edit it.');
    }
    if (kona3isFrozenPageText($edit_txt)) {
        return lang('Only administrators can freeze a page. Please remove #freeze from the first line.');
    }
    return '';
}

function kona3edit_checkEditToken($page, $i_mode)
{
    // check edit_token
    if (!kona3_checkEditToken("edit_token")) {
        // セッション切れでトークンが失われた場合も、送信された本文を
        // 退避してからエラーにする (issue #64, 案2)。
        $stashed = kona3edit_stashRecovery($page);
        $label = lang('Edit');
        $edit_token = kona3_getEditToken("edit_token", TRUE);
        $url = kona3getPageURL($page, 'edit', '');
        $page_html = htmlspecialchars($page, ENT_QUOTES);
        $label_page = "$label &gt; $page_html";
        $msg = lang('Invalid edit token.');
        if ($stashed) {
            $msg .= "<br>" . kona3text2html(lang('Your unsaved text was kept. It will be restored when you open the edit page after logging in again.'));
        }
        // form or ajax
        if ($i_mode == 'form') {
            $form = kona3_getEditTokenForm($page, 'edit', $label_page);
            kona3showMessage(
                "$label > $page_html",
                "$msg<br>$form"
            );
        } else { // ajax
            // JS がセッション切れとして扱えるよう、専用のコードを返す (issue #64)
            kona3_edit_err(lang('Invalid edit token.'), $i_mode, 'invalid_token');
        }
        exit;
    }
    return true;
}


// -----------------------------------------------
// セッション切れ対策 (issue #64)
// -----------------------------------------------
// (案2) 保存に失敗した本文をセッションへ退避し、再ログイン後の
//       編集画面で復旧できるようにする。
// (案3) 編集画面を開いている間、定期的にサーバーと交信して
//       セッションを維持し、編集トークンを交換する (keepalive)。

// 退避した本文をセッションに保持するキー名
define('KONA3_EDIT_RECOVERY_KEY', 'kona3_edit_recovery');
// 退避した本文の有効期間 (編集トークンの有効期間に合わせる)
define('KONA3_EDIT_RECOVERY_TTL', 60 * 60 * 24);

// 保存しようとした本文をセッションへ退避する。
// 保存系の送信 (a_mode=trywrite/trygit) で本文が送られている場合だけ退避し、
// 退避できたら TRUE を返す。本文にはページを空にする (削除する) ための
// 空文字列も含むので、パラメータの有無で判定する。
function kona3edit_stashRecovery($page)
{
    $a_mode = kona3param('a_mode', '');
    if ($a_mode !== 'trywrite' && $a_mode !== 'trygit') {
        return FALSE;
    }
    if (!isset($_REQUEST['edit_txt']) || !is_string($_REQUEST['edit_txt'])) {
        return FALSE;
    }
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }
    if (!isset($_SESSION[KONA3_EDIT_RECOVERY_KEY]) || !is_array($_SESSION[KONA3_EDIT_RECOVERY_KEY])) {
        $_SESSION[KONA3_EDIT_RECOVERY_KEY] = [];
    }
    // 付随する項目は、パラメータが送られた場合だけ退避する。
    // 「送られなかった」と「空文字が送られた (例: タグを全て消した)」を
    // 区別するためで、復旧側はキーの有無で復元するかどうかを決める。
    $recovery = [
        'txt' => $_REQUEST['edit_txt'],
        'time' => time(),
    ];
    foreach (['tags', 'edit_ext', 'page_mode'] as $k) {
        if (isset($_REQUEST[$k]) && is_string($_REQUEST[$k])) {
            $recovery[$k] = $_REQUEST[$k];
        }
    }
    $_SESSION[KONA3_EDIT_RECOVERY_KEY][$page] = $recovery;
    return TRUE;
}

// 退避した内容を編集画面の表示値へ反映する。
// 戻り値: [$txt, $tags, $page_mode, $ext]
// - 本文は常に退避したものに置き換える
// - タグはキーがあれば (空文字 = タグを全て消した場合も含めて) 復元する
// - 記法モードと拡張子は、退避値が空でない場合だけ復元する
//   (正規の値は空文字にならないため、空は「不明」として扱う)
function kona3edit_applyRecovery($recovery, $txt, $tags, $page_mode, $ext)
{
    if (!is_array($recovery) || !isset($recovery['txt'])) {
        return [$txt, $tags, $page_mode, $ext];
    }
    $txt = $recovery['txt'];
    if (array_key_exists('tags', $recovery)) {
        $tags = $recovery['tags'];
    }
    if (isset($recovery['page_mode']) && $recovery['page_mode'] !== '') {
        $page_mode = $recovery['page_mode'];
    }
    if (isset($recovery['edit_ext']) && kona3isEditableExt($recovery['edit_ext'])) {
        $ext = $recovery['edit_ext'];
    }
    return [$txt, $tags, $page_mode, $ext];
}

// 退避した本文を取得する。なければ (または期限切れなら) null を返す。
function kona3edit_getRecovery($page)
{
    if (empty($_SESSION[KONA3_EDIT_RECOVERY_KEY][$page]) ||
        !is_array($_SESSION[KONA3_EDIT_RECOVERY_KEY][$page])) {
        return null;
    }
    $recovery = $_SESSION[KONA3_EDIT_RECOVERY_KEY][$page];
    $time = isset($recovery['time']) ? intval($recovery['time']) : 0;
    if ($time <= 0 || (time() - $time) > KONA3_EDIT_RECOVERY_TTL) {
        kona3edit_clearRecovery($page);
        return null;
    }
    if (!isset($recovery['txt']) || !is_string($recovery['txt'])) {
        return null;
    }
    return $recovery;
}

// 退避した本文を破棄する。
function kona3edit_clearRecovery($page)
{
    if (isset($_SESSION[KONA3_EDIT_RECOVERY_KEY][$page])) {
        unset($_SESSION[KONA3_EDIT_RECOVERY_KEY][$page]);
    }
}

// keepalive の応答データを組み立てる。
// ログイン中であることだけを条件に、現在の (期限切れなら再発行した)
// 編集トークンを返す。編集画面の JavaScript がトークンを受け取って
// フォームの値を差し替えることで、開いたままのフォームが
// トークンの期限切れで保存できなくなることを防ぐ。
// あわせてセッションの最終アクセス時刻を更新し、サーバー側の
// セッションが GC で消えるのを防ぐ。
function kona3edit_keepaliveData($page)
{
    if (!kona3isLogin()) {
        return [
            'result' => 'ng',
            'code' => 'nologin',
            'reason' => lang('Please login.'),
        ];
    }
    $_SESSION['kona3_last_activity'] = time();
    return [
        'result' => 'ok',
        'edit_token' => kona3_getEditToken('edit_token'),
        'user' => kona3getUserName(),
        'time' => time(),
    ];
}

function kona3edit_keepalive($page)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(kona3edit_keepaliveData($page));
    return TRUE;
}

// edit command execute
function edit_command($cmd)
{
    global $kona3conf, $page;
    if (!kona3edit_checkEditToken($page, 'form')) {
        return;
    }
    $page = $kona3conf["page"];
    $action = kona3getPageURL($page, "edit");

    if (!kona3isAdmin()) {
        return kona3error('Not Admin', lang('You do not have admin perm.'));
    }
    if ($cmd == 'history_delete') {
        $history_id = intval(kona3param("history_id"));
        $hash = kona3param("hash");
        $r = db_exec(
            'DELETE FROM page_history ' .
                'WHERE history_id=? AND hash=?',
            [$history_id, $hash]
        );
        if ($r) {
            // 編集トークンは "edit_token" キーで発行したものを使う (#46)。
            // ページ名をキーにすると、照合側 (kona3_checkEditToken
            // ("edit_token")) とは別のトークンになってしまう。
            $edit_token = kona3_getEditToken("edit_token", FALSE);
            $url = kona3getPageURL($page, "edit", "", "edit_token=$edit_token");
            return kona3showMessage(
                'DELETE History',
                "OK! (history_id=$history_id) " .
                    "<a href='$url'>Continue to edit</a>"
            );
        } else {
            return kona3error('ng', 'Sorry, failed to delete.');
        }
    }
    kona3error('ng', 'Unknown command');
}

// 行単位の差分テキストを作る。共通行はそのまま、$text_a にのみある行は
// '<< '、$text_b にのみある行は '>> ' を付ける。
// 以前は単純な位置合わせだったため、途中の1行の差し替えで以降の行が
// すべてずれて表示されていた。LCS で行の対応を取るようにし、巨大な
// テキストでは従来の位置合わせにフォールバックする
// (PR #349 レビュー対応、表示は AJAX 側の computeLineDiff と揃えた)。
function kona3_make_diff($text_a, $text_b)
{
    $lines_a = explode("\n", $text_a);
    $lines_b = explode("\n", $text_b);
    $na = count($lines_a);
    $nb = count($lines_b);
    if (($na + 1) * ($nb + 1) > 1000000) {
        return kona3_make_diff_simple($lines_a, $lines_b);
    }
    // LCS の長さ表 (後方から)
    $lcs = array();
    for ($i = 0; $i <= $na; $i++) {
        $lcs[$i] = array_fill(0, $nb + 1, 0);
    }
    for ($i = $na - 1; $i >= 0; $i--) {
        for ($j = $nb - 1; $j >= 0; $j--) {
            if ($lines_a[$i] === $lines_b[$j]) {
                $lcs[$i][$j] = $lcs[$i + 1][$j + 1] + 1;
            } else {
                $lcs[$i][$j] = max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }
    }
    $res = array();
    $i = 0;
    $j = 0;
    while ($i < $na && $j < $nb) {
        if ($lines_a[$i] === $lines_b[$j]) {
            $res[] = $lines_a[$i];
            $i++;
            $j++;
        } else if ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
            $res[] = '<< ' . $lines_a[$i];
            $i++;
        } else {
            $res[] = '>> ' . $lines_b[$j];
            $j++;
        }
    }
    while ($i < $na) {
        $res[] = '<< ' . $lines_a[$i];
        $i++;
    }
    while ($j < $nb) {
        $res[] = '>> ' . $lines_b[$j];
        $j++;
    }
    return implode("\n", $res);
}

// 従来の位置合わせによる差分 (巨大なテキスト用のフォールバック)
function kona3_make_diff_simple($lines_a, $lines_b)
{
    $res = array();
    $ia = $ib = 0;
    for (;;) {
        $a = isset($lines_a[$ia]) ? $lines_a[$ia] : NULL;
        $b = isset($lines_b[$ib]) ? $lines_b[$ib] : NULL;
        if ($a === NULL && $b === NULL) break;
        // same
        if ($a == $b) {
            $res[] = $a;
            $ia++;
            $ib++;
            continue;
        }
        // not same
        if ($a === NULL) {
            $res[] = '>> ' . $b;
            $ib++;
            continue;
        }
        if ($b === NULL) {
            $res[] = '<< ' . $a;
            $ia++;
            continue;
        }
        //
        $res[] = $a;
        $ia++;
    }
    return implode("\n", $res);
}

function kona3_edit_err($msg, $method = "web", $code = '', $extra = array())
{
    global $page;
    if ($method == "ajax" || $method == "git") {
        // $extra は追加情報専用で、result/reason/code を上書きできない
        // ように本体を後勝ちでマージする (PR #349 レビュー対応)
        echo json_encode(array_merge($extra, array(
            'result' => 'ng',
            'reason' => $msg,
            'code' => $code,
        )));
    } else {
        kona3error($page, $msg);
    }
}

function kona3_conflict($edit_txt, &$txt, $i_mode)
{
    // エラーメッセージ
    $msg = lang("Conflict editing, Please submit and check.");
    // ajaxの場合
    if ($i_mode == "ajax") {
        // issue #153 フェーズ4: 本文は書き換えず、競合の解決に必要な
        // サーバー側の本文とそのハッシュをクライアントへ返す。
        // クライアントはドロワーの競合パネルで解決方法を選ぶ。
        kona3_edit_err($msg, $i_mode, 'conflict', array(
            'server_text' => $txt,
            'server_hash' => kona3getPageHash($txt),
        ));
        return $msg;
    }
    // formの場合
    $msg = "<div class='error'>$msg</div>";
    // issue #153 フェーズ4: 以前はテキストエリアの中身を差分テキストで
    // 置き換えていたが、ユーザーの本文がそのまま残らないため廃止した。
    // 差分は別枠 (テンプレートの $conflict_diff) で表示し、テキストエリア
    // にはユーザーが送信した本文をそのまま残す。再表示時の a_hash は
    // 呼び出し側でディスク側の本文から計算済みなので、再送信すると
    // ユーザーの内容で上書きされる。
    $GLOBALS['kona3_conflict_diff'] = kona3_make_diff($edit_txt, $txt);
    $txt = $edit_txt;
    return $msg;
}

function kona3getEditFile($page, &$ext)
{
    // 編集できるファイル形式は .txt / .md だけ (issue #53、
    // リストは kona3getEditableExtList() が唯一の定義)
    $test_ext = kona3getFileExt($page);
    if ($test_ext !== '' && kona3isEditableExt($test_ext)) {
        // ページ名の末尾が .txt / .md の場合は、そのままがファイル名である
        $ext = $test_ext;
        $fname = kona3getWikiFile($page, FALSE, '');
    } else {
        // 拡張子がない場合、および、それ以外の拡張子 (php, html, json など) の場合:
        // 拡張子はページ名の一部として扱い、ファイル名には .txt / .md を付与する
        // (例: "xxxx.php" → "xxxx.php.txt" または "xxxx.php.md")。
        // どちらの拡張子になるかは koan3getWikiFileText() が決める:
        // 既存ファイル → メタ情報の mode → 設定 def_text_ext の順。
        // 従来はリスト外の拡張子をエラーにしていたため、"xxxx.php" のような
        // ページが保存できなかった。
        $fname = koan3getWikiFileText($page);
        $ext = kona3getFileExt($fname);
    }
    return $fname;
}

// 保存時の edit_ext を検証して正規化する (issue #53 agy レビュー指摘)
// - ページ名自体が .txt / .md で終わる場合は、その拡張子が正である
//   (フォームの値や記法モードで変わることはない)
// - edit_ext が .txt / .md の場合はそのまま使う
// - それ以外 (改ざんされた 'php'、欠落した空文字など) は、ページの実際の
//   ファイル (既存ファイル → mode → def_text_ext) から解決した拡張子を使う。
//   これにより、意図しないファイル名 (xxxx.php.md など) への保存や、
//   Git フックとの食い違いを防ぐ (生の xxxx.php が作られることは元々ない)
function kona3edit_normalizeEditExt($page, $edit_ext)
{
    $page_ext = kona3getFileExt($page);
    if ($page_ext !== '' && kona3isEditableExt($page_ext)) {
        return $page_ext;
    }
    if (kona3isEditableExt($edit_ext)) {
        return $edit_ext;
    }
    $resolved = '';
    kona3getEditFile($page, $resolved);
    return kona3isEditableExt($resolved) ? $resolved : 'md';
}


function kona3_trywrite(&$txt, &$a_hash, $i_mode, &$result)
{
    global $kona3conf, $page;

    $edit_txt = kona3param('edit_txt', '');
    $a_hash_frm = kona3param('a_hash', '');
    $tags = kona3param('tags', '');
    $edit_ext = kona3param('edit_ext', '');
    $page_mode = kona3param('page_mode', '');
    $a_mode = kona3param('a_mode', '');
    $postId = intval(kona3param('postId', 0)); // option

    // edit_ext を検証する (ページ名が .txt/.md で終わる場合はその拡張子、
    // 不正・欠落した値は実際のファイルから解決した拡張子にする) (#53)
    $edit_ext = kona3edit_normalizeEditExt($page, $edit_ext);
    // ページ名自体が直接編集できる拡張子で終わっているか
    // (その場合はファイル名が明示されているので、拡張子は固定される)
    $page_ext = kona3getFileExt($page);
    $has_explicit_ext = ($page_ext !== '' && kona3isEditableExt($page_ext));

    // 既存ファイルの存在確認
    // ページ名に拡張子が明示されている場合は、そのファイル自体を確認する
    // ("{$page}.{$edit_ext}" とすると memo.txt → memo.txt.txt になってしまう)
    if ($has_explicit_ext) {
        $original_fname = kona3getEditFile($page, $dummy_ext);
    } else {
        $original_fname = kona3getEditFile("{$page}.{$edit_ext}", $dummy_ext);
    }
    $has_existing_file = file_exists($original_fname);

    // page_mode に基づいて拡張子を同期
    $new_ext = $edit_ext;
    if ($has_existing_file || $has_explicit_ext) {
        // 既存ファイルがある場合、および、ページ名に拡張子が明示されている
        // 場合は拡張子を変更しない (記法モードはメタ情報で管理される)
        $new_ext = $edit_ext;
    } else {
        // 新規ファイル作成時のみ、page_mode に応じて拡張子を変更可能
        if ($page_mode === 'Markdown') {
            $new_ext = 'md';
        } else if ($page_mode === 'KonaNotation') {
            $new_ext = 'txt';
        }
    }

    // ページ名の末尾に拡張子があるか確認 (元の edit_ext で判定)
    if ($edit_ext !== '' && str_ends_with($page, ".{$edit_ext}")) {
        $page = substr($page, 0, strlen($page) - strlen(".{$edit_ext}"));
    }

    // 古いファイルパスの特定
    $old_fname = '';
    if ($edit_ext !== '' && $edit_ext !== $new_ext) {
        $old_fname = kona3getEditFile("{$page}.{$edit_ext}", $dummy_ext);
    }

    $edit_ext = $new_ext;
    $fname = kona3getEditFile("{$page}.{$edit_ext}", $ext);
    $user_id = kona3getUserId();

    // 凍結ページの確認 (issue #16)
    // 保存経路 (form / ajax / git) はすべてこの関数を通るので、ここで一括して弾く。
    $frozen_error = kona3edit_frozenError($page, $edit_txt);
    if ($frozen_error !== '') {
        kona3_edit_err($frozen_error, $i_mode, 'frozen');
        return "";
    }

    // ページ名の文字数制限を確認する (issue #133)
    // 長すぎるページ名は既存ページでも保存できない (編集画面を開いた時点でも警告する)。
    // ただし本文を空にしてページを削除する操作は、ファイルを作らないので許可する
    if (trim($edit_txt) !== '') {
        $page_name_error = kona3getPageNameTooLongMessage($page);
        if ($page_name_error !== '') {
            kona3_edit_err($page_name_error, $i_mode, $postId);
            return "";
        }
    }

    // 改行コードを設定に基づいて正規化 (issue #251)
    // (ブラウザのtextareaは送信時にLFをCRLFに変えてしまうため)
    $edit_txt = kona3_normalizeNewlineCode($edit_txt, $fname);

    $result = FALSE;
    // check hash
    if ($a_hash_frm !== $a_hash) { // conflict
        return kona3_conflict($edit_txt, $txt, $i_mode);
    }

    // エイリアス同期のための情報を事前取得
    require_once dirname(__FILE__) . '/show.inc.php';
    $old_alias_target = kona3show_find_alias_target($txt);
    $new_alias_target = kona3show_find_alias_target($edit_txt);
    // save
    // === for FILE ===
    if (file_exists($fname)) {
        if (!is_writable($fname)) {
            kona3_edit_err(lang('Could not write file.'), $i_mode, $postId);
            return "";
        }
    } else {
        $dirname = dirname($fname);
        if (file_exists($dirname)) {
            if (!is_writable(dirname($fname))) {
                kona3_edit_err(lang('Could not write file.'), $i_mode);
                return "";
            }
        } else {
            // auto mkdir ?
            $data_dir = KONA3_DIR_DATA;
            $max_level = $kona3conf['path_max_mkdir'];
            if ($data_dir != substr($dirname, 0, strlen($data_dir))) {
                kona3_edit_err('Invalid File Path.', $i_mode);
                exit;
            }
            $dirname2 = substr($dirname, strlen($data_dir) + 1);
            $cnt = count(explode("/", $dirname2));
            // check directories level
            if ($cnt > $max_level) {
                if ($max_level == 0) {
                    kona3_edit_err(lang("Invalid Wiki Name: not allow use '/'"), $i_mode, $postId);
                    exit;
                }
                kona3_edit_err(
                    sprintf(
                        lang("Invalid Wiki Name: not allow use '/' over %s times"),
                        $max_level
                    ),
                    $i_mode,
                    $postId
                );
                exit;
            }
            // get dir mode
            $dir_mode = @octdec($kona3conf['chmod_mkdir']);
            if ($dir_mode == 0) {
                kona3_edit_err('Invalid value: chmod_mkdir in config', $i_mode, $postId);
                exit;
            }
            // mkdir
            $b = @mkdir($dirname, $dir_mode, TRUE);
            if (!$b) {
                kona3_edit_err('mkdir failed.', $i_mode, $postId);
                exit;
            }
        }
    }
    // remove file?
    // テキストが空白ならファイルを削除
    if (trim($edit_txt) === "") {
        // remove
        @unlink($fname);
        if ($old_fname !== '' && file_exists($old_fname)) {
            @unlink($old_fname);
        }

        kona3db_writePage($page, trim($edit_txt), $user_id, $tags);
    } else {
        // write
        $b = kona3lock_save($fname, $edit_txt);
        if ($b === FALSE) {
            $msg = lang('Could not write file.');
            kona3_edit_err($msg, $i_mode, $postId);
            $result = FALSE;
            return $msg;
        }
        if ($old_fname !== '' && $old_fname !== $fname && file_exists($old_fname)) {
            @unlink($old_fname);
        }

        // === for Database ===
        kona3db_writePage($page, $edit_txt, $user_id, $tags);
    }

    // === hook ===
    // 元の edit_ext が直接編集できる拡張子の場合だけ、旧拡張子として扱う (#53)
    $orig_edit_ext = kona3param('edit_ext', '');
    $old_ext = ($orig_edit_ext !== '' && kona3isEditableExt($orig_edit_ext) && $orig_edit_ext !== $edit_ext) ? $orig_edit_ext : '';
    try {
        kona3triggerHook('write', $page, $edit_txt, [
            'i_mode' => $i_mode,
            'a_mode' => $a_mode,
            'edit_ext' => $edit_ext,
            'old_ext' => $old_ext,
            'tags' => $tags,
            'page_mode' => $page_mode,
            'old_alias_target' => $old_alias_target,
            'new_alias_target' => $new_alias_target,
        ]);
    } catch (Exception $e) {
        $msg = $e->getMessage();
        kona3_edit_err($msg, $i_mode, $postId);
        $result = FALSE;
        return $msg;
    }

    $result = TRUE;

    // 保存できたので、退避していた本文は不要 (issue #64)
    kona3edit_clearRecovery($page);

    // 静的ページの生成 (失敗しても保存は成功扱い)
    $static_warning = kona3static_onPageSaved($page);

    // result
    if ($i_mode == "git") {
        return TRUE;
    } else if ($i_mode == "ajax") {
        $json = array(
            'result' => 'ok',
            'a_hash' => kona3getPageHash($edit_txt),
            'postId' => $postId,
            'edit_ext' => $edit_ext,
        );
        if ($static_warning !== '') {
            $json['warning'] = $static_warning;
        }
        echo json_encode($json);
        return TRUE;
    }
    $jump = kona3getPageURL($page);
    header("location:$jump");
    echo "ok, saved.";
    return TRUE;
}

function kona3_trygit(&$txt, &$a_hash, $i_mode)
{
    global $kona3conf, $page;
    $edit_txt = kona3param('edit_txt', '');
    $edit_ext = kona3param('edit_ext', '');
    $edit_ext = kona3edit_normalizeEditExt($page, $edit_ext);
    $page_mode = kona3param('page_mode', '');

    // 先に保存
    $msg = kona3_trywrite($txt, $a_hash, $i_mode, $result);
    if (!$result) {
        return $msg;
    }

    // 既存ファイルの存在確認
    $original_fname = kona3getEditFile("{$page}.{$edit_ext}", $dummy_ext);
    $has_existing_file = file_exists($original_fname);

    // page_mode に基づいて拡張子を同期
    if (!$has_existing_file) {
        if ($page_mode === 'Markdown') {
            $edit_ext = 'md';
        } else if ($page_mode === 'KonaNotation') {
            $edit_ext = 'txt';
        }
    }

    // result
    if ($i_mode == "ajax") {
        // 保存時と同じ改行コード正規化を適用したテキストでハッシュを返す (issue #251)
        $edit_txt = kona3_normalizeNewlineCode($edit_txt, $original_fname);
        echo json_encode(array(
            'result' => 'ok',
            'a_hash' => kona3getPageHash($edit_txt),
            'edit_ext' => $edit_ext,
        ));
        return TRUE;
    }
    $jump = kona3getPageURL($page);
    header("location:$jump");
    echo "ok, saved.";
    return TRUE;
}

// プレビュー用に本文を HTML へ変換する (issue #153)
// 描画中は $kona3conf['preview_mode'] を立て、副作用のあるプラグイン
// (例: #counter の加算) がプレビューでは動かないようにする。
function kona3edit_render_preview($txt, $page_mode)
{
    global $kona3conf;
    $old = isset($kona3conf['preview_mode']) ? $kona3conf['preview_mode'] : null;
    $kona3conf['preview_mode'] = true;
    try {
        if ($page_mode === 'Markdown') {
            require_once dirname(__DIR__) . '/kona3parser_md.inc.php';
            return kona3markdown_parser_convert($txt);
        }
        return konawiki_parser_convert($txt);
    } finally {
        if ($old === null) {
            unset($kona3conf['preview_mode']);
        } else {
            $kona3conf['preview_mode'] = $old;
        }
    }
}

// max_edit_size (単位: MB、0なら無制限) を本文が超えているかを返す。
// 読み込み側 (kona3edit_load) と同じくバイト換算で比較すること。
// MB の数値とバイト長を直接比べると数バイトで常に超過になる。
function kona3edit_is_too_large($txt)
{
    global $kona3conf;
    $max = isset($kona3conf['max_edit_size']) ? intval($kona3conf['max_edit_size']) : 0;
    if ($max <= 0) {
        return false;
    }
    return strlen($txt) > $max * 1024 * 1024;
}

function kona3edit_preview_ajax($page)
{
    // kona3edit_ai_ajax と同様、この時点で権限チェックを通過している
    if (!kona3edit_checkEditToken($page, 'ajax')) {
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    global $kona3conf;
    $txt = kona3param('edit_txt', '');
    $page_mode = kona3param('page_mode', '');
    if (kona3edit_is_too_large($txt)) {
        kona3_edit_err('Text is too large.', 'ajax');
        return;
    }
    // 表示画面と描画環境を揃える (page_ext に依存するプラグイン向け)
    kona3setConf('page_ext', $page_mode === 'Markdown' ? '.md' : '.txt');
    $html = kona3edit_render_preview($txt, $page_mode);
    echo json_encode(array('result' => 'ok', 'html' => $html));
}

function kona3edit_ai_ajax($page)
{
    // この時点で既に認証を通過しているので安心して応答を返して良い
    if (!kona3edit_checkEditToken($page, 'ajax')) {
        return;
    }

    header('Content-Type: application/json');
    $ai_settings = kona3edit_ai_get_provider_settings();
    $ai_provider = $ai_settings['provider'];
    $apikey = $ai_settings['apikey'];

    if ($apikey == '') {
        echo json_encode(array(
            'result' => 'ng',
            'message' => 'AI API Key is not set.',
        ));
        return;
    }
    $a_mode = kona3param('a_mode', '');
    // run mode (プロンプトはサーバー側で組み立てる)
    if ($a_mode == 'run') {
        $params = [];
        foreach (['ai_action', 'ai_text', 'ai_selected', 'ai_before', 'ai_instruction', 'ai_user_prompt', 'ai_model'] as $key) {
            $params[$key] = (string)kona3param($key, '');
        }
        echo json_encode(kona3edit_ai_run($apikey, $ai_provider, $params));
        return;
    }
    // invalid mode
    echo json_encode(array(
        'result' => 'ng',
        'message' => 'Invalid AI mode.',
    ));
}

// AI機能の定義 (ホワイトリスト)。キーが機能ID = プロンプトファイル名
function kona3edit_ai_get_actions()
{
    return [
        'proofread' => ['label' => 'AI Proofread', 'input' => 'all',      'output' => 'replace_list'],
        'rephrase'  => ['label' => 'AI Rephrase',  'input' => 'selected', 'output' => 'candidates'],
        'append'    => ['label' => 'AI Append',    'input' => 'before',   'output' => 'text'],
        'quality'   => ['label' => 'AI Quality',   'input' => 'all',      'output' => 'quality'],
        'improve'   => ['label' => 'AI Improve',   'input' => 'all',      'output' => 'suggestions'],
    ];
}

// template/prompt/{lang}/{action}.md を読み込む。無い言語は en → ja にフォールバック
function kona3edit_ai_load_prompt($action, $lang)
{
    if (!is_string($action) || !preg_match('/^[a-z_]+$/', $action)) {
        return FALSE;
    }
    if (!array_key_exists($action, kona3edit_ai_get_actions())) {
        return FALSE;
    }
    if (!is_string($lang) || !preg_match('/^[a-z]+$/', $lang)) {
        $lang = 'en';
    }
    foreach (array_unique([$lang, 'en', 'ja']) as $code) {
        $file = KONA3_DIR_ENGINE . "/template/prompt/{$code}/{$action}.md";
        if (!is_file($file)) {
            continue;
        }
        $prompt = @file_get_contents($file);
        if ($prompt !== FALSE && trim($prompt) !== '') {
            return $prompt;
        }
    }
    return FALSE;
}

// プロンプトの置換変数を展開する
// $vars のキー: text, text_selected, text_before, instruction
function kona3edit_ai_build_prompt($template, $vars)
{
    $fence = function ($s) {
        $s = str_replace('```', '\\`\\`\\`', (string)$s);
        return "```\n" . $s . "\n```";
    };
    $get = function ($key) use ($vars) {
        return isset($vars[$key]) ? (string)$vars[$key] : '';
    };
    $instruction = trim(preg_replace('/\s*[\r\n]+\s*/u', ' ', $get('instruction')));
    $instruction = trim(mb_substr($instruction, 0, 200));
    if ($instruction === '') {
        $instruction = lang('AI rephrase default');
    }
    // strtr は1回の走査で置換するので、本文中の __TEXT__ 等が再展開されることはない
    return strtr($template, [
        '__TEXT_SELECTED__' => $fence($get('text_selected')),
        '__TEXT_BEFORE__' => $fence($get('text_before')),
        '__TEXT__' => $fence($get('text')),
        '__INSTRUCTION__' => $instruction,
    ]);
}

// data/ai_prompt.md を解析する ('# 名前' 〜 '-----')。['名前' => '本文', ...]
function kona3edit_ai_parse_user_prompts($text)
{
    $text = str_replace(["\r\n", "\r"], "\n", (string)$text);
    $result = [];
    foreach (preg_split('/^-{5,}[ \t]*$/m', $text) as $chunk) {
        $chunk = trim($chunk, "\n");
        if (!preg_match('/^#[ \t]+(.+?)[ \t]*(?:\n|$)/', ltrim($chunk, "\n"), $m)) {
            continue;
        }
        $name = $m[1];
        $body = trim(substr(ltrim($chunk, "\n"), strlen($m[0])));
        if ($body === '' || isset($result[$name])) {
            continue;
        }
        $result[$name] = $body;
    }
    return $result;
}

// AIの応答からJSON(配列/オブジェクト)を取り出す。失敗時は null
function kona3edit_ai_extract_json($msg)
{
    $msg = trim((string)$msg);
    $candidates = [];
    if (preg_match('/```[ \t]*(?:json)?[ \t]*\n?(.*?)```/si', $msg, $m)) {
        $candidates[] = trim($m[1]);
    }
    $candidates[] = $msg;
    if (preg_match('/(\[.*\]|\{.*\})/s', $msg, $m)) {
        $candidates[] = $m[1];
    }
    foreach ($candidates as $code) {
        if ($code === '') {
            continue;
        }
        $data = json_decode($code, TRUE);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }
    }
    return null;
}

// 行の別名キーを ng/ok/desc に正規化する (edit_ai_core.js の aiNormalizeJsonRow と同じ別名)
// 空の判定は呼び出し側で行う。配列でなければ null
function kona3edit_ai_normalize_row($row)
{
    if (!is_array($row)) {
        return null;
    }
    $pick = function ($keys) use ($row) {
        foreach ($keys as $key) {
            if (isset($row[$key]) && !is_array($row[$key])) {
                return (string)$row[$key];
            }
        }
        return '';
    };
    return [
        'ng' => $pick(['ng', 'before', 'old', 'original', 'source', 'ErrorLocation', 'error', '誤り', '修正前', '対象']),
        'ok' => $pick(['ok', 'after', 'new', 'replacement', 'Correction', 'correction', '修正', '修正後', '言い換え']),
        'desc' => $pick(['desc', 'description', 'Reason', 'reason', '理由', '説明']),
    ];
}

// 文字列配列へ正規化
function kona3edit_ai_to_string_list($v)
{
    if (is_string($v)) {
        $v = trim($v) === '' ? [] : [$v];
    }
    if (!is_array($v)) {
        return [];
    }
    $list = [];
    foreach ($v as $item) {
        if (is_scalar($item) && trim((string)$item) !== '') {
            $list[] = (string)$item;
        }
    }
    return $list;
}

// AIの応答を出力契約 (type + items|text|quality) へ正規化する
// $output: replace_list / candidates / text / quality / suggestions / user
function kona3edit_ai_parse_result($output, $msg)
{
    $text_result = ['type' => 'text', 'text' => trim((string)$msg)];
    if ($output === 'text') {
        return $text_result;
    }
    $data = kona3edit_ai_extract_json($msg);
    if ($data === null) {
        return $text_result;
    }
    if ($output === 'quality') {
        if (array_values($data) === $data) { // オブジェクトではない
            return $text_result;
        }
        $score = isset($data['score']) && is_numeric($data['score']) ? intval($data['score']) : 0;
        return ['type' => 'quality', 'quality' => [
            'score' => max(0, min(100, $score)),
            'summary' => isset($data['summary']) && is_scalar($data['summary']) ? trim((string)$data['summary']) : '',
            'good' => kona3edit_ai_to_string_list(isset($data['good']) ? $data['good'] : []),
            'bad' => kona3edit_ai_to_string_list(isset($data['bad']) ? $data['bad'] : []),
        ]];
    }
    // 以降は行の配列。単一オブジェクトなら1行として扱う
    $rows = (array_values($data) === $data) ? $data : [$data];
    $items = [];
    if ($output === 'suggestions') {
        foreach ($rows as $row) {
            $n = kona3edit_ai_normalize_row($row);
            if ($n === null) {
                continue;
            }
            $point = '';
            foreach (['point', 'title', 'suggestion', '改善点'] as $key) {
                if (isset($row[$key]) && is_scalar($row[$key])) {
                    $point = (string)$row[$key];
                    break;
                }
            }
            $detail = '';
            foreach (['detail', 'details', 'desc', 'description', 'reason', '詳細', '説明'] as $key) {
                if (isset($row[$key]) && is_scalar($row[$key])) {
                    $detail = (string)$row[$key];
                    break;
                }
            }
            if ($point === '' && $detail === '') {
                continue;
            }
            $items[] = ['point' => $point, 'detail' => $detail, 'ng' => $n['ng'], 'ok' => $n['ok']];
        }
        return ['type' => 'suggestions', 'items' => $items];
    }
    if ($output === 'candidates') {
        foreach ($rows as $row) {
            $n = kona3edit_ai_normalize_row($row);
            if ($n === null || $n['ok'] === '') {
                continue;
            }
            $items[] = ['ok' => $n['ok'], 'desc' => $n['desc']];
        }
        return ['type' => 'candidates', 'items' => array_slice($items, 0, 3)];
    }
    // replace_list / user
    foreach ($rows as $row) {
        $n = kona3edit_ai_normalize_row($row);
        if ($n === null || $n['ng'] === '' || $n['ok'] === '' || $n['ng'] === $n['ok']) {
            continue;
        }
        $items[] = $n;
    }
    if ($output === 'user' && count($items) === 0) {
        return $text_result;
    }
    return ['type' => 'replace_list', 'items' => $items];
}

// AI機能を実行して結果配列を返す (echo しない)。プロンプト本文は結果に含めない
function kona3edit_ai_run($apikey, $provider, $params, $asker = 'chatgpt_ask')
{
    $ng = function ($message) {
        return ['result' => 'ng', 'message' => $message, 'token' => 0];
    };
    $p = function ($key) use ($params) {
        return isset($params[$key]) ? (string)$params[$key] : '';
    };
    $action = $p('ai_action');
    $text = $p('ai_text');
    $selected = $p('ai_selected');
    $before = $p('ai_before');
    if (kona3edit_is_too_large($text) || kona3edit_is_too_large($selected)) {
        return $ng('Text is too large.');
    }
    $before = mb_strlen($before) > 4000 ? mb_substr($before, -4000) : $before;
    $vars = [
        'text' => $text,
        'text_selected' => $selected,
        'text_before' => $before,
        'instruction' => $p('ai_instruction'),
    ];
    $output = 'user';
    if ($action === 'user') {
        $name = $p('ai_user_prompt');
        $user_file = KONA3_DIR_DATA . "/ai_prompt.md";
        $user_prompts = file_exists($user_file)
            ? kona3edit_ai_parse_user_prompts((string)@file_get_contents($user_file)) : [];
        if ($name === '' || !isset($user_prompts[$name])) {
            return $ng('AI template not found: ' . $name);
        }
        $template = $user_prompts[$name];
        if (trim($text) === '' && trim($selected) === '') {
            return $ng('Input text is empty.');
        }
        if ($vars['text_selected'] === '') {
            $vars['text_selected'] = $text;
        }
    } else {
        $actions = kona3edit_ai_get_actions();
        if (!array_key_exists($action, $actions)) {
            return $ng('Invalid AI action.');
        }
        $output = $actions[$action]['output'];
        $input = $actions[$action]['input'];
        $target = ($input === 'before') ? $before : $text;
        if (trim($target) === '') {
            return $ng('Input text is empty.');
        }
        $template = kona3edit_ai_load_prompt($action, kona3getLangCode());
        if ($template === FALSE) {
            return $ng('AI prompt not found.');
        }
    }
    $prompt = kona3edit_ai_build_prompt($template, $vars);
    $model = kona3edit_ai_get_validated_model($p('ai_model'), $provider);
    $basic_instruction = kona3getConf('openai_api_basic_instruction', 'You are helpful AI assitant.');
    $messages = chatgpt_messages_init($basic_instruction, $prompt);
    list($msg, $token) = $asker($messages, $apikey, $model, 0, [], $provider, 90);
    if ($token === 0 && strpos((string)$msg, 'Error:') === 0) {
        return $ng($msg);
    }
    $result = kona3edit_ai_parse_result($output, $msg);
    return ['result' => 'ok', 'action' => $action, 'token' => $token] + $result;
}

function kona3ai_edit_template()
{
    // check ai_prompt.md
    $prompt_file = KONA3_DIR_DATA . "/ai_prompt.md";
    if (!file_exists($prompt_file)) {
        // copy template
        $template = <<<EOS
# prompt name1
### Instruction:
(prompt here)
### Input:
__TEXT__
-----
# prompt name2
### Instruction:
(prompt here)
### Input:
__TEXT__
-----
# 英語に翻訳
### Instruction:
Translate the following text into English. Output only the translation.
### Input:
__TEXT_SELECTED__
-----
# 要約
### Instruction:
Summarize the following text in a few sentences, in the same language as the text.
### Input:
__TEXT__
-----
# USER_PROMPT1
### Instruction:
(prompt here)
### Input:
__TEXT__
-----
# USER_PROMPT2
### Instruction:
(prompt here)
### Input:
__TEXT__
-----
EOS;
        file_put_contents($prompt_file, $template);
    }
    // jump to edit
    $url = kona3getPageURL('ai_prompt', 'edit', '', "");
    kona3jump($url, 'Show AI Prompt');
}

function kona3edit_ai_get_allowed_models()
{
    $confItems = kona3conf_getConfigItems();
    return isset($confItems['AI']['openai_apikey_model']['items'])
        ? $confItems['AI']['openai_apikey_model']['items']
        : ['gpt-6.1-sol', 'gpt-6-astra', 'gpt-6-sol', 'gpt-6-luna', 'gpt-5.6-sol', 'gpt-5.6-terra', 'gpt-5.6-luna', 'gpt-5.5', 'gpt-5.4-mini'];
}

function kona3edit_ai_get_default_model()
{
    // 設定値が現行の選択肢に含まれない場合（旧モデル名が設定ファイルに残っている場合）は、
    // kona3getConf() が生の値を返すため、そのままでは無効なモデルを使ってしまう。
    // そのため、ここで選択肢に含まれる場合だけ設定値を採用し、それ以外は定義側のデフォルトを使う。
    $confItems = kona3conf_getConfigItems();
    $default = isset($confItems['AI']['openai_apikey_model']['default'])
        ? $confItems['AI']['openai_apikey_model']['default']
        : 'gpt-6-luna';
    $model = kona3getConf('openai_apikey_model', '');
    if ($model !== '' && in_array($model, kona3edit_ai_get_allowed_models(), TRUE)) {
        return $model;
    }
    return $default;
}

function kona3edit_ai_get_validated_model($ai_model, $provider)
{
    if ($ai_model === '') {
        if ($provider === "OpenRouter") {
            $ai_model = kona3getConf('openrouter_model', '');
            if ($ai_model === '') {
                $ai_model = 'openrouter/free';
            }
        } else {
            $ai_model = kona3edit_ai_get_default_model();
        }
    } else {
        // OpenAIプロバイダーの場合、モデル名がホワイトリストに含まれるか検証する
        if ($provider === "OpenAI") {
            if (!in_array($ai_model, kona3edit_ai_get_allowed_models(), TRUE)) {
                $ai_model = kona3edit_ai_get_default_model();
            }
        }
    }
    return $ai_model;
}

/**
 * 古いエイリアスターゲットと新しいエイリアスターゲットのメタデータを同期する
 */
function kona3edit_sync_aliases($page, $old_alias_target, $new_alias_target)
{
    if ($old_alias_target === $new_alias_target) {
        return;
    }
    // 古いターゲットからこのページを削除
    if ($old_alias_target !== FALSE) {
        $old_meta = kona3db_loadPageMeta($old_alias_target);
        if (is_array($old_meta) && isset($old_meta['aliases']) && is_array($old_meta['aliases'])) {
            $old_meta['aliases'] = array_values(array_filter($old_meta['aliases'], function($v) use ($page) {
                return $v !== $page;
            }));
            kona3db_savePageMeta($old_alias_target, $old_meta);
        }
    }
    // 新しいターゲットにこのページを追加
    if ($new_alias_target !== FALSE) {
        $new_meta = kona3db_loadPageMeta($new_alias_target);
        if ($new_meta === null) {
            $new_meta = [];
        }
        if (!isset($new_meta['aliases']) || !is_array($new_meta['aliases'])) {
            $new_meta['aliases'] = [];
        }
        if (!in_array($page, $new_meta['aliases'])) {
            $new_meta['aliases'][] = $page;
            kona3db_savePageMeta($new_alias_target, $new_meta);
        }
    }
}

function kona3edit_ai_get_provider_settings()
{
    $ai_provider = kona3getConf('openai_provider', 'none');
    $openai_key = kona3getConf('openai_apikey', '');
    $openrouter_key = kona3getConf('openrouter_apikey', '');
    $apikey = '';
    $default_model = '';

    // 後方互換性: プロバイダー未指定でもAPIキーから推定する
    if ($ai_provider === 'none') {
        if ($openai_key !== '') {
            $ai_provider = 'OpenAI';
        } else if ($openrouter_key !== '') {
            $ai_provider = 'OpenRouter';
        }
    }
    if ($ai_provider === 'OpenAI') {
        $apikey = $openai_key;
        $default_model = kona3edit_ai_get_default_model();
    } else if ($ai_provider === 'OpenRouter') {
        $apikey = $openrouter_key;
        $default_model = kona3getConf('openrouter_model', '');
    }

    return [
        'provider' => $ai_provider,
        'apikey' => $apikey,
        'enabled' => ($apikey !== ''),
        'default_model' => $default_model,
    ];
}
