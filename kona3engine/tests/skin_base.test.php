<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/kona3static.inc.php';
require_once KONA3_DIR_ENGINE . '/kona3static_build.inc.php';

// 派生スキンは def のCSSを土台として先に読み込む (#288)

$save_conf = $kona3conf;
$kona3conf['url.index'] = 'index.php';
$kona3conf['static_mode'] = FALSE;

// 1. def スキンは def のCSSだけ
$kona3conf['skin'] = 'def';
$urls = kona3getSkinCSSURLs();
test_eq(__LINE__, count($urls), 1, "def: only one skin css");
test_eq(__LINE__, $urls[0], 'index.php?kona3.css&skin', "def: own kona3.css");

// 2. 派生スキンは def のCSS → 自分のCSSの順
foreach (['aqua', 'nako3', 'single'] as $skin) {
    $kona3conf['skin'] = $skin;
    $urls = kona3getSkinCSSURLs();
    test_eq(__LINE__, count($urls), 2, "$skin: def css + own css");
    test_eq(__LINE__, $urls[0], 'index.php?kona3.css&skin&s=def', "$skin: def css first");
    test_eq(__LINE__, $urls[1], 'index.php?kona3.css&skin', "$skin: own css second");
}

// 3. mtime 付き: sm と s を両方付ける
$kona3conf['skin'] = 'aqua';
$urls = kona3getSkinCSSURLs(TRUE);
$mtime = filemtime(KONA3_DIR_SKIN . '/def/kona3.css');
test_eq(__LINE__, $urls[0], "index.php?kona3.css&skin&sm=$mtime&s=def", "aqua: def css with mtime");

// 4. 静的出力モードでは、それぞれのスキンのディレクトリを指す
$kona3conf['static_mode'] = TRUE;
$kona3conf['static_base_url'] = '/';
$urls = kona3getSkinCSSURLs(TRUE);
test_eq(__LINE__, $urls[0], '/_kona3/skin/def/kona3.css', "static: def css");
test_eq(__LINE__, $urls[1], '/_kona3/skin/aqua/kona3.css', "static: aqua css");
$kona3conf['static_mode'] = FALSE;

// 5. 静的出力のアセットに def のCSSも含まれること
$assets = kona3static_scanAssets();
test_assert(__LINE__, isset($assets['_kona3/skin/def/kona3.css']), "static assets: def css is collected for aqua");
test_assert(__LINE__, isset($assets['_kona3/skin/aqua/kona3.css']), "static assets: aqua css is collected");

// 6. 派生スキンのCSSは、def のトークン(--k3-)を上書きする形で書かれていること
foreach (['aqua', 'nako3', 'single'] as $skin) {
    $css = file_get_contents(KONA3_DIR_SKIN . "/$skin/kona3.css");
    test_assert(__LINE__, strpos($css, '--k3-color-accent') !== false, "$skin: overrides --k3-color-accent");
    // 印刷時はライトの配色に戻るよう、ダーク用の変数は @media screen の中で定義する
    test_assert(__LINE__, preg_match('#@media\s+screen\s*\{\s*body\.dark-theme\s*\{#', $css) === 1, "$skin: dark variables are in @media screen");
}

// 7. 派生スキンのCSSの書き方を検査する
//    - 波括弧の対応
//    - 参照している変数が、def か自スキンの :root で定義されていること
//    - :root で上書きした色の変数は、ダーク用にも定義していること
//    - body.dark-theme の子孫セレクタでも !important を付けないこと(darkmode.css が変数化されたため #294)
function skin_base_rootVars($css)
{
    if (!preg_match('#:root\s*\{([^}]*)\}#', $css, $m)) {
        return [];
    }
    preg_match_all('#(--[a-z0-9\-]+)\s*:#', $m[1], $mm);
    return $mm[1];
}
$def_css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(KONA3_DIR_SKIN . '/def/kona3.css'));
$def_vars = skin_base_rootVars($def_css);
foreach (['aqua', 'nako3', 'single'] as $skin) {
    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(KONA3_DIR_SKIN . "/$skin/kona3.css"));
    test_eq(__LINE__, substr_count($css, '{'), substr_count($css, '}'), "$skin: braces are balanced");
    $own_vars = skin_base_rootVars($css);
    foreach ($own_vars as $name) {
        if (strpos($name, '--k3-') === 0) {
            test_assert(__LINE__, in_array($name, $def_vars), "$skin: overridden token $name exists in def");
        }
    }
    preg_match_all('#var\(\s*(--[a-z0-9\-]+)\s*[,)]#', $css, $m);
    foreach (array_unique($m[1]) as $name) {
        test_assert(__LINE__, in_array($name, $def_vars) || in_array($name, $own_vars), "$skin: used variable $name is defined");
    }
    preg_match('#@media\s+screen\s*\{\s*body\.dark-theme\s*\{([^}]*)\}#', $css, $m);
    preg_match_all('#(--[a-z0-9\-]+)\s*:#', $m[1] ?? '', $mm);
    foreach ($own_vars as $name) {
        if (strpos($name, '--k3-color-') === 0) {
            test_assert(__LINE__, in_array($name, $mm[1]), "$skin: dark theme redefines $name");
        }
    }
    preg_match_all('#([^{}]+)\{([^{}]*)\}#', $css, $rules, PREG_SET_ORDER);
    foreach ($rules as $rule) {
        $selector = trim($rule[1]);
        $is_dark = false;
        foreach (explode(',', $selector) as $part) {
            if (preg_match('#^body\.dark-theme\s+\S#', trim($part))) {
                $is_dark = true;
            }
        }
        if (!$is_dark) {
            continue;
        }
        foreach (array_filter(array_map('trim', explode(';', $rule[2]))) as $decl) {
            test_assert(__LINE__, strpos($decl, '!important') === false, "$skin: dark override must not need !important: $selector { $decl }");
        }
    }
}

$kona3conf = $save_conf;
