<?php
require_once __DIR__ . '/test_common.inc.php';

// Test for the editor line numbers (issue #381)
$root = dirname(__DIR__);
$js_path = $root . '/resource/edit_linenum.js';
test_assert(__LINE__, file_exists($js_path), 'linenum: edit_linenum.js exists (#381)');
$js = file_exists($js_path) ? file_get_contents($js_path) : '';
$edit_js = file_get_contents($root . '/resource/edit.js');
$edit_css = file_get_contents($root . '/resource/edit.css');
$edit_html = file_get_contents($root . '/template/edit.html');
$edit_action = file_get_contents($root . '/action/edit.inc.php');
$conf = file_get_contents($root . '/kona3conf.inc.php');

foreach (['kona3EditLineNumber', 'kona3EditorMetrics', 'function lineHeights(', 'function caretTop(', 'kona3_edit_linenum', 'editor_line_number_default'] as $needle) {
    test_assert(__LINE__, strpos($js, $needle) !== false, "linenum: edit_linenum.js has {$needle} (#381)");
}
// 設定画面のスイッチ
test_assert(__LINE__, strpos($edit_html, 'id="k3ln_toggle"') !== false, 'linenum: settings panel has a switch (#381)');
test_assert(__LINE__, strpos($edit_html, 'id="k3ln_toggle"') > strpos($edit_html, 'id="settings_div"'), 'linenum: switch lives in the settings panel (#381)');
test_assert(__LINE__, strpos($edit_html, 'id="editor_line_number_default"') !== false, 'linenum: default flag is passed to the page (#381)');
test_assert(__LINE__, strpos($edit_js, "qs('#k3ln_toggle')") !== false, 'linenum: edit.js wires the switch (#381)');
// 既定はオン
test_assert(__LINE__, strpos($js, "input.value !== '0'") !== false, 'linenum: on by default unless the flag is 0 (#381)');
test_assert(__LINE__, preg_match("/'editor_line_number_default' => \[[^\]]*'default' => TRUE/", $conf) === 1, 'linenum: config default is TRUE (#381)');
test_assert(__LINE__, strpos($edit_action, "kona3getConf('editor_line_number_default', TRUE)") !== false, 'linenum: edit action passes the config (#381)');
foreach (['conf.ja.inc.php', 'conf.en.inc.php'] as $f) {
    $c = file_get_contents($root . '/lang/' . $f);
    test_assert(__LINE__, strpos($c, 'editor_line_number_default') !== false, "linenum: {$f} documents the setting (#381)");
}
// スクリプトは edit.js より前に登録する
$p_ln = strpos($edit_action, 'edit_linenum.js');
$p_edit = strpos($edit_action, "'edit.js'");
test_assert(__LINE__, $p_ln !== false && $p_edit !== false && $p_ln < $p_edit, 'linenum: script is registered before edit.js (#381)');
// CSS
foreach (['.k3ln-gutter', '.k3ln-num', '.editor.k3ln-on'] as $needle) {
    test_assert(__LINE__, strpos($edit_css, $needle) !== false, "linenum: edit.css styles {$needle} (#381)");
}
// 設定オフ時はガターを取り除く
test_assert(__LINE__, strpos($js, "classList.remove('k3ln-on')") !== false, 'linenum: disable() removes the gutter (#381)');

$node = trim((string) @shell_exec('command -v node 2>/dev/null'));
if ($node !== '') {
    $out = [];
    $code = 0;
    exec(escapeshellarg($node) . ' --check ' . escapeshellarg($js_path) . ' 2>&1', $out, $code);
    test_assert(__LINE__, $code === 0, 'linenum: edit_linenum.js is valid JS (#381)');
}
