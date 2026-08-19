<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/action/go.inc.php';

// --- リダイレクト先URL(エイリアスなし) ---
test_eq(__LINE__, kona3go_getRedirectURL('', []), 'index.php', "empty go target redirects to index.php");
test_eq(__LINE__, kona3go_getRedirectURL('FrontPage', []), 'index.php?FrontPage&show', "go.php?FrontPage redirects to show action");
test_eq(__LINE__, kona3go_getRedirectURL('Category/SubPage', []), 'index.php?Category%2FSubPage&show', "go.php?sub page redirects to show action");
test_eq(__LINE__, kona3go_getRedirectURL('日本語ページ', []), 'index.php?' . urlencode('日本語ページ') . '&show', "go.php?Japanese page redirects to show action");

// --- alias.json の解析 ---
$aliases = kona3go_parseAliasList('{"a1":"RealPage1","エイリアス":"実際のWiki名"}');
test_eq(__LINE__, $aliases['a1'], 'RealPage1', "parse alias.json");
test_eq(__LINE__, $aliases['エイリアス'], '実際のWiki名', "parse alias.json (Japanese)");
test_eq(__LINE__, count(kona3go_parseAliasList('broken json')), 0, "broken alias.json is ignored");
test_eq(__LINE__, count(kona3go_parseAliasList('{"b":{"c":"d"},"c":true,"d":null,"":"x","y":"  "}')), 0, "invalid alias entries are ignored");
// json_decode() は "1" のような数値のキーを int に変換するので、それも扱えること
$num_aliases = kona3go_parseAliasList('{"1":"FrontPage","2":"aaaa","3":"MenuBar","MenuBar":"m"}');
test_eq(__LINE__, count($num_aliases), 4, "numeric alias keys are kept");
test_eq(__LINE__, $num_aliases['2'], 'aaaa', "numeric alias key can be looked up");
test_eq(__LINE__, kona3go_parseAliasList('{"n":123}')['n'], '123', "numeric page name is converted to string");
test_eq(__LINE__, kona3go_getRedirectURL('2', $num_aliases), 'index.php?aaaa&show', "go.php?2 redirects to the aliased page");
test_eq(__LINE__, kona3go_getRedirectURL('3', $num_aliases), 'index.php?m&show', "go.php?3 follows the chained alias");
test_eq(__LINE__, kona3go_parseAliasList('{" sp ":" Page "}')['sp'], 'Page', "alias entries are trimmed");

// --- エイリアスの解決 ---
$aliases = ['short' => 'LongPageName', 'a' => 'b', 'b' => 'c'];
test_eq(__LINE__, kona3go_resolveAlias('short', $aliases), 'LongPageName', "resolve alias");
test_eq(__LINE__, kona3go_resolveAlias('NoAlias', $aliases), 'NoAlias', "page without alias is kept");
test_eq(__LINE__, kona3go_resolveAlias('a', $aliases), 'c', "resolve chained alias");
test_eq(__LINE__, kona3go_resolveAlias('x', ['x' => 'y', 'y' => 'x']), 'x', "circular alias does not loop forever");
// 連鎖は最大5段まで
$deep = ['c1' => 'c2', 'c2' => 'c3', 'c3' => 'c4', 'c4' => 'c5', 'c5' => 'c6', 'c6' => 'c7'];
test_eq(__LINE__, kona3go_resolveAlias('c2', $deep), 'c7', "resolve 5 chained aliases");
test_eq(__LINE__, kona3go_resolveAlias('c1', $deep), 'c6', "chained alias stops at 5 hops");

// --- go.php?{ALIAS} のリダイレクト ---
$aliases = ['10' => 'FrontPage', '20' => 'Category/SubPage', 'jp' => '日本語ページ'];
test_eq(__LINE__, kona3go_getRedirectURL('10', $aliases), 'index.php?FrontPage&show', "go.php?ALIAS redirects to the real page");
test_eq(__LINE__, kona3go_getRedirectURL('20', $aliases), 'index.php?Category%2FSubPage&show', "go.php?ALIAS redirects to the real sub page");
test_eq(__LINE__, kona3go_getRedirectURL('jp', $aliases), 'index.php?' . urlencode('日本語ページ') . '&show', "go.php?ALIAS redirects to the real Japanese page");
test_eq(__LINE__, kona3go_getRedirectURL('FrontPage', $aliases), 'index.php?FrontPage&show', "go.php?PAGE without alias works as before");

// --- ファイル(data/alias.json)からの読み込み ---
test_eq(__LINE__, count(kona3go_loadAliasList(__DIR__ . '/no_such_alias.json')), 0, "missing alias.json returns empty list");
$tmp_alias = KONA3_DIR_CACHE . '/test_alias.json';
file_put_contents($tmp_alias, json_encode(['t1' => 'TestPage'], JSON_UNESCAPED_UNICODE));
$loaded = kona3go_loadAliasList($tmp_alias);
test_eq(__LINE__, $loaded['t1'], 'TestPage', "load alias.json from file");
test_eq(__LINE__, kona3go_getRedirectURL('t1', $loaded), 'index.php?TestPage&show', "go.php?ALIAS with loaded alias.json");
@unlink($tmp_alias);
