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

// 出力上限(finish_reason=length)で切れたとき、続きを取得する最大回数
const CHATGPT_MAX_CONTINUE = 5;

/**
 * 応答が途中で切れた(finish_reason=length)場合に「続き」を依頼して連結する
 * @param array $messages 初期メッセージ
 * @param callable $once fn($messages): [content, tokens, finish_reason]
 * @return array [content, tokens]
 */
function chatgpt_ask_continue($messages, $once, $max_continue = CHATGPT_MAX_CONTINUE) {
    $all = '';
    $total_tokens = 0;
    for ($i = 0; $i <= $max_continue; $i++) {
        list($content, $tokens, $finish) = $once($messages);
        if ($tokens === 0 && $i === 0 && strpos((string)$content, 'Error:') === 0) {
            return [$content, 0]; // 最初の呼び出しがエラー
        }
        if ($tokens === 0 && $i > 0 && strpos((string)$content, 'Error:') === 0) {
            break; // 続きの取得に失敗: ここまでの結果を返す
        }
        $all .= $content;
        $total_tokens += $tokens;
        if ($finish !== 'length') { break; }
        $messages[] = ["role" => "assistant", "content" => $content];
        $messages[] = ["role" => "user", "content" =>
            "Your previous response was cut off. Continue exactly from where you stopped. "
            . "Do not repeat any earlier text and do not add any preface."];
    }
    return [$all, $total_tokens];
}

function chatgpt_ask($chatgpt_messages, $apikey=null, $model= "gpt-6-luna", $temperature=0, $opt=[], $provider="OpenAI", $timeout=20) {
    return chatgpt_ask_continue($chatgpt_messages, function ($messages) use ($apikey, $model, $temperature, $opt, $provider, $timeout) {
        return chatgpt_ask_once($messages, $apikey, $model, $temperature, $opt, $provider, $timeout);
    });
}

// 1回だけAPIを呼ぶ。戻り値: [content, tokens, finish_reason]
function chatgpt_ask_once($chatgpt_messages, $apikey=null, $model= "gpt-6-luna", $temperature=0, $opt=[], $provider="OpenAI", $timeout=20) {
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
            return ["Error: API Key is not set.", 0, ""];
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
            return [$errMessage."(Error: $errorCode, $errorMessage)", 0, ""];
        }

        // JSON decode
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [$errMessage."(Response broken)", 0, ""];
        }
        // get result
        $tokens = isset($result['usage']['total_tokens']) ? $result['usage']['total_tokens'] : 0;
        if (isset($result['choices'][0]['message']['content'])) {
            $contents = $result['choices'][0]['message']['content'];
            $finish = isset($result['choices'][0]['finish_reason']) ? (string)$result['choices'][0]['finish_reason'] : '';
            return [$contents, $tokens, $finish];
        }
        // get error
        if (isset($result['error'])) {
            $errDetail = is_array($result['error']) ? 
                (isset($result['error']['message']) ? $result['error']['message'] : json_encode($result['error'])) : 
                $result['error'];
            return [$errMessage.", ".$errDetail, 0, ""];
        }
        return [$errMessage . " (No choices or error details in response)", 0, ""];
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