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

function chatgpt_ask($chatgpt_messages, $apikey=null, $model= "gpt-4o-mini", $temperature=0, $opt=[]) {
    $errMessage = "Error: Unable to get response from ChatGPT.";
    // check api key (ENV)
    if (is_string($apikey) && preg_match('/^\@ENV\:(.+)$/', $apikey, $m)) {
        $apikey = getenv($m[1]);
    }
    if ($apikey == null || $apikey == '') { // check apikey
        $apikey = getenv("OPENAI_API_KEY");
        if ($apikey == null || $apikey == '') {
            return ["Error: API Key is not set.", 0];
        }
    }
    $url = "https://api.openai.com/v1/chat/completions";
    $headers = array(
        "Content-Type: application/json",
        "Authorization: Bearer $apikey"
    );

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

    $response = curl_exec($ch);
    if ($response === false) {
        $errorCode = curl_errno($ch); // エラーコードを取得
        $errorMessage = curl_error($ch); // エラーメッセージを取得
        return [$errMessage."(Error: $errorCode, $errorMessage)", 0];
    }
    curl_close($ch);

    // JSON decode
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [$errMessage."(Response broken: )", 0];
    }
    // get result
    $tokens = isset($result['usage']['total_tokens']) ? $result['usage']['total_tokens'] : 0;
    if (isset($result['choices'][0]['message']['content'])) {
        $contents = $result['choices'][0]['message']['content'];
        return [$contents, $tokens];
    }
    // get error
    if (isset($result['error'])) {
        return [$errMessage.", ".$result['error'], 0];
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