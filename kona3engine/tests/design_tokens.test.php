<?php
require_once __DIR__ . '/test_common.inc.php';

// 土台CSS・ドロワー・テンプレートが、def スキンのデザイントークンへ統合されているか確認する (Issue #340)

$root_dir = dirname(dirname(__DIR__));
$def_file = $root_dir . '/kona3engine/resource/kona3def.css';
$drawer_file = $root_dir . '/kona3engine/resource/drawer.css';
$skin_file = $root_dir . '/skin/def/kona3.css';
$search_file = $root_dir . '/kona3engine/template/search.html';
$admin_conf_file = $root_dir . '/kona3engine/template/admin_conf.html';
foreach ([$def_file, $drawer_file, $skin_file, $search_file, $admin_conf_file] as $f) {
    test_assert(__LINE__, file_exists($f), "exists: " . basename($f));
}

$strip_comments = function ($css) {
    return preg_replace('#/\*.*?\*/#s', '', $css);
};
$def_nc = $strip_comments(file_get_contents($def_file));
$drawer_nc = $strip_comments(file_get_contents($drawer_file));
$skin_nc = $strip_comments(file_get_contents($skin_file));

// 1. kona3def.css に旧パレットの代表色が残っていないこと
//    (@media print と .filecode 以降の固定色は、これらのマーカーを含まないため全体検査でよい)
$forbidden = ['#00f', '#ffa', '#f0f0ff', '#ffffc9', '#fff0f0', 'silver'];
foreach ($forbidden as $marker) {
    $pat = '/' . preg_quote($marker, '/') . ($marker === 'silver' ? '/i' : '\b/i');
    test_eq(__LINE__, preg_match($pat, $def_nc), 0, "kona3def.css has no $marker");
}

// 2. var(--k3-...) がすべてフォールバック付きであること
foreach (['kona3def.css' => $def_nc, 'drawer.css' => $drawer_nc] as $name => $css) {
    $total = preg_match_all('/var\(\s*--k3-/', $css);
    $with_fb = preg_match_all('/var\(\s*--k3-[a-z0-9\-]+\s*,/', $css);
    test_assert(__LINE__, $total > 0, "$name references --k3-* tokens");
    test_eq(__LINE__, $with_fb, $total, "$name: every var(--k3-*) has a fallback");
}

// 3. drawer.css に旧パレット (暗い固定色) が残っていないこと
foreach (['#333', '#444', '#555', '#666', 'silver', '#ddd'] as $marker) {
    test_eq(__LINE__, stripos($drawer_nc, $marker), false, "drawer.css has no $marker");
}
// 印刷時の非表示ルールは変更しない
test_assert(__LINE__, preg_match('/@media print\s*\{\s*#drawer_wrapper, #hamburger_icon, #drawer_background\s*\{\s*display: none !important;\s*\}\s*\}/', $drawer_nc) === 1, "drawer.css keeps print hide rule");

// 4. 追加した状態トークンが :root と body.dark-theme の両方に定義されていること
$state_tokens = [
    'accent-subtle', 'info-bg', 'info-border', 'info-text',
    'success-bg', 'success-border', 'success-text',
    'warn-bg', 'warn-border', 'warn-text',
    'danger-bg', 'danger-border', 'danger-text', 'overlay',
];
preg_match('#:root\s*\{([^}]*)\}#', $skin_nc, $m);
$root_block = $m[1] ?? '';
preg_match('#@media\s+screen\s*\{\s*body\.dark-theme\s*\{([^}]*)\}#', $skin_nc, $m);
$dark_block = $m[1] ?? '';
foreach ($state_tokens as $t) {
    $re = '/--k3-color-' . preg_quote($t, '/') . '\s*:/';
    test_eq(__LINE__, preg_match($re, $root_block), 1, ":root defines --k3-color-$t");
    test_eq(__LINE__, preg_match($re, $dark_block), 1, "body.dark-theme defines --k3-color-$t");
}

// 5. テンプレートのインラインスタイルから旧色が除去されていること
$search_html = file_get_contents($search_file);
test_eq(__LINE__, preg_match('/style\s*=\s*"[^"]*silver/i', $search_html), 0, "search.html has no inline silver style");
$admin_html = file_get_contents($admin_conf_file);
test_eq(__LINE__, stripos($admin_html, '#f0f0ff'), false, "admin_conf.html has no #f0f0ff");
test_assert(__LINE__, strpos($def_nc, '.kona3_bordered_box') !== false, "kona3def.css defines .kona3_bordered_box");
test_assert(__LINE__, strpos($def_nc, '.kona3_highlight_box') !== false, "kona3def.css defines .kona3_highlight_box");

// 7. フォールバック値が def の :root の定義値と一致すること (Copilot レビュー指摘)
//    var(--k3-x, rgba(...)) のように入れ子の括弧を含む値にも対応する
preg_match('#:root\s*\{([^}]*)\}#', $skin_nc, $m);
preg_match_all('#(--k3-[a-z0-9\-]+)\s*:\s*([^;]+);#', $m[1] ?? '', $mm);
$def_root_values = [];
foreach ($mm[1] as $i => $name) {
    $def_root_values[$name] = strtolower(preg_replace('/\s+/', '', $mm[2][$i]));
}
foreach (['kona3def.css' => $def_nc, 'drawer.css' => $drawer_nc] as $name => $css) {
    preg_match_all('/var\(\s*(--k3-[a-z0-9\-]+)\s*,\s*((?:[^()]|\([^()]*\))+)\)/', $css, $vm, PREG_SET_ORDER);
    foreach ($vm as $v) {
        $token = $v[1];
        $fallback = strtolower(preg_replace('/\s+/', '', $v[2]));
        test_assert(__LINE__, isset($def_root_values[$token]), "$name: token $token is defined in def :root");
        if (isset($def_root_values[$token])) {
            test_eq(__LINE__, $fallback, $def_root_values[$token], "$name: fallback of $token matches def :root");
        }
    }
}

// 6. 波括弧の対応が取れていること
test_eq(__LINE__, substr_count($def_nc, '{'), substr_count($def_nc, '}'), "kona3def.css braces are balanced");
test_eq(__LINE__, substr_count($drawer_nc, '{'), substr_count($drawer_nc, '}'), "drawer.css braces are balanced");
