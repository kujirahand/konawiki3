<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/kona3lib.inc.php';

// Test 1: Simple hook registration and triggering
$called = false;
kona3addHook('test_event_1', function() use (&$called) {
    $called = true;
});

test_eq(__LINE__, $called, false, "Hook should not be called before trigger");
kona3triggerHook('test_event_1');
test_eq(__LINE__, $called, true, "Hook should be called after trigger");

// Test 2: Hook with arguments propagation
$received_args = [];
kona3addHook('test_event_2', function($arg1, $arg2) use (&$received_args) {
    $received_args = [$arg1, $arg2];
});

kona3triggerHook('test_event_2', 'foo', 'bar');
test_eq(__LINE__, count($received_args), 2, "Hook received correct number of arguments");
test_eq(__LINE__, $received_args[0], 'foo', "First argument is foo");
test_eq(__LINE__, $received_args[1], 'bar', "Second argument is bar");

// Test 3: Multiple hooks execution order
$call_order = [];
kona3addHook('test_event_3', function() use (&$call_order) {
    $call_order[] = 1;
});
kona3addHook('test_event_3', function() use (&$call_order) {
    $call_order[] = 2;
});

kona3triggerHook('test_event_3');
test_eq(__LINE__, count($call_order), 2, "Both hooks triggered");
test_eq(__LINE__, $call_order[0], 1, "First hook ran first");
test_eq(__LINE__, $call_order[1], 2, "Second hook ran second");

// Test 4: Exception propagation
kona3addHook('test_event_4', function() {
    throw new Exception("Hook error");
});

$caught = false;
try {
    kona3triggerHook('test_event_4');
} catch (Exception $e) {
    if ($e->getMessage() === 'Hook error') {
        $caught = true;
    }
}
test_eq(__LINE__, $caught, true, "Exception was successfully caught");

// Test 5: Integrated write hook (page update)
require_once dirname(__DIR__) . '/kona3page_updated.inc.php';

global $kona3hooks;
test_assert(__LINE__, isset($kona3hooks['write']), "write hook should be registered");
test_assert(__LINE__, count($kona3hooks['write']) >= 3, "At least Tag/Meta/Alias, Discord, and Git hooks should be registered");

