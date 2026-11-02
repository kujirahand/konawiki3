<?php

function chatgpt_messages_init($system_message, $user_message) {
    return [
        [
            "role" => "system",
            "content" => $system_message
        ],
        [
            "role" => "user",
            "content" => $user_message
        ]
    ];
}

// 環境変数を取得 (getenv → $_SERVER → $_ENV の順)
function kona3ai_get_env($name) {
    $v = getenv($name);
    if (is_string($v) && $v !== '') { return $v; }
    if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return $_SERVER[$name];
    }
    if (isset($_ENV[$name]) && is_string($_ENV[$name]) && $_ENV[$name] !== '') {
        return $_ENV[$name];
    }
    return '';
}

// APIキーを解決 (@ENV:NAME / @NAME / 直接指定 / プロバイダー既定の環境変数)
function kona3ai_resolve_apikey($apikey, $provider = "OpenAI") {
    $resolved = '';
    if (is_string($apikey) && $apikey !== '') {
        if (preg_match('/^\@ENV\:(.+)$/', $apikey, $m)) {
            $resolved = kona3ai_get_env($m[1]);
        } else if (preg_match('/^\@([a-zA-Z0-9_]+)$/', $apikey, $m)) {
            $resolved = kona3ai_get_env($m[1]);
        } else {
            return $apikey;
        }
    }
    if ($resolved !== '') {
        return $resolved;
    }
    // 未指定、または @NAME が未設定のときはプロバイダー既定の環境変数へ (従来挙動)
    $envKey = ($provider === "OpenRouter") ? "OPENROUTER_API_KEY" : "OPENAI_API_KEY";
    return kona3ai_get_env($envKey);
}

function chatgpt_ask($chatgpt_messages, $apikey=null, $model= "gpt-6-luna", $temperature=0, $opt=[], $provider="OpenAI", $timeout=20) {
    $errMessage = "Error: Unable to get response from AI.";
    // check api key (ENV)
    $apikey = kona3ai_resolve_apikey($apikey, $provider);
    if ($apikey === '') {
        return ["Error: API Key is not set.", 0];
    }
    $url = "https://api.openai.com/v1/chat/completions";
    $headers = array(
        "Content-Type: application/json",
        "Authorization: Bearer $apikey"
    );
    if ($provider === "OpenRouter") {
        $url = "https://openrouter.ai/api/v1/chat/completions";
        $headers[] = "HTTP-Referer: https://github.com/kujirahand/konawiki3";
        $headers[] = "X-Title: KonaWiki3";
    }

    $data = $opt + [
        "model" => $model,
        "messages" => $chatgpt_messages,
    ];
    // set temperature
    if ($temperature > 0) {
        $data["temperature"] = $temperature;
    }

    $ch = curl_init($url);
    try {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $response = curl_exec($ch);
        if ($response === false) {
            $errorCode = curl_errno($ch); // エラーコードを取得
            $errorMessage = curl_error($ch); // エラーメッセージを取得
            return [$errMessage."(Error: $errorCode, $errorMessage)", 0];
        }

        // JSON decode
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [$errMessage."(Response broken)", 0];
        }
        // get result
        $tokens = isset($result['usage']['total_tokens']) ? $result['usage']['total_tokens'] : 0;
        if (isset($result['choices'][0]['message']['content'])) {
            $contents = $result['choices'][0]['message']['content'];
            return [$contents, $tokens];
        }
        // get error
        if (isset($result['error'])) {
            $errDetail = is_array($result['error']) ? 
                (isset($result['error']['message']) ? $result['error']['message'] : json_encode($result['error'])) : 
                $result['error'];
            return [$errMessage.", ".$errDetail, 0];
        }
        return [$errMessage . " (No choices or error details in response)", 0];
    } finally {
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }
    }
}

/*
// Example
$apikey = getenv("OPENAI_API_KEY");
$messages = chatgpt_messages_init(
    "You are helpful AI assitant.", 
    "可愛いネコの名前を2つ考えてJSON形式で答えてください。");
$response = chatgpt_ask($messages, $apikey);
print_r($response);
*/