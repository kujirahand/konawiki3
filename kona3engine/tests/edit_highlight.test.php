<?php
require_once __DIR__ . '/test_common.inc.php';

// Test for the editor syntax-highlight core (issue #360, PR1: tokenizer
// only, no UI wiring yet). The DOM layer arrives in a later PR.

$root = dirname(__DIR__);
$core_path = $root . '/resource/edit_highlight_core.js';
$js_test_path = $root . '/tests/js/edit_highlight_core.test.js';

test_assert(__LINE__, file_exists($core_path), 'highlight: core file exists (#360)');
test_assert(__LINE__, file_exists($js_test_path), 'highlight: node test exists (#360)');
$core = file_exists($core_path) ? file_get_contents($core_path) : '';

foreach (['k3hlEscape', 'k3hlInitialState', 'k3hlTokenizeLine', 'k3hlRender'] as $fn) {
    test_assert(__LINE__, strpos($core, 'function ' . $fn . '(') !== false, "highlight: core has {$fn}() (#360)");
}
// The core must stay DOM-free so it runs under Node unchanged.
test_assert(__LINE__, strpos($core, 'document.') === false, 'highlight: core does not touch the DOM (#360)');
test_assert(__LINE__, strpos($core, 'module.exports') !== false, 'highlight: core exports for Node tests (#360)');

// --- PR2: backdrop layer wiring ---
$layer_path = $root . '/resource/edit_highlight.js';
test_assert(__LINE__, file_exists($layer_path), 'highlight: layer file exists (#360 PR2)');
$layer = file_exists($layer_path) ? file_get_contents($layer_path) : '';
test_assert(__LINE__, strpos($layer, 'kona3EditHighlight') !== false, 'highlight: layer publishes its API (#360 PR2)');
test_assert(__LINE__, strpos($layer, 'k3hl-backdrop') !== false, 'highlight: layer builds the backdrop (#360 PR2)');
$edit_inc = file_get_contents($root . '/action/edit.inc.php');
$p_core = strpos($edit_inc, "'edit_highlight_core.js'");
$p_layer = strpos($edit_inc, "'edit_highlight.js'");
$p_edit = strpos($edit_inc, "'edit.js'");
test_assert(__LINE__, $p_core !== false && $p_layer !== false && $p_core < $p_layer && $p_layer < $p_edit,
    'highlight: edit screen loads core, then layer, then edit.js (#360 PR2)');
$edit_css = file_get_contents($root . '/resource/edit.css');
foreach (['.k3hl-backdrop', '#edit_txt.k3hl-on', '.k3hl-mark', '.k3hl-strong', 'forced-colors'] as $sel) {
    test_assert(__LINE__, strpos($edit_css, $sel) !== false, "highlight: edit.css has {$sel} (#360 PR2)");
}
test_assert(__LINE__, file_exists($root . '/tests/js/edit_highlight_dom.test.js'), 'highlight: DOM test exists (#360 PR2)');

// Run the behaviour tests when Node.js is available (this also covers
// the long-standing TODO of running JS tests from `just test`, #153).
$node = trim((string) shell_exec('command -v node 2>/dev/null'));
if ($node !== '') {
    $out = [];
    $code = 0;
    $cout = [];
    $ccode = 0;
    exec(escapeshellarg($node) . ' --check ' . escapeshellarg($core_path) . ' 2>&1', $cout, $ccode);
    test_assert(__LINE__, $ccode === 0, 'highlight: core is syntactically valid JS (#360)');
    exec(escapeshellarg($node) . ' ' . escapeshellarg($js_test_path) . ' 2>&1', $out, $code);
    test_assert(__LINE__, $code === 0, 'highlight: node behaviour tests pass (#360): ' . implode(' ', array_slice($out, -2)));
    $dout = [];
    $dcode = 0;
    exec(escapeshellarg($node) . ' ' . escapeshellarg($root . '/tests/js/edit_highlight_dom.test.js') . ' 2>&1', $dout, $dcode);
    test_assert(__LINE__, $dcode === 0, 'highlight: node DOM tests pass (#360 PR2): ' . implode(' ', array_slice($dout, -2)));
} else {
    echo "# highlight: node not found, JS behaviour tests skipped\n";
}
