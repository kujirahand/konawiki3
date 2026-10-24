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
    '--k3-color-on-accent', '--k3-color-summary',
    '--k3-space-1', '--k3-space-2', '--k3-space-3',
    '--k3-space-4', '--k3-space-5', '--k3-space-6',
    '--k3-width-layout', '--k3-radius',
];
foreach ($required as $name) {
    test_assert(__LINE__, in_array($name, $root_vars), ":root defines $name");
}

// 3. ダークモードでは、すべての色トークンを再定義していること
//    印刷時にライトの配色へ戻るよう、変数の再定義は @media screen の中に置く
test_assert(__LINE__, preg_match('#@media\s+screen\s*\{\s*body\.dark-theme\s*\{([^}]*)\}#', $css_nc, $m) === 1, "body.dark-theme variable block exists in @media screen");
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
//    var(--k3-x, fallback) の形も対象にする
preg_match_all('#var\(\s*(--k3-[a-z0-9\-]+)\s*[,)]#', $css_nc, $m);
$used_vars = array_unique($m[1]);
foreach ($used_vars as $name) {
    test_assert(__LINE__, in_array($name, $root_vars), "used variable $name is defined in :root");
}

// 5. darkmode.css が変数化されたため(#294)、ダーク用の上書きに !important は不要なこと
//    def のダーク配色は、冒頭の変数ブロックで --k3-* と --k3d-* を再定義して完結する。
//    子孫セレクタのルールが残る場合(見出しアンカーなど)も、詳細度で上書きし !important は付けない
preg_match_all('#([^{}]+)\{([^{}]*)\}#', $css_nc, $rules, PREG_SET_ORDER);
foreach ($rules as $rule) {
    $selector = trim($rule[1]);
    // セレクタリストのどれか1つでも body.dark-theme の子孫セレクタなら対象にする
    $is_dark_override = false;
    foreach (explode(',', $selector) as $part) {
        if (preg_match('#^body\.dark-theme\s+\S#', trim($part))) {
            $is_dark_override = true;
        }
    }
    if (!$is_dark_override) {
        continue;
    }
    $decls = array_filter(array_map('trim', explode(';', $rule[2])));
    foreach ($decls as $decl) {
        test_assert(__LINE__, strpos($decl, '!important') === false, "dark override must not need !important: $selector { $decl }");
    }
}

// 6. 色コードは変数の定義(:root と body.dark-theme)にだけ書き、個々のルールでは変数を参照すること
foreach ($rules as $rule) {
    $selector = trim($rule[1]);
    if ($selector === ':root' || $selector === 'body.dark-theme') {
        continue;
    }
    $has_hex = preg_match('#:[^;]*\#[0-9a-fA-F]{3,8}\b#', $rule[2]) === 1;
    test_assert(__LINE__, !$has_hex, "no hard-coded color in rule: $selector");
}

// 7. 外部 Web フォントを読み込んでいないこと
test_assert(__LINE__, stripos($css_nc, '@import') === false, "no @import");
test_assert(__LINE__, stripos($css_nc, '@font-face') === false, "no @font-face");
