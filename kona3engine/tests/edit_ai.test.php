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

echo "edit_ai.test.php: ALL OK\n";
