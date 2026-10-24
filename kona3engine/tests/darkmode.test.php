<?php
require_once __DIR__ . '/test_common.inc.php';

// Test dark mode implementation details

// 1. Check if darkmode.css exists and defines body.dark-theme overrides
$darkmode_css = dirname(__DIR__) . '/resource/darkmode.css';
test_assert(__LINE__, file_exists($darkmode_css), "darkmode.css exists");
$css_content = file_get_contents($darkmode_css);
test_assert(__LINE__, strpos($css_content, 'body.dark-theme') !== false, "darkmode.css defines body.dark-theme styles");
test_assert(__LINE__, strpos($css_content, 'background-color') !== false, "darkmode.css defines background-color styles");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme #wikiedit') !== false, "darkmode.css defines #wikiedit dark mode overrides");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme textarea#edit_txt') !== false, "darkmode.css defines textarea#edit_txt dark mode overrides");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme .kona3_login_bar') !== false, "darkmode.css defines .kona3_login_bar dark mode overrides");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme #wikimessage h2') !== false, "darkmode.css defines #wikimessage h2 dark mode overrides");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme #wikimessage h3') !== false, "darkmode.css defines #wikimessage h3 dark mode overrides");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme #wikifooter') !== false, "darkmode.css defines #wikifooter dark mode overrides");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme #wikifooter a') !== false, "darkmode.css defines #wikifooter a dark mode overrides");
test_assert(__LINE__, strpos($css_content, 'body.dark-theme .memo') !== false, "darkmode.css defines .memo dark mode overrides");


// 1b. darkmode.css は CSS 変数(--k3d-*)で配色を切り替える (#294)
test_assert(__LINE__, strpos($css_content, '--k3d-bg') !== false, "darkmode.css defines --k3d-* variables");
test_assert(__LINE__, strpos($css_content, 'var(--k3d-') !== false, "darkmode.css rules use var(--k3d-*)");
test_assert(__LINE__, strpos($css_content, '@media screen') !== false, "darkmode.css applies dark colors only on screen (not in print)");
// !important は、インラインスタイルのハイライト色を上書きする箇所だけに残す
$css_nc = preg_replace('#/\*.*?\*/#s', '', $css_content);
test_assert(__LINE__, substr_count($css_nc, '!important') <= 5, "darkmode.css uses !important only for PHP highlight overrides");
foreach (explode("\n", $css_nc) as $line) {
    if (strpos($line, '!important') !== false) {
        test_assert(__LINE__, strpos($line, 'span[style') !== false, "darkmode.css !important only on highlight span rules: $line");
    }
}

// 1c. --k3d-* 変数の定義と参照に誤記がないこと (#294)
//     darkmode.css で定義した変数だけが、darkmode.css と各スキンで参照・再定義されること
preg_match_all('#(--k3d-[a-z0-9\-]+)\s*:#', $css_nc, $m);
$k3d_defined = array_unique($m[1]);
test_assert(__LINE__, count($k3d_defined) > 30, "darkmode.css defines many --k3d-* variables");
preg_match_all('#var\(\s*(--k3d-[a-z0-9\-]+)#', $css_nc, $m);
foreach (array_unique($m[1]) as $name) {
    test_assert(__LINE__, in_array($name, $k3d_defined), "darkmode.css: used variable $name is defined");
}
foreach (['def', 'aqua', 'nako3', 'single'] as $skin) {
    $skin_css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(KONA3_DIR_SKIN . "/$skin/kona3.css"));
    // スキンが再定義する --k3d-* は、darkmode.css に実在する変数であること(タイポ検出)
    preg_match_all('#(--k3d-[a-z0-9\-]+)\s*:#', $skin_css, $m);
    foreach (array_unique($m[1]) as $name) {
        test_assert(__LINE__, in_array($name, $k3d_defined), "$skin: redefined variable $name exists in darkmode.css");
    }
    // スキンが参照する --k3d-* も、darkmode.css で定義済みであること
    preg_match_all('#var\(\s*(--k3d-[a-z0-9\-]+)#', $skin_css, $m);
    foreach (array_unique($m[1]) as $name) {
        test_assert(__LINE__, in_array($name, $k3d_defined), "$skin: used variable $name is defined in darkmode.css");
    }
}

// 2. Check if parts_header.html contains toggle button and inline JS to restore preference
$parts_header = dirname(__DIR__) . '/template/parts_header.html';
test_assert(__LINE__, file_exists($parts_header), "parts_header.html exists");
$header_content = file_get_contents($parts_header);
test_assert(__LINE__, strpos($header_content, 'id="dark_mode_toggle"') !== false, "parts_header.html contains dark_mode_toggle ID");
test_assert(__LINE__, strpos($header_content, 'localStorage.getItem(\'kona3_theme\')') !== false, "parts_header.html contains inline JS reading theme preference");

// 3. Check if drawer.js implements the toggle click handler
$drawer_js = dirname(__DIR__) . '/resource/drawer.js';
test_assert(__LINE__, file_exists($drawer_js), "drawer.js exists");
$js_content = file_get_contents($drawer_js);
test_assert(__LINE__, strpos($js_content, '#dark_mode_toggle') !== false, "drawer.js targets #dark_mode_toggle");
test_assert(__LINE__, strpos($js_content, 'localStorage.setItem(\'kona3_theme\'') !== false, "drawer.js saves theme preference");

// 4. OSのダーク設定に追従せず、明示的に選択した場合のみダークにする (デフォルトはライト)
test_assert(__LINE__, strpos($header_content, 'prefers-color-scheme') === false, "parts_header.html does not follow OS dark preference");
test_assert(__LINE__, strpos($header_content, "theme === 'dark'") !== false, "parts_header.html applies dark theme only when explicitly chosen");
