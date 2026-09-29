<?php
require_once __DIR__ . '/stopwatch.inc.php';

/** JSのデジタル時計を表示する
 * - [書式] #watch(always=yes)
 * - [引数]
 * -- always=yes ... 画面上部に固定表示する(省略可)
 */
function kona3plugins_watch_execute($args) {
  $always = kona3plugins_stopwatch_is_always($args);
  $inner = '<div class="kona3-clock-display">--:--:--</div>';
  $script = <<<'EOS'
const disp = root.querySelector('.kona3-clock-display');
const p = (v) => String(v).padStart(2, '0');
const render = () => {
  const d = new Date();
  disp.textContent = p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
};
render();
setInterval(render, 250);
EOS;
  return kona3plugins_stopwatch_wrap('watch', $inner, $script, $always);
}
