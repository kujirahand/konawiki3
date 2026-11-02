<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/kona3ai.inc.php';

// 元の状態を退避
$names = ['KONA3_T_A', 'KONA3_T_B', 'KONA3_T_C', 'KONA3_T_D', 'OPENAI_API_KEY', 'OPENROUTER_API_KEY'];
$bak = [];
foreach ($names as $n) {
    $bak[$n] = [
        'getenv' => getenv($n),
        'server' => array_key_exists($n, $_SERVER) ? [$_SERVER[$n]] : null,
        'env' => array_key_exists($n, $_ENV) ? [$_ENV[$n]] : null,
    ];
    putenv($n);
    unset($_SERVER[$n]);
    unset($_ENV[$n]);
}

// @VAR / @ENV:VAR (putenv)
putenv('KONA3_T_A=key-a');
test_eq(__LINE__, kona3ai_resolve_apikey('@KONA3_T_A'), 'key-a', '@VAR via putenv');
test_eq(__LINE__, kona3ai_resolve_apikey('@ENV:KONA3_T_A'), 'key-a', '@ENV:VAR via putenv');

// $_SERVER のみ (回帰テスト)
$_SERVER['KONA3_T_B'] = 'key-b';
test_assert(__LINE__, getenv('KONA3_T_B') === false, 'getenv cannot see $_SERVER');
test_eq(__LINE__, kona3ai_resolve_apikey('@KONA3_T_B'), 'key-b', '@VAR via $_SERVER');
test_eq(__LINE__, kona3ai_resolve_apikey('@ENV:KONA3_T_B'), 'key-b', '@ENV:VAR via $_SERVER');

// $_ENV のみ
$_ENV['KONA3_T_C'] = 'key-c';
test_eq(__LINE__, kona3ai_resolve_apikey('@KONA3_T_C'), 'key-c', '@VAR via $_ENV');

// getenv が優先
putenv('KONA3_T_D=from-getenv');
$_SERVER['KONA3_T_D'] = 'from-server';
$_ENV['KONA3_T_D'] = 'from-env';
test_eq(__LINE__, kona3ai_get_env('KONA3_T_D'), 'from-getenv', 'getenv has priority');

// 通常の文字列
test_eq(__LINE__, kona3ai_resolve_apikey('sk-plain'), 'sk-plain', 'plain key');

// 存在しない (プロバイダー既定も未設定のとき)
test_eq(__LINE__, kona3ai_resolve_apikey('@KONA3_T_NOT_EXISTS'), '', 'unknown var');
test_eq(__LINE__, kona3ai_get_env('KONA3_T_NOT_EXISTS'), '', 'unknown env');

// 空 → プロバイダー既定
putenv('OPENAI_API_KEY=openai-default');
putenv('OPENROUTER_API_KEY=openrouter-default');
test_eq(__LINE__, kona3ai_resolve_apikey('', 'OpenAI'), 'openai-default', 'empty + OpenAI');
test_eq(__LINE__, kona3ai_resolve_apikey(null), 'openai-default', 'null + default provider');
test_eq(__LINE__, kona3ai_resolve_apikey('', 'OpenRouter'), 'openrouter-default', 'empty + OpenRouter');

// @NAME が未設定ならプロバイダー既定へフォールバック (従来挙動)
test_eq(__LINE__, kona3ai_resolve_apikey('@KONA3_T_NOT_EXISTS', 'OpenAI'), 'openai-default', 'unknown @var falls back to provider default');
test_eq(__LINE__, kona3ai_resolve_apikey('@KONA3_T_NOT_EXISTS', 'OpenRouter'), 'openrouter-default', 'unknown @var falls back to OpenRouter default');

// 元に戻す
foreach ($names as $n) {
    putenv($n);
    unset($_SERVER[$n]);
    unset($_ENV[$n]);
    if ($bak[$n]['getenv'] !== false) { putenv($n . '=' . $bak[$n]['getenv']); }
    if ($bak[$n]['server'] !== null) { $_SERVER[$n] = $bak[$n]['server'][0]; }
    if ($bak[$n]['env'] !== null) { $_ENV[$n] = $bak[$n]['env'][0]; }
}

echo "kona3ai.test.php: ALL OK\n";
