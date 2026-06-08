<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/action/edit.inc.php';

// --- AIモデル名検証機能のテスト ---

// 1. OpenAIプロバイダーで有効なモデルを指定した場合
$model = kona3edit_ai_get_validated_model('gpt-4o-mini', 'OpenAI');
test_eq(__LINE__, $model, 'gpt-4o-mini', "OpenAI: 有効なモデル 'gpt-4o-mini' はそのまま許可される");

$model = kona3edit_ai_get_validated_model('gpt-5', 'OpenAI');
test_eq(__LINE__, $model, 'gpt-5', "OpenAI: 有効なモデル 'gpt-5' はそのまま許可される");

// 2. OpenAIプロバイダーで無効なモデル（未許可のモデルや悪意あるパラメータ）を指定した場合
$model = kona3edit_ai_get_validated_model('gpt-4-expensive-model', 'OpenAI');
$default_model = kona3getConf('openai_apikey_model', 'gpt-4o-mini');
test_eq(__LINE__, $model, $default_model, "OpenAI: 無効なモデルが指定された場合はデフォルトモデル（{$default_model}）にフォールバックされる");

$model = kona3edit_ai_get_validated_model('random-string-123', 'OpenAI');
test_eq(__LINE__, $model, $default_model, "OpenAI: ランダムな文字列が指定された場合もデフォルトモデルにフォールバックされる");

// 3. OpenAIプロバイダーで空文字を指定した場合
$model = kona3edit_ai_get_validated_model('', 'OpenAI');
test_eq(__LINE__, $model, $default_model, "OpenAI: 空文字を指定した場合はデフォルトモデルが選択される");

// 4. OpenRouterプロバイダーで任意のモデルを指定した場合
$model = kona3edit_ai_get_validated_model('meta-llama/llama-3-8b-instruct:free', 'OpenRouter');
test_eq(__LINE__, $model, 'meta-llama/llama-3-8b-instruct:free', "OpenRouter: 任意のモデル名はそのまま許可される");

$model = kona3edit_ai_get_validated_model('anthropic/claude-3-opus', 'OpenRouter');
test_eq(__LINE__, $model, 'anthropic/claude-3-opus', "OpenRouter: 別の任意のモデル名も許可される");

// 5. OpenRouterプロバイダーで空文字を指定した場合のフォールバック
$model = kona3edit_ai_get_validated_model('', 'OpenRouter');
$expected_or_model = kona3getConf('openrouter_model', '');
if ($expected_or_model === '') {
    $expected_or_model = 'meta-llama/llama-3-8b-instruct:free';
}
test_eq(__LINE__, $model, $expected_or_model, "OpenRouter: 空文字指定時は設定のデフォルトかフリーモデル（{$expected_or_model}）にフォールバックされる");

$old_provider = isset($kona3conf['openai_provider']) ? $kona3conf['openai_provider'] : null;
$old_openai_key = isset($kona3conf['openai_apikey']) ? $kona3conf['openai_apikey'] : null;
$old_openrouter_key = isset($kona3conf['openrouter_apikey']) ? $kona3conf['openrouter_apikey'] : null;
$old_openrouter_model = isset($kona3conf['openrouter_model']) ? $kona3conf['openrouter_model'] : null;

$kona3conf['openai_provider'] = 'none';
$kona3conf['openai_apikey'] = '';
$kona3conf['openrouter_apikey'] = 'or-test-key';
$kona3conf['openrouter_model'] = 'openrouter/test-model';
$settings = kona3edit_ai_get_provider_settings();
test_eq(__LINE__, $settings['provider'], 'OpenRouter', "AI表示判定: provider未指定でもOpenRouterキーがあればOpenRouterとして扱う");
test_eq(__LINE__, $settings['enabled'], TRUE, "AI表示判定: OpenRouterキーのみでAI機能を有効にする");
test_eq(__LINE__, $settings['default_model'], 'openrouter/test-model', "AI表示判定: OpenRouterモデルを既定モデルにする");

$edit_template = file_get_contents(KONA3_DIR_ENGINE . '/template/edit.html');
test_assert(__LINE__, strpos($edit_template, '{{$ai_edit_conf_url}}') !== FALSE, "AI編集画面: AI設定編集リンクが表示される");
test_assert(__LINE__, strpos($edit_template, '{{\'Edit AI Config\' | lang}}') !== FALSE, "AI編集画面: AI設定編集リンクの文言が翻訳キーを使う");
test_assert(__LINE__, strpos($edit_template, 'https://kujirahand.com/konawiki3/index.php?AI') !== FALSE, "AI編集画面: AIヘルプリンクが表示される");
test_assert(__LINE__, strpos($edit_template, 'target="_blank"') !== FALSE, "AI編集画面: AIヘルプリンクは新規ウィンドウで開く");
test_assert(__LINE__, strpos($edit_template, '>？</a>') !== FALSE, "AI編集画面: AIヘルプリンクは疑問符で表示される");
test_assert(__LINE__, strpos($edit_template, 'id="ai_shortcut_user_prompt1"') !== FALSE, "AI編集画面: USER_PROMPT1ショートカット設定をJSへ渡す");
test_assert(__LINE__, strpos($edit_template, 'id="ai_shortcut_user_prompt2"') !== FALSE, "AI編集画面: USER_PROMPT2ショートカット設定をJSへ渡す");
test_assert(__LINE__, strpos($edit_template, 'id="ai_model"') === FALSE, "AI編集画面: モデル入力UIは表示しない");
test_assert(__LINE__, strpos($edit_template, 'for="ai_model"') === FALSE, "AI編集画面: モデル入力ラベルは表示しない");

$admin_conf_template = file_get_contents(KONA3_DIR_ENGINE . '/template/admin_conf.html');
test_assert(__LINE__, strpos($admin_conf_template, 'id="conf_category_{{$category}}"') !== FALSE, "設定画面: カテゴリ見出しにアンカーを付与する");

$edit_action = file_get_contents(KONA3_DIR_ENGINE . '/action/edit.inc.php');
test_assert(__LINE__, strpos($edit_action, '# USER_PROMPT1') !== FALSE, "AIひな形新規作成: USER_PROMPT1を含める");
test_assert(__LINE__, strpos($edit_action, '# USER_PROMPT2') !== FALSE, "AIひな形新規作成: USER_PROMPT2を含める");

$edit_js = file_get_contents(KONA3_DIR_ENGINE . '/resource/edit.js');
test_assert(__LINE__, strpos($edit_js, 'function aiNormalizeJsonRow') !== FALSE, "AI結果表示: JSONキー正規化関数がある");
test_assert(__LINE__, strpos($edit_js, "'ErrorLocation'") !== FALSE, "AI結果表示: ErrorLocationキーをngとして扱う");
test_assert(__LINE__, strpos($edit_js, "'Correction'") !== FALSE, "AI結果表示: Correctionキーをokとして扱う");
test_assert(__LINE__, strpos($edit_js, "'Reason'") !== FALSE, "AI結果表示: Reasonキーをdescとして扱う");

$ja_ai_prompt = file_get_contents(KONA3_DIR_ENGINE . '/lang/ja-ai_prompt.md');
test_assert(__LINE__, strpos($ja_ai_prompt, '# 言い換え(置換候補)') !== FALSE, "AI標準ひな形: 言い換え置換候補がある");
test_assert(__LINE__, strpos($ja_ai_prompt, '"ng": "…ここに言い換え前の文章…"') !== FALSE, "AI標準ひな形: 言い換え置換候補はngキーを使う");
test_assert(__LINE__, strpos($ja_ai_prompt, '"ok": "…ここに言い換え後の文章…"') !== FALSE, "AI標準ひな形: 言い換え置換候補はokキーを使う");
test_assert(__LINE__, strpos($ja_ai_prompt, '"desc": "…ここに言い換えの理由…"') !== FALSE, "AI標準ひな形: 言い換え置換候補はdescキーを使う");

if ($old_provider === null) {
    unset($kona3conf['openai_provider']);
} else {
    $kona3conf['openai_provider'] = $old_provider;
}
if ($old_openai_key === null) {
    unset($kona3conf['openai_apikey']);
} else {
    $kona3conf['openai_apikey'] = $old_openai_key;
}
if ($old_openrouter_key === null) {
    unset($kona3conf['openrouter_apikey']);
} else {
    $kona3conf['openrouter_apikey'] = $old_openrouter_key;
}
if ($old_openrouter_model === null) {
    unset($kona3conf['openrouter_model']);
} else {
    $kona3conf['openrouter_model'] = $old_openrouter_model;
}

echo "edit_ai.test.php: ALL OK\n";
