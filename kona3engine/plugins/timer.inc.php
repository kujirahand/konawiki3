<?php
require_once __DIR__ . '/stopwatch.inc.php';

/** JSのカウントダウンタイマーを表示する(1分/3分/5分/30分/カスタム)
 * - [書式] #timer(always=yes)
 * - [引数]
 * -- always=yes ... 画面上部に固定表示する(省略可)
 */
function kona3plugins_timer_execute($args) {
  $always = kona3plugins_stopwatch_is_always($args);
  $inner = <<<EOS
<div class="kona3-clock-display">05:00</div>
<div class="kona3-clock-buttons">
<select data-act="preset">
<option value="1">1分</option>
<option value="3">3分</option>
<option value="5" selected>5分</option>
<option value="30">30分</option>
<option value="custom">カスタム</option>
</select>
<input type="number" data-act="custom" min="1" max="999" value="10" style="width:4em; display:none"><span data-act="customlabel" style="display:none">分</span>
<button type="button" data-act="start">スタート</button>
<button type="button" data-act="stop">停止</button>
<button type="button" data-act="reset">リセット</button>
</div>
EOS;
  $script = <<<'EOS'
const disp = root.querySelector('.kona3-clock-display');
const sel = root.querySelector('[data-act=preset]');
const custom = root.querySelector('[data-act=custom]');
const customLabel = root.querySelector('[data-act=customlabel]');
let total = 0, remain = 0, endAt = 0, timer = null;
const getTotal = () => {
  const v = sel.value === 'custom' ? parseFloat(custom.value) : parseFloat(sel.value);
  return (isNaN(v) || v <= 0 ? 1 : v) * 60000;
};
const render = () => {
  const left = timer ? endAt - Date.now() : remain;
  disp.textContent = window.kona3ClockFormat(Math.ceil(Math.max(0, left) / 1000) * 1000, false);
  if (timer && left <= 0) finish();
};
const beep = () => {
  try {
    const ac = new (window.AudioContext || window.webkitAudioContext)();
    for (let i = 0; i < 3; i++) {
      const o = ac.createOscillator();
      o.connect(ac.destination);
      o.frequency.value = 880;
      o.start(ac.currentTime + i * 0.4);
      o.stop(ac.currentTime + i * 0.4 + 0.2);
    }
  } catch (e) {}
};
const finish = () => {
  clearInterval(timer); timer = null;
  remain = 0;
  root.classList.add('kona3-clock-finished');
  disp.textContent = window.kona3ClockFormat(0, false);
  beep();
};
const reset = () => {
  if (timer) { clearInterval(timer); timer = null; }
  root.classList.remove('kona3-clock-finished');
  total = remain = getTotal();
  render();
};
sel.onchange = () => {
  const c = sel.value === 'custom';
  custom.style.display = c ? '' : 'none';
  customLabel.style.display = c ? '' : 'none';
  reset();
};
custom.onchange = reset;
root.querySelector('[data-act=start]').onclick = () => {
  if (timer) return;
  if (remain <= 0) reset();
  endAt = Date.now() + remain;
  timer = setInterval(render, 100);
};
root.querySelector('[data-act=stop]').onclick = () => {
  if (!timer) return;
  remain = Math.max(0, endAt - Date.now());
  clearInterval(timer); timer = null;
  render();
};
root.querySelector('[data-act=reset]').onclick = reset;
reset();
EOS;
  return kona3plugins_stopwatch_wrap('timer', $inner, $script, $always);
}
