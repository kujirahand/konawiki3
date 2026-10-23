<?php

require_once dirname(__DIR__) . '/kona3lib.inc.php';
header('X-Frame-Options: SAMEORIGIN');

// 静的ページの生成・削除 (マイページの管理者メニューから。POST と編集トークンが必須)
function kona3_action_static()
{
    $title = lang('Static Output');
    try {
        $body = kona3static_handleRequest(kona3post('mode', 'build'));
    } catch (Kona3StaticException $e) {
        kona3error($title, kona3htmlspecialchars($e->getMessage()));
        exit;
    }
    kona3template('message.html', [
        'page_body' => '<h3>' . kona3htmlspecialchars($title) . '</h3>' . $body,
    ]);
}
