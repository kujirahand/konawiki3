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

function chatgpt_ask($chatgpt_messages, $apikey=null, $model="gpt-3.5-turbo", $opt=[]) {
    if ($apikey == null || $apikey == '') {
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

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    // print_r($result);
    $tokens = isset($result['usage']['total_tokens']) ? $result['usage']['total_tokens'] : 0;
    $contents = isset($result['choices'][0]['message']['content']) ? $result['choices'][0]['message']['content'] : "Error: Unable to get response from ChatGPT.";
    // result
    return [$contents, $tokens];
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
