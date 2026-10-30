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

// Run the behaviour tests when Node.js is available (this also covers
// the long-standing TODO of running JS tests from `just test`, #153).
$node = trim((string) shell_exec('command -v node 2>/dev/null'));
if ($node !== '') {
    $out = [];
    $code = 0;
    exec(escapeshellarg($node) . ' ' . escapeshellarg($js_test_path) . ' 2>&1', $out, $code);
    test_assert(__LINE__, $code === 0, 'highlight: node behaviour tests pass (#360): ' . implode(' ', array_slice($out, -2)));
} else {
    echo "# highlight: node not found, JS behaviour tests skipped\n";
}
