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

function chatgpt_ask($chatgpt_messages, $apikey=null, $model= "gpt-4o-mini", $temperature=0, $opt=[], $provider="OpenAI") {
    $errMessage = "Error: Unable to get response from AI.";
    // check api key (ENV)
    if (is_string($apikey)) {
        if (preg_match('/^\@ENV\:(.+)$/', $apikey, $m)) {
            $apikey = getenv($m[1]);
        } else if (preg_match('/^\@([a-zA-Z0-9_]+)$/', $apikey, $m)) {
            $apikey = getenv($m[1]);
        }
    }
    if ($apikey == null || $apikey == '') { // check apikey
        $envKey = ($provider === "OpenRouter") ? "OPENROUTER_API_KEY" : "OPENAI_API_KEY";
        $apikey = getenv($envKey);
        if ($apikey == null || $apikey == '') {
            return ["Error: API Key is not set.", 0];
        }
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
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $keyInfo = "[Key: Len=" . strlen($apikey) . ", Head=" . substr($apikey, 0, 15) . "]";
    if ($response === false) {
        $errorCode = curl_errno($ch); // エラーコードを取得
        $errorMessage = curl_error($ch); // エラーメッセージを取得
        return [$errMessage."(Error: $errorCode, $errorMessage) $keyInfo", 0];
    }


    // JSON decode
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [$errMessage."(Response broken: ) $keyInfo", 0];
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
        return [$errMessage.", ".$errDetail . " $keyInfo", 0];
    }
    return [$errMessage . " (No choices or error details in response) $keyInfo", 0];
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