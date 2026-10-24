<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/plugins/nako3/lib.inc.php';

echo "=== nako3 load error test (issue #44) ===\n";

// isIE() が参照するため、テスト用の UA を設定する (IE ではないもの)
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 test';

// --- nako3_script_onerror_attr: 失敗をキューへ記録し、定義済みなら通知する ---
$attr = nako3_script_onerror_attr('wnako3.js');
test_eq(__LINE__, strpos($attr, 'onerror=') !== false, true, "onerror attr");
test_eq(__LINE__, strpos($attr, "__nako3_load_errors") !== false, true, "onerror queues error");
test_eq(__LINE__, strpos($attr, ".push('wnako3.js')") !== false, true, "onerror pushes file name");
test_eq(__LINE__, strpos($attr, "nako3_report_load_error('wnako3.js')") !== false, true, "onerror reports");
test_eq(__LINE__, strpos($attr, 'window.nako3_report_load_error') !== false, true, "onerror checks report fn");
// ファイル名に引用符などが含まれても属性値を壊さないよう正規化される
$attr_bad = nako3_script_onerror_attr("a'b\"c.js");
test_eq(__LINE__, strpos($attr_bad, "a_b_c.js") !== false, true, "onerror name sanitized");
test_eq(__LINE__, substr_count($attr_bad, '"'), 2, "onerror attr quotes only delimiter");

// --- nako3_make_script_tag: 全ての外部スクリプトに onerror が付く ---
$nako3 = ['baseurl' => '', 'ver' => '3.8.8'];
$tags = nako3_make_script_tag($nako3);
test_eq(__LINE__, $nako3['baseurl'], 'https://n3s.nadesi.com/cdn.php?v=3.8.8&f=', "default baseurl");
test_eq(__LINE__, substr_count($tags, '<script'), 10, "script tag count (ace 4 + nako3 6)");
test_eq(__LINE__, substr_count($tags, 'onerror='), 10, "every script tag has onerror");
test_eq(__LINE__, substr_count($tags, '.push('), 10, "every onerror queues");
$plugins = ['wnako3.js', 'plugin_markup.js', 'plugin_turtle.js', 'plugin_webworker.js', 'plugin_caniuse.js', 'plugin_kansuji.js'];
foreach ($plugins as $p) {
    test_eq(__LINE__, strpos($tags, "release/$p") !== false, true, "plugin src $p");
    test_eq(__LINE__, strpos($tags, ".push('$p')") !== false, true, "plugin onerror $p");
}
foreach (['ace.js', 'ext-language_tools.min.js', 'ext-options.min.js', 'ext-code_lens.min.js'] as $f) {
    test_eq(__LINE__, strpos($tags, "ace/1.4.12/$f") !== false, true, "ace src $f");
    test_eq(__LINE__, strpos($tags, ".push('$f')") !== false, true, "ace onerror $f");
}
// integrity / crossorigin は従来どおり維持される
test_eq(__LINE__, substr_count($tags, 'integrity="sha512-'), 4, "ace integrity kept");
test_eq(__LINE__, substr_count($tags, 'crossorigin="anonymous"'), 4, "ace crossorigin kept");
test_eq(__LINE__, substr_count($tags, 'defer'), 6, "nako3 scripts stay defer");
// カスタム baseurl でも onerror が付く
$nako3_custom = ['baseurl' => 'https://example.com/nako/', 'ver' => '9.9.9'];
$tags_custom = nako3_make_script_tag($nako3_custom);
test_eq(__LINE__, strpos($tags_custom, 'https://example.com/nako/release/wnako3.js') !== false, true, "custom baseurl src");
test_eq(__LINE__, substr_count($tags_custom, 'onerror='), 10, "custom baseurl onerror count");
// onerror では同じファイル名を重複して積まない (agy レビュー低1)
test_eq(__LINE__, strpos($attr, 'indexOf(') !== false, true, "onerror dedups queue");
// IE の場合は ace のタグ自体が出力されない (agy レビュー低2)
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 6.1; Trident/7.0; rv:11.0) like Gecko';
$nako3_ie = ['baseurl' => '', 'ver' => '3.8.8'];
$tags_ie = nako3_make_script_tag($nako3_ie);
test_eq(__LINE__, substr_count($tags_ie, '<script'), 6, "IE script tag count (nako3 only)");
test_eq(__LINE__, substr_count($tags_ie, 'onerror='), 6, "IE every script tag has onerror");
test_eq(__LINE__, strpos($tags_ie, 'cdnjs.cloudflare.com') === false, true, "IE no ace tags");
test_eq(__LINE__, strpos($tags_ie, 'release/wnako3.js') !== false, true, "IE wnako3 src");
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 test';

// --- テンプレート側の失敗検出ロジック ---
$dir = dirname(__DIR__) . '/plugins/nako3';
$js_edit = file_get_contents($dir . '/tpl-js-edit.html');
test_eq(__LINE__, strpos($js_edit, 'NAKO3_LOAD_TIMEOUT_MS') !== false, true, "edit js has load timeout");
test_eq(__LINE__, strpos($js_edit, 'nako3_is_load_failed') !== false, true, "edit js has is_load_failed");
test_eq(__LINE__, strpos($js_edit, 'nako3_show_load_error') !== false, true, "edit js has show_load_error");
test_eq(__LINE__, strpos($js_edit, 'nako3_hide_load_error') !== false, true, "edit js has hide_load_error for late load recovery");
test_eq(__LINE__, strpos($js_edit, 'nako3_has_fatal_error') !== false, true, "edit js distinguishes fatal error from timeout");
test_eq(__LINE__, strpos($js_edit, 'nako3_is_fatal_for') !== false, true, "edit js fatal check is per-mode (ace not fatal for use_textarea)");
test_eq(__LINE__, strpos($js_edit, 'nako3_is_load_failed(use_textarea)') !== false, true, "edit js load failure check takes use_textarea");
test_eq(__LINE__, substr_count($js_edit, "typeof(window.ace) === 'undefined'") >= 2, true, "edit js ace timeout + ace guard");
test_eq(__LINE__, strpos($js_edit, 'errors.indexOf(name) < 0') !== false, true, "edit js message dedups file names");
test_eq(__LINE__, strpos($js_edit, 'nako3_editor_failed_pids') !== false, true, "edit js tracks per-pid editor failure");
test_eq(__LINE__, strpos($js_edit, 'nako3_load_recovery') !== false, true, "edit js retries after timeout to recover");
test_eq(__LINE__, strpos($js_edit, 'nako3_report_load_error') !== false, true, "edit js has report_load_error");
test_eq(__LINE__, strpos($js_edit, 'window.nako3_report_load_error') !== false, true, "edit js exposes report fn");
test_eq(__LINE__, strpos($js_edit, '__nako3_load_errors') !== false, true, "edit js reads error queue");
test_eq(__LINE__, strpos($js_edit, 'ランタイムの取得に失敗しました') !== false, true, "edit js failure message");
test_eq(__LINE__, strpos($js_edit, 'ネットワーク接続を確認して') !== false, true, "edit js failure message hint");
test_eq(__LINE__, strpos($js_edit, 'NAKO3_LOAD_RETRY_MS') !== false, true, "edit js bounded retry interval");
test_eq(__LINE__, strpos($js_edit, 'nako3error_div_') !== false, true, "edit js writes to error div");
test_eq(__LINE__, strpos($js_edit, "nako3_code_") !== false && strpos($js_edit, 'catch') !== false, true, "edit js fallback + catch setup failure");
// 従来の無限リトライ (固定 200ms で条件なし再帰) が残っていないこと
test_eq(__LINE__, strpos($js_edit, '}, 200);') === false, true, "edit js no bare 200ms infinite retry");

$js_nako3 = file_get_contents($dir . '/tpl-js-nako3.html');
test_eq(__LINE__, strpos($js_nako3, 'nako3_is_load_failed(use_textarea)') !== false, true, "run js checks load failure per-mode");
test_eq(__LINE__, strpos($js_nako3, 'nako3_editor_failed_pids') !== false, true, "run js checks per-pid editor failure");
test_eq(__LINE__, strpos($js_nako3, 'nako3_show_load_error') !== false, true, "run js shows load error");
// エラーはダイアログではなく、コードエディタのエラー表示の場所に出す (#44 ユーザー指示)
test_eq(__LINE__, strpos($js_nako3, 'alert(nako3_load_error_message') === false, true, "run js no dialog for load error");
test_eq(__LINE__, strpos($js_nako3, '現在「なでしこ3」を読み込み中です') !== false, true, "run js keeps loading alert");

$code = file_get_contents($dir . '/tpl-code.html');
test_eq(__LINE__, strpos($code, 'id="nako3error_div_{{pid}}"') !== false, true, "code tpl has error div");
test_eq(__LINE__, strpos($code, 'class="nako3error"') !== false, true, "code tpl error div class");
test_eq(__LINE__, strpos($code, 'role="alert"') !== false, true, "code tpl error div role");
test_eq(__LINE__, strpos($code, 'autoRunCount') !== false, true, "code tpl auto_run bounded");
test_eq(__LINE__, strpos($code, 'nako3_show_load_error') !== false, true, "code tpl auto_run shows error");

$style = file_get_contents($dir . '/tpl-style.html');
test_eq(__LINE__, strpos($style, '.nako3error') !== false, true, "style has nako3error");

// --- プラグイン全体の生成 HTML に、タグとエラー表示先が揃って含まれる ---
require_once dirname(__DIR__) . '/plugins/nako3.inc.php';
global $kona3conf;
$kona3conf['page'] = 'FrontPage';
$html = kona3plugins_nako3_execute(['「テスト」と表示']);
test_eq(__LINE__, strpos($html, 'id="nako3error_div_1"') !== false, true, "rendered error div pid1");
test_eq(__LINE__, strpos($html, '__nako3_load_errors') !== false, true, "rendered script onerror queue");
test_eq(__LINE__, strpos($html, 'nako3_show_load_error') !== false, true, "rendered show_load_error");
test_eq(__LINE__, strpos($html, 'ランタイムの取得に失敗しました') !== false, true, "rendered failure message");
$html2 = kona3plugins_nako3_execute(['「テスト2」と表示']);
test_eq(__LINE__, strpos($html2, 'id="nako3error_div_2"') !== false, true, "rendered error div pid2");
test_eq(__LINE__, strpos($html2, 'nako3.include_once') === false, true, "script_once only for pid1");
