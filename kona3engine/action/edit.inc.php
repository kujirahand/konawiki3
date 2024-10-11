<?php

include_once dirname(__DIR__) . '/kona3lib.inc.php';
include_once dirname(__DIR__) . '/kona3db.inc.php';
include_once dirname(__DIR__) . '/kona3parser.inc.php';
include_once dirname(__DIR__) . '/kona3ai.inc.php';

header('X-Frame-Options: SAMEORIGIN');

function kona3_action_edit() {
    global $kona3conf, $page;

    $ext = "txt";
    $page = $kona3conf["page"];
    $action = kona3getPageURL($page, "edit");
    $a_mode = kona3param('a_mode', '');
    $i_mode = kona3param('i_mode', 'form'); // or ajax
    $q = kona3param("q");
    $cmd = kona3param("cmd");
    $git_enabled = $kona3conf["git_enabled"];
    $page_id = kona3db_getPageId($page, FALSE);

    // check permission
    if (!kona3edit_checkPermission($page, $i_mode)) { return; }
    // check edit_token
    if (!kona3edit_checkEditToken($page, $i_mode)) { return; }

    // generate edit_token ... (memo) 強制的に更新しないことで不要な書き込みエラーを防ぐ
    $edit_token = kona3_getEditToken($page, FALSE);

    // check $cmd or $q
    // edit_command ?
    if ($cmd != '') { return edit_command($cmd); }
    // AI mode
    if ($q == 'ai') { return kona3edit_ai(); }
    if ($q == 'ai_edit_template') { return kona3ai_edit_template(); }

    // load body
    $txt = "";
    if ($q == 'history') {
        // history mode
        $history_id = kona3param("history_id");
        $r = kona3db_getPageHistoryById($history_id);
        $txt = isset($r['body']) ? $r['body'] : '(empty)';
    } else {
        // normal mode
        $fname = kona3getEditFile($page, $ext);
        if (!file_exists($fname)) {
            $fname = kona3getWikiFile($page, FALSE, '');
        }
        if (file_exists($fname)) {
            $txt = @file_get_contents($fname);
            // check filesize
            $sz = @filesize($fname);
            $max_edit_size = intval(kona3getConf('max_edit_size', '0'));
            if ($max_edit_size > 0) {
                $max_bytes = 1024 * 1024 * $max_edit_size;
                if ($sz > $max_bytes) {
                    kona3error('File Size overflow',
                        "<h1>File size overflow</h1>".
                        "<p>max_edit_size: $max_edit_size MB</p>".
                        "<p>File size: $sz B</p>");
                    exit;
                }
            }
        }
    }
    $a_hash = kona3getPageHash($txt);

    // Check mode
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
    }
    // Ajaxならテンプレート出力しない
    if ($i_mode == 'ajax') return;

    // include script
    $kona3conf['js'][] = kona3getResourceURL('edit.js', TRUE);
    $kona3conf['css'][] = kona3getResourceURL('edit.css', TRUE);

    // history
    $history = kona3db_getPageHistory($page, $edit_token);

    // tags
    $tags = '';
    $r = db_get('SELECT * FROM tags WHERE page_id=?', [$page_id]);
    if ($r) {
      $a = [];
      foreach ($r as $i) {
        $a[] = $i['tag'];
      }
      $tags = implode('/', $a);
    }

    // new button
    $new_btn_url = kona3getPageURL($page, "new");
    $ai_enabled = (kona3getConf('openai_apikey', '') != '');
    $ai_edit_template_url = kona3getPageURL($page, "edit", "", "q=ai_edit_template&edit_token=$edit_token");

    // show
    kona3template('edit.html', array(
        "action" => $action,
        "a_hash" => $a_hash,
        "page_title" => $page,
        "edit_txt"  => $txt,
        "msg" => $msg,
        "history" => $history,
        "edit_token" => $edit_token,
        "tags" => $tags,
        "new_btn_url" => $new_btn_url,
        "ai_enabled" => $ai_enabled,
        "ai_edit_template_url" => $ai_edit_template_url,
        "edit_ext" => $ext,
    ));
}

function kona3edit_checkPermission($page, $i_mode) {
    // check permission
    if (!kona3isLogin()) {
        $please_login = lang("Please login.");
        $url = kona3getPageURL($page, 'login');
        $msg = "<a href=\"$url\">{$please_login}</a>";
        if ($i_mode == 'ajax') {
            $msg = $please_login;
        }
        kona3_edit_err($msg, $i_mode, 'nologin');
        exit;
    }
    return true;
}

function kona3edit_checkEditToken($page, $i_mode) {
    // check edit_token
    if (!kona3_checkEditToken($page)) {
        $label = lang('Edit');
        $edit_token = kona3_getEditToken($page, FALSE);
        $url = kona3getPageURL($page, 'edit', '', "edit_token=" . $edit_token);
        $page_html = htmlspecialchars($page, ENT_QUOTES);
        if ($i_mode == 'form') {
            kona3showMessage(
                $label,
                "<a href='$url' class='pure-button pure-button-primary'>" .
                    "$label - $page_html</a>"
            );
        } else {
            $postId = intval(kona3param('postId', 0));
            $edit_token = '';
            foreach ($_SESSION as $key => $val) {
                if (is_string($val)) {
                    $edit_token .= "[$key=$val]";
                }
            }
            kona3_edit_err(lang('Invalid edit token.') . "et=$edit_token", $i_mode, $postId);
        }
        exit;
    }
    return true;
}


// edit command execute
function edit_command($cmd) {
    global $kona3conf, $page;

    $page = $kona3conf["page"];
    $action = kona3getPageURL($page, "edit");

    if (!kona3isAdmin()) {
        return kona3error('Not Admin', lang('You do not have admin perm.'));
    }
    if ($cmd == 'history_delete') {
        $history_id = intval(kona3param("history_id"));
        $hash = kona3param("hash");
        $r = db_exec(
            'DELETE FROM page_history '.
            'WHERE history_id=? AND hash=?',
            [$history_id, $hash]);
        if ($r) {
            $edit_token = kona3_getEditToken($page, FALSE);
            $url = kona3getPageURL($page, "edit", "", "edit_token=$edit_token");
            return kona3showMessage(
                'DELETE History', 
                "OK! (history_id=$history_id) ".
                "<a href='$url'>Continue to edit</a>");
        } else {
            return kona3error('ng', 'Sorry, failed to delete.');
        }
    }
    kona3error('ng', 'Unknown command');
}

function kona3_make_diff($text_a, $text_b) {
    $lines_a = explode("\n", $text_a);
    $lines_b = explode("\n", $text_b);

    $res = array();
    $ia = $ib = 0;
    for (;;) {
        $a = isset($lines_a[$ia]) ? $lines_a[$ia] : NULL;
        $b = isset($lines_b[$ib]) ? $lines_b[$ib] : NULL;
        if ($a === NULL && $b === NULL) break;
        // same
        if ($a == $b) {
            $res[] = $a;
            $ia++; $ib++;
            continue;
        }
        // not same
        if ($a === NULL) {
            $res[] = '>> '.$b;
            $ib++;
            continue;
        }
        if ($b === NULL) {
            $res[] = '<< '.$a;
            $ia++;
            continue;
        }
        //
        $res[] = $a;
        $ia++;
    }
    return implode("\n", $res);
}

function kona3_edit_err($msg, $method = "web", $code = '') {
    global $page;
    if ($method == "ajax" || $method == "git") {
        echo json_encode(array(
            'result' => 'ng',
            'reason' => $msg,
            'code' => $code,
        ));
    } else {
        kona3error($page, $msg);
    }
}

function kona3_conflict($edit_txt, &$txt, $i_mode) {
    // エラーメッセージ
    $msg = lang("Conflict editing, Please submit and check.");
    // ajaxの場合
    if ($i_mode == "ajax") {
        kona3_edit_err($msg, $i_mode);
        return $msg;
    }
    // formの場合
    $msg = "<div class='error'>$msg</div>";
    $txt = kona3_make_diff($edit_txt, $txt);
    return $msg;
}

function kona3getEditFile($page, &$ext) {
    // has file ext?
    $test_ext = kona3getFileExt($page);
    if ($test_ext != '') {
        // ページ名に拡張子が含まれている場合は、そのままがファイル名である
        // ただし、セキュリティ対策のため、指定の拡張子のみ許可する
        $allow_ext_list = ['txt', 'md', 'html', 'htm', 'json', 'css', 'yaml', 'yml', 'xml', 'css', 'tsv'];
        $ext = $test_ext;
        if (!in_array($ext, $allow_ext_list)) {
            kona3_edit_err(lang('Invalid file extension.'));
            exit;
        }
        $fname = kona3getWikiFile($page, FALSE, '');
    } else {
        $fname = koan3getWikiFileText($page);
        $ext = kona3getFileExt($fname);
    }
    return $fname;
}


function kona3_trywrite(&$txt, &$a_hash, $i_mode, &$result) {
    global $kona3conf, $page;

    $edit_txt = kona3param('edit_txt', '');
    $a_hash_frm = kona3param('a_hash', '');
    $tags = kona3param('tags', '');
    $edit_ext = kona3param('edit_ext', '');
    $postId = intval(kona3param('postId', 0)); // option

    // ページ名の末便に拡張子があるか確認
    if (str_ends_with($page, ".{$edit_ext}")) {
        $page = substr($page, 0, strlen($page) - strlen(".{$edit_ext}"));
    }
    $fname = kona3getEditFile("{$page}.{$edit_ext}", $ext);
    $user_id = kona3getUserId();

    $result = FALSE;
    // check hash
    if ($a_hash_frm !== $a_hash) { // conflict
        return kona3_conflict($edit_txt, $txt, $i_mode);
    }
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
                kona3_edit_err('Invalid File Path.', $i_mode); exit;
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
                    sprintf(lang("Invalid Wiki Name: not allow use '/' over %s times"),
                    $max_level), 
                    $i_mode, $postId);
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

    // write
    $bytes = @file_put_contents($fname, $edit_txt);
    if ($bytes === FALSE) {
        $msg = lang('Could not write file.');
        kona3_edit_err($msg, $i_mode, $postId);
        $result = FALSE;
        return $msg;
    }
    // === for Database ===
    kona3db_writePage($page, $edit_txt, $user_id, $tags);
    // === discord ===
    if (kona3getConf('discord_webhook_url', '') != '') {
        kona3postDiscordWebhook($page);
    }
    
    // result
    if ($i_mode == "git") {
        $result = TRUE;
        return TRUE;
    }
    else if ($i_mode == "ajax") {
        echo json_encode(array(
            'result' => 'ok',
            'a_hash' => kona3getPageHash($edit_txt),
            'postId' => $postId,
        ));
        return TRUE;
    }
    $jump = kona3getPageURL($page);
    header("location:$jump");
    echo "ok, saved.";
    return TRUE;
}

function kona3_trygit(&$txt, &$a_hash, $i_mode) {
    require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';
    global $kona3conf, $page;

    $edit_txt = kona3param('edit_txt', '');
    $a_hash_frm = kona3param('a_hash', '');
    $fname = kona3getEditFile($page, $ext);

    // 先に保存
    kona3_trywrite($txt, $a_hash, 'git', $result);
    if (!$result) {
        return;
    }

    // Gitが有効?
    if (!$kona3conf["git_enabled"]) {
        return;
    }

    // Git操作
    try {
        $branch = $kona3conf["git_branch"];
        $remote_repository = $kona3conf["git_remote_repository"];
        $repo = new Cz\Git\GitRepository(dirname($fname));

        if ($repo->getCurrentBranchName() != $branch) {
            $repo->checkout($branch);
        }
        $repo->addFile($fname);
        if ($repo->hasChanges()) {
            $userId = kona3getUserId();
            $repo->commit("Update $page by $userId");
            $repo->push($remote_repository, array($branch));
        }
    } catch(Exception $e) {
        kona3_edit_err('Git Error:'.$e->getMessage(), $i_mode);
        exit;
    }

    // result
    if ($i_mode == "ajax") {
        echo json_encode(array(
            'result' => 'ok',
            'a_hash' => kona3getPageHash($edit_txt),
        ));
        return;
    }
    $jump = kona3getPageURL($page);
    header("location:$jump");
    echo "ok, saved.";
}

function kona3edit_ai() {
    // この時点で既に認証を通過しているので安心して応答を返して良い
    header('Content-Type: application/json');
    $apikey = kona3getConf('openai_apikey', '');
    if ($apikey == '') {
      echo json_encode(array(
            'result' => 'ng',
            'message' => 'OpenAI API Key is not set.',
        ));
        return;
    }
    $a_mode = kona3param('a_mode', '');
    // ask mode
    if ($a_mode == 'ask') {
        kona3edit_ai_ask($apikey);
        return;
    }
    // load_template
    if ($a_mode == 'load_template') {
        kona3edit_ai_load_template();
        return;
    }
    // invalid mode
    echo json_encode(array(
        'result' => 'ng',
        'message' => 'Invalid AI mode.',
    ));
}

function kona3edit_ai_load_template()
{
    // read wiki data (ai_prompt)
    // read user defined
    $prompt_file = KONA3_DIR_DATA."/ai_prompt.md";
    $prompt = file_exists($prompt_file) ? @file_get_contents($prompt_file) : '';
    // read system defined
    $lang = kona3getLangCode();
    $prompt_file = KONA3_DIR_ENGINE."/lang/{$lang}-ai_prompt.md";
    if (file_exists($prompt_file)) {
        $promptSys = file_get_contents($prompt_file);
        if ($promptSys != '') {
            $prompt .= $promptSys;
        }
    }
    //
    echo json_encode([
        'result' => 'ok',
        'message' => $prompt,
    ]);
}

function kona3ai_edit_template()
{
    // check ai_prompt.md
    $prompt_file = KONA3_DIR_DATA."/ai_prompt.md";
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
EOS;
        file_put_contents($prompt_file, $template);
    }
    // jump to edit
    $url = kona3getPageURL('ai_prompt', 'edit', '', "");
    kona3jump($url, 'Show AI Prompt');
}

function kona3edit_ai_ask($apikey)
{
    // get input text
    $ai_input_text = kona3param('ai_input_text', '');
    $ai_model = kona3getConf('openai_apikey_model', 'gpt-4o-mini');
    if ($ai_input_text == '') {
        echo json_encode(array(
            'result' => 'ng',
            'message' => 'Input text is empty.',
            'token' => 0,
        ));
        return;
    }
    // send to chatgpt
    $basic_instruction = kona3getConf('openai_api_basic_instruction', 'You are helpful AI assitant.');
    $messages = chatgpt_messages_init(
        $basic_instruction,
        $ai_input_text
    );
    list($msg, $token) = chatgpt_ask($messages, $apikey, $ai_model);
    // todo : tokenを数えて報告する
    echo json_encode(array(
        'result' => 'ok',
        'message' => $msg,
        'token' => $token,
    ));
}

