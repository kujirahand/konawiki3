<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/plugins/stopwatch.inc.php';
require_once dirname(__DIR__) . '/plugins/watch.inc.php';
require_once dirname(__DIR__) . '/plugins/timer.inc.php';

global $kona3conf;
foreach (['stopwatch', 'watch', 'timer'] as $kind) {
    unset($kona3conf["plugins.$kind.count"]);
}
unset($kona3conf['plugins.stopwatch.common']);

// --- #stopwatch ---
$html = kona3plugins_stopwatch_execute([]);
test_assert(__LINE__, strpos($html, 'kona3-stopwatch-1') !== false, '#stopwatch: element id is output');
test_assert(__LINE__, strpos($html, 'data-act="start"') !== false && strpos($html, 'data-act="stop"') !== false && strpos($html, 'data-act="reset"') !== false, '#stopwatch: has start/stop/reset buttons');
test_assert(__LINE__, strpos($html, 'kona3ClockMount') !== false, '#stopwatch: common script output on first use');
test_assert(__LINE__, strpos($html, "kona3ClockMount(root, false)") !== false, '#stopwatch: not fixed by default');
test_assert(__LINE__, strpos($html, '__ID__') === false, '#stopwatch: placeholder replaced');

$html = kona3plugins_stopwatch_execute(['always=yes']);
test_assert(__LINE__, strpos($html, 'kona3-stopwatch-2') !== false, '#stopwatch: id is incremented');
test_assert(__LINE__, strpos($html, 'window.kona3ClockMount = ') === false, '#stopwatch: common script only once');
test_assert(__LINE__, strpos($html, "kona3ClockMount(root, true)") !== false, '#stopwatch: always=yes fixes to top');

// --- #watch ---
$html = kona3plugins_watch_execute(['always=yes']);
test_assert(__LINE__, strpos($html, 'kona3-watch-1') !== false, '#watch: element id is output');
test_assert(__LINE__, strpos($html, "kona3ClockMount(root, true)") !== false, '#watch: always=yes fixes to top');
$html = kona3plugins_watch_execute(['always=no']);
test_assert(__LINE__, strpos($html, "kona3ClockMount(root, false)") !== false, '#watch: always=no is not fixed');

// --- #timer ---
$html = kona3plugins_timer_execute([]);
foreach (['1', '3', '5', '30', 'custom'] as $v) {
    test_assert(__LINE__, strpos($html, "<option value=\"$v\"") !== false, "#timer: has option $v");
}
test_assert(__LINE__, strpos($html, "kona3ClockMount(root, false)") !== false, '#timer: not fixed by default');
$html = kona3plugins_timer_execute(['always=yes']);
test_assert(__LINE__, strpos($html, 'kona3-timer-2') !== false, '#timer: id is incremented');
test_assert(__LINE__, strpos($html, "kona3ClockMount(root, true)") !== false, '#timer: always=yes fixes to top');
