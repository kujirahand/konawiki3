<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/action/edit.inc.php';

// --- 機能定義 ---
$actions = kona3edit_ai_get_actions();
test_eq(__LINE__, array_keys($actions), ['proofread', 'rephrase', 'append', 'quality', 'improve'], "機能は5つ");
test_eq(__LINE__, $actions['proofread']['output'], 'replace_list', "proofread の出力種別");
test_eq(__LINE__, $actions['rephrase']['output'], 'candidates', "rephrase の出力種別");
test_eq(__LINE__, $actions['append']['output'], 'text', "append の出力種別");
test_eq(__LINE__, $actions['quality']['output'], 'quality', "quality の出力種別");
test_eq(__LINE__, $actions['improve']['output'], 'suggestions', "improve の出力種別");

// --- プロンプトファイル ---
foreach (['ja', 'en'] as $lang) {
    foreach (array_keys($actions) as $id) {
        $file = KONA3_DIR_ENGINE . "/template/prompt/{$lang}/{$id}.md";
        $body = file_exists($file) ? file_get_contents($file) : '';
        test_assert(__LINE__, trim($body) !== '', "prompt {$lang}/{$id}.md が存在し非空");
        $var = ($id === 'append') ? '__TEXT_BEFORE__' : '__TEXT__';
        test_assert(__LINE__, strpos($body, $var) !== false, "prompt {$lang}/{$id}.md に {$var}");
        if ($id === 'rephrase') {
            test_assert(__LINE__, strpos($body, '__INSTRUCTION__') !== false, "prompt {$lang}/rephrase.md に __INSTRUCTION__");
        }
    }
}
test_assert(__LINE__, file_exists(KONA3_DIR_ENGINE . '/template/prompt/README.md'), "prompt/README.md が存在");

// --- kona3edit_ai_load_prompt ---
$ja = kona3edit_ai_load_prompt('proofread', 'ja');
$en = kona3edit_ai_load_prompt('proofread', 'en');
test_assert(__LINE__, is_string($ja) && is_string($en) && $ja !== $en, "proofread ja/en は別内容");
test_eq(__LINE__, kona3edit_ai_load_prompt('proofread', 'fr'), $en, "未対応言語は en にフォールバック");
foreach (['../edit', 'proofread/../x', '', 'PROOFREAD', 'user', 'load_template'] as $bad) {
    test_eq(__LINE__, kona3edit_ai_load_prompt($bad, 'ja'), false, "不正なID '{$bad}' は FALSE");
}
test_assert(__LINE__, strpos($ja, 'ng には本文中に実在する文字列') !== false, "proofread(ja) は ng を実在文字列にする指示を含む");

// --- kona3edit_ai_build_prompt ---
$p = kona3edit_ai_build_prompt("A\n__TEXT__\nB", ['text' => 'hello']);
test_eq(__LINE__, $p, "A\n```\nhello\n```\nB", "__TEXT__ をフェンス付きで置換");
$p = kona3edit_ai_build_prompt("__TEXT__", ['text' => "a ``` b"]);
test_assert(__LINE__, strpos($p, 'a \\`\\`\\` b') !== false, "本文中の ``` はエスケープ");
$p = kona3edit_ai_build_prompt("X __TEXT__ __TEXT_SELECTED__ __TEXT_BEFORE__ __INSTRUCTION__", ['text' => 'a']);
test_eq(__LINE__, preg_match('/__[A-Z_]+__/', $p), 0, "未指定変数が残らない");
$p = kona3edit_ai_build_prompt("__TEXT__ / __TEXT_SELECTED__", ['text' => '__TEXT_SELECTED__', 'text_selected' => 'S']);
test_assert(__LINE__, strpos($p, "```\n__TEXT_SELECTED__\n```") !== false, "本文中の変数名は再展開されない");
$p = kona3edit_ai_build_prompt("__INSTRUCTION__", ['instruction' => "丁寧に\n簡潔に"]);
test_eq(__LINE__, $p, "丁寧に 簡潔に", "instruction の改行は空白化");
$p = kona3edit_ai_build_prompt("__INSTRUCTION__", ['instruction' => str_repeat('あ', 300)]);
test_eq(__LINE__, mb_strlen($p), 200, "instruction は200文字まで");
// lang() は include_once 経由で、他テストが読み込み済みだと再読込されないため、
// 言語ファイルを直接 include して $lang_data を確定させる (hamburger_menu.test.php と同じ方法)
global $lang_data;
include KONA3_DIR_ENGINE . '/lang/en.inc.php';
test_assert(__LINE__, isset($lang_data['AI rephrase default']), "en: lang キー AI rephrase default が定義済み");
include KONA3_DIR_ENGINE . '/lang/ja.inc.php';
test_assert(__LINE__, isset($lang_data['AI rephrase default']), "ja: lang キー AI rephrase default が定義済み");
$p = kona3edit_ai_build_prompt("__INSTRUCTION__", ['instruction' => '  ']);
test_eq(__LINE__, $p, lang('AI rephrase default'), "空 instruction は既定文言");
test_assert(__LINE__, lang('AI rephrase default') !== 'AI rephrase default', "lang キー AI rephrase default が定義済み");

// --- kona3edit_ai_extract_json ---
$expect = [['ng' => 'a', 'ok' => 'b']];
test_eq(__LINE__, kona3edit_ai_extract_json("```json\n[{\"ng\":\"a\",\"ok\":\"b\"}]\n```"), $expect, "```json ブロック");
test_eq(__LINE__, kona3edit_ai_extract_json("```\n[{\"ng\":\"a\",\"ok\":\"b\"}]\n```"), $expect, "言語なしブロック");
test_eq(__LINE__, kona3edit_ai_extract_json('[{"ng":"a","ok":"b"}]'), $expect, "素の配列");
test_eq(__LINE__, kona3edit_ai_extract_json("結果です:\n[{\"ng\":\"a\",\"ok\":\"b\"}]\n以上"), $expect, "前置きつき");
test_eq(__LINE__, kona3edit_ai_extract_json('[{"ng": '), null, "壊れたJSONは null");
test_eq(__LINE__, kona3edit_ai_extract_json('ただの文章'), null, "JSONなしは null");

// --- kona3edit_ai_parse_result ---
$r = kona3edit_ai_parse_result('replace_list', '[{"ErrorLocation":"あ","Correction":"い","Reason":"r"},{"ng":"","ok":"x"},{"ng":"same","ok":"same"}]');
test_eq(__LINE__, $r['type'], 'replace_list', "replace_list の type");
test_eq(__LINE__, $r['items'], [['ng' => 'あ', 'ok' => 'い', 'desc' => 'r']], "別名キー正規化・空/同一は除外");
$r = kona3edit_ai_parse_result('quality', '{"score":"85","summary":"s","good":"g","bad":["b1","b2"]}');
test_eq(__LINE__, $r['quality'], ['score' => 85, 'summary' => 's', 'good' => ['g'], 'bad' => ['b1', 'b2']], "quality の正規化");
$r = kona3edit_ai_parse_result('quality', '{"score":150}');
test_eq(__LINE__, $r['quality']['score'], 100, "score は100に clamp");
$r = kona3edit_ai_parse_result('quality', '{"score":-1,"good":null}');
test_eq(__LINE__, $r['quality']['score'], 0, "score は0に clamp");
test_eq(__LINE__, $r['quality']['good'], [], "good が非配列でも配列化");
$r = kona3edit_ai_parse_result('candidates', '[{"ok":"1"},{"ok":"2"},{"ok":"3"},{"ok":"4"}]');
test_eq(__LINE__, count($r['items']), 3, "candidates は最大3件");
test_eq(__LINE__, $r['type'], 'candidates', "candidates の type");
$r = kona3edit_ai_parse_result('suggestions', '[{"point":"p","detail":"d","ng":"a","ok":"b"},{"point":"p2","detail":"d2"}]');
test_eq(__LINE__, $r['items'][1], ['point' => 'p2', 'detail' => 'd2', 'ng' => '', 'ok' => ''], "suggestions は ng/ok 任意");
$r = kona3edit_ai_parse_result('text', "  続きです  \n");
test_eq(__LINE__, $r, ['type' => 'text', 'text' => '続きです'], "text は trim");
$r = kona3edit_ai_parse_result('replace_list', '解析できない応答');
test_eq(__LINE__, $r, ['type' => 'text', 'text' => '解析できない応答'], "解析失敗は text にフォールバック");
$r = kona3edit_ai_parse_result('user', '[{"ng":"a","ok":"b"}]');
test_eq(__LINE__, $r['type'], 'replace_list', "user: ng/ok があれば replace_list");
$r = kona3edit_ai_parse_result('user', 'Hello world');
test_eq(__LINE__, $r['type'], 'text', "user: それ以外は text");

// --- kona3edit_ai_parse_user_prompts ---
$u = kona3edit_ai_parse_user_prompts("# USER_PROMPT1\r\n### 指示:\r\nA\r\n### 入力:\r\n__TEXT__\r\n-----\r\n# 名前2\r\nBODY2\r\n");
test_eq(__LINE__, array_keys($u), ['USER_PROMPT1', '名前2'], "名前の切り出し (CRLF・区切りなし末尾)");
test_eq(__LINE__, $u['名前2'], 'BODY2', "区切りなし末尾の本文");
test_assert(__LINE__, strpos($u['USER_PROMPT1'], "### 指示:\nA") === 0, "本文は見出し行の次から");
test_assert(__LINE__, strpos($u['USER_PROMPT1'], '-----') === false, "本文に区切りを含まない");
test_assert(__LINE__, !isset($u['NOT_EXIST']), "存在しない名前は無い");
test_eq(__LINE__, kona3edit_ai_parse_user_prompts(''), [], "空文字");

// --- kona3edit_ai_run (偽 asker) ---
$captured = null;
$fake = function ($messages, $apikey, $model, $temp, $opt, $provider, $timeout) use (&$captured) {
    $captured = ['messages' => $messages, 'timeout' => $timeout];
    return ['[{"ng":"ほんぶん","ok":"本文","desc":"変換"}]', 12];
};
$res = kona3edit_ai_run('key', 'OpenAI', ['ai_action' => 'proofread', 'ai_text' => 'ほんぶんです'], $fake);
test_eq(__LINE__, $res['result'], 'ok', "run: proofread 成功");
test_eq(__LINE__, $res['type'], 'replace_list', "run: type");
test_eq(__LINE__, $res['token'], 12, "run: token");
test_eq(__LINE__, $captured['timeout'], 90, "run: timeout 90");
$user_msg = $captured['messages'][1]['content'];
test_assert(__LINE__, strpos($user_msg, 'ほんぶんです') !== false, "run: asker のメッセージに本文");
test_assert(__LINE__, strpos($user_msg, '実在する文字列') !== false, "run: asker のメッセージに指示文");
$json = json_encode($res, JSON_UNESCAPED_UNICODE);
test_assert(__LINE__, strpos($json, '### 指示:') === false && strpos($json, '### Instructions:') === false, "run: レスポンスにプロンプト本文なし");

$res = kona3edit_ai_run('key', 'OpenAI', ['ai_action' => 'nothing', 'ai_text' => 'x'], $fake);
test_eq(__LINE__, $res['result'], 'ng', "run: 不正 ai_action");
$res = kona3edit_ai_run('key', 'OpenAI', ['ai_action' => 'rephrase', 'ai_text' => ''], $fake);
test_eq(__LINE__, $res['result'], 'ng', "run: rephrase で ai_text 空");
$res = kona3edit_ai_run('key', 'OpenAI', ['ai_action' => 'append', 'ai_before' => '前の文脈'], $fake);
test_eq(__LINE__, $res['result'], 'ok', "run: append は ai_before で実行");
$err = function () {
    return ['Error: Unable to get response from AI.', 0];
};
$res = kona3edit_ai_run('key', 'OpenAI', ['ai_action' => 'proofread', 'ai_text' => 'x'], $err);
test_eq(__LINE__, $res['result'], 'ng', "run: asker がエラーを返したら ng");
$res = kona3edit_ai_run('key', 'OpenAI', ['ai_action' => 'user', 'ai_text' => 'x', 'ai_user_prompt' => 'NO_SUCH_PROMPT_XYZ'], $fake);
test_eq(__LINE__, $res['result'], 'ng', "run: 存在しないひな形名は ng");

// ユーザーひな形
$user_file = KONA3_DIR_DATA . '/ai_prompt.md';
file_put_contents($user_file, "# USER_PROMPT1\n### 指示:\n翻訳せよ\n### 入力:\n__TEXT__\n-----\n");
$res = kona3edit_ai_run('key', 'OpenAI', ['ai_action' => 'user', 'ai_text' => 'こんにちは', 'ai_user_prompt' => 'USER_PROMPT1'], $fake);
test_eq(__LINE__, $res['result'], 'ok', "run: user ひな形で成功");
test_assert(__LINE__, strpos($captured['messages'][1]['content'], '翻訳せよ') !== false, "run: user ひな形が使われる");
test_eq(__LINE__, $res['type'], 'replace_list', "run: user で ng/ok が取れれば replace_list");
@unlink($user_file);

// --- 旧経路 (load_template / ask) が無いこと ---
$edit_action_src = file_get_contents(KONA3_DIR_ENGINE . '/action/edit.inc.php');
test_assert(__LINE__, strpos($edit_action_src, "'load_template'") === false, "edit.inc.php に load_template 分岐が無い");
test_assert(__LINE__, strpos($edit_action_src, "'ask'") === false, "edit.inc.php に ask 分岐が無い");
test_assert(__LINE__, strpos($edit_action_src, 'function kona3edit_ai_load_template') === false, "kona3edit_ai_load_template が無い");
test_assert(__LINE__, strpos($edit_action_src, 'function kona3edit_ai_ask(') === false, "kona3edit_ai_ask が無い");
test_assert(__LINE__, strpos($edit_action_src, "'run'") !== false, "edit.inc.php に run 分岐がある");
test_assert(__LINE__, !file_exists(KONA3_DIR_ENGINE . '/lang/ja-ai_prompt.md'), "lang/ja-ai_prompt.md が存在しない");
test_assert(__LINE__, !file_exists(KONA3_DIR_ENGINE . '/lang/en-ai_prompt.md'), "lang/en-ai_prompt.md が存在しない");
test_assert(__LINE__, strpos($edit_action_src, '# 英語に翻訳') !== false && strpos($edit_action_src, '# 要約') !== false, "ひな形の初期サンプルに翻訳・要約がある");

// --- edit.html の構造 ---
$tpl = file_get_contents(KONA3_DIR_ENGINE . '/template/edit.html');
foreach (['id="ai_actions"', 'aiRunAction(', 'id="ai_rephrase_instruction"', 'id="ai_undo_btn"', 'locale__apply_all',
          'locale__insert', 'locale__select_range', 'locale__applied_n', 'locale__not_found_n', 'id="ai_user_actions"'] as $needle) {
    test_assert(__LINE__, strpos($tpl, $needle) !== false, "edit.html に {$needle}");
}
foreach (['id="ai_input_text"', 'id="ai_template_select"', 'id="ai_ask_btn"'] as $needle) {
    test_assert(__LINE__, strpos($tpl, $needle) === false, "edit.html に {$needle} が無い");
}

// --- edit.js / edit_ai_core.js の構造 ---
$js = file_get_contents(KONA3_DIR_ENGINE . '/resource/edit.js');
foreach (['function aiRunAction(', 'function aiApplyAll(', 'function aiSetEditorText(', 'function aiUndo(', 'function aiRunUserPrompt('] as $needle) {
    test_assert(__LINE__, strpos($js, $needle) !== false, "edit.js に {$needle}");
}
foreach (["'load_template'", 'getSpellCheckPrompt', '### 指示'] as $needle) {
    test_assert(__LINE__, strpos($js, $needle) === false, "edit.js に {$needle} が無い");
}
$core_file = KONA3_DIR_ENGINE . '/resource/edit_ai_core.js';
test_assert(__LINE__, file_exists($core_file), "edit_ai_core.js が存在する");
$core = file_exists($core_file) ? file_get_contents($core_file) : '';
test_assert(__LINE__, strpos($core, 'module.exports') !== false, "edit_ai_core.js に module.exports");
test_assert(__LINE__, strpos($core, 'document.') === false, "edit_ai_core.js は document. を使わない");
$pos_core = strpos($edit_action_src, 'edit_ai_core.js');
$pos_edit = strpos($edit_action_src, "'edit.js'");
test_assert(__LINE__, $pos_core !== false && $pos_edit !== false && $pos_core < $pos_edit, "edit_ai_core.js は edit.js より前に登録される");

echo "edit_ai_actions.test.php: ALL OK\n";
