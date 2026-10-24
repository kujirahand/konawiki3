<?php
require_once __DIR__ . '/test_common.inc.php';

// デフォルトスキン(def)のCSSが、docs/default_skin.md の方針どおりに書かれているか確認する

$css_file = dirname(dirname(__DIR__)) . '/skin/def/kona3.css';
test_assert(__LINE__, file_exists($css_file), "skin/def/kona3.css exists");
$css = file_get_contents($css_file);
// コメントを除去してから解析する
$css_nc = preg_replace('#/\*.*?\*/#s', '', $css);

// 1. 波括弧の対応が取れていること
test_eq(__LINE__, substr_count($css_nc, '{'), substr_count($css_nc, '}'), "braces are balanced");

// 2. デザイントークンが :root に定義されていること
test_assert(__LINE__, preg_match('#:root\s*\{([^}]*)\}#', $css_nc, $m) === 1, ":root block exists");
$root_block = $m[1] ?? '';
preg_match_all('#(--k3-[a-z0-9\-]+)\s*:#', $root_block, $m);
$root_vars = $m[1];
$required = [
    '--k3-color-text', '--k3-color-text-muted', '--k3-color-heading',
    '--k3-color-bg', '--k3-color-bg-subtle', '--k3-color-border',
    '--k3-color-border-strong', '--k3-color-accent', '--k3-color-accent-hover',
    '--k3-space-1', '--k3-space-2', '--k3-space-3',
    '--k3-space-4', '--k3-space-5', '--k3-space-6',
    '--k3-width-layout', '--k3-radius',
];
foreach ($required as $name) {
    test_assert(__LINE__, in_array($name, $root_vars), ":root defines $name");
}

// 3. ダークモードでは、すべての色トークンを再定義していること
test_assert(__LINE__, preg_match('#(?:^|\})\s*body\.dark-theme\s*\{([^}]*)\}#', $css_nc, $m) === 1, "body.dark-theme variable block exists");
$dark_block = $m[1] ?? '';
preg_match_all('#(--k3-color-[a-z0-9\-]+)\s*:#', $dark_block, $m);
$dark_vars = $m[1];
foreach ($root_vars as $name) {
    if (strpos($name, '--k3-color-') !== 0) {
        continue;
    }
    test_assert(__LINE__, in_array($name, $dark_vars), "body.dark-theme redefines $name");
}

// 4. 参照している変数がすべて定義済みであること(変数名の誤記の検出)
preg_match_all('#var\((--k3-[a-z0-9\-]+)\)#', $css_nc, $m);
$used_vars = array_unique($m[1]);
foreach ($used_vars as $name) {
    test_assert(__LINE__, in_array($name, $root_vars), "used variable $name is defined in :root");
}

// 5. darkmode.css の上書き(body.dark-theme の子孫セレクタ)は !important を付けていること
//    darkmode.css が !important で指定しているため、付けないと効かない
preg_match_all('#([^{}]+)\{([^{}]*)\}#', $css_nc, $rules, PREG_SET_ORDER);
$dark_rule_count = 0;
foreach ($rules as $rule) {
    $selector = trim($rule[1]);
    if (!preg_match('#^body\.dark-theme\s+\S#', $selector)) {
        continue;
    }
    $dark_rule_count++;
    $decls = array_filter(array_map('trim', explode(';', $rule[2])));
    foreach ($decls as $decl) {
        test_assert(__LINE__, strpos($decl, '!important') !== false, "dark override has !important: $selector { $decl }");
    }
}
test_assert(__LINE__, $dark_rule_count > 0, "dark override rules exist");

// 6. 外部 Web フォントを読み込んでいないこと
test_assert(__LINE__, stripos($css_nc, '@import') === false, "no @import");
test_assert(__LINE__, stripos($css_nc, '@font-face') === false, "no @font-face");
