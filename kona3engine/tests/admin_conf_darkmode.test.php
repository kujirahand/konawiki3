<?php
require_once __DIR__ . '/test_common.inc.php';

// Issue #368: 管理者設定画面がダークモードで見づらい

$tpl = file_get_contents(dirname(__DIR__) . '/template/admin_conf.html');
$css = file_get_contents(dirname(__DIR__) . '/resource/darkmode.css');
test_assert(__LINE__, $tpl !== false && $css !== false, "files are readable");

// 1. テンプレートの固定色がトークン化されている
test_assert(__LINE__, strpos($tpl, 'background-color: #fff') === false, "admin_conf.html has no fixed white background");
test_assert(__LINE__, strpos($tpl, 'var(--k3-color-bg') !== false, "admin_conf.html uses var(--k3-color-bg");

// 固定色が単独で残っていない (var(..., #xxx) のフォールバック内は可)
$bare = function ($color) use ($tpl) {
    return preg_match('/(:|solid)\s+' . preg_quote($color, '/') . '/', $tpl) === 1;
};
test_assert(__LINE__, !$bare('#d8d8e8'), "admin_conf.html has no bare #d8d8e8");
test_assert(__LINE__, !$bare('#9aa5d8'), "admin_conf.html has no bare #9aa5d8");
test_assert(__LINE__, strpos($tpl, 'color: #444') === false, "admin_conf.html has no fixed color: #444");
test_assert(__LINE__, strpos($tpl, 'color: #555') === false, "admin_conf.html has no fixed color: #555");

// 2. darkmode.css に .conf-item の上書きがある
test_assert(__LINE__, strpos($css, 'body.dark-theme #wikimessage .conf-item') !== false, "darkmode.css overrides .conf-item");
test_assert(__LINE__, preg_match('/body\.dark-theme #wikimessage \.conf-item\s*\{[^}]*--k3d-bg/', $css) === 1, ".conf-item override uses --k3d-bg (distinct from the .box subtle bg)");
test_assert(__LINE__, strpos($css, 'body.dark-theme #wikimessage .conf-desc') !== false, "darkmode.css overrides .conf-desc");
