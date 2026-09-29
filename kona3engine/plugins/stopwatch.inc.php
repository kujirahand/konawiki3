<?php
/** JSのストップウォッチを表示する
 * - [書式] #stopwatch(always=yes)
 * - [引数]
 * -- always=yes ... 画面上部に固定表示する(省略可)
 */

/** 引数から always=yes を判定する */
function kona3plugins_stopwatch_is_always($args) {
  foreach ($args as $arg) {
    if (preg_match('/^always\s*=\s*(yes|true|1|on)$/i', trim($arg))) {
      return true;
    }
  }
  return false;
}

/** ウィジェットをラップする (always=yes なら画面上部の固定バーに移動する) */
function kona3plugins_stopwatch_wrap($kind, $inner, $script, $always) {
  global $kona3conf;
  $key = "plugins.{$kind}.count";
  $n = isset($kona3conf[$key]) ? $kona3conf[$key] + 1 : 1;
  $kona3conf[$key] = $n;
  $id = "kona3-{$kind}-{$n}";
  $script = str_replace('__ID__', $id, $script);
  $alwaysJs = $always ? 'true' : 'false';
  $head = "";
  if (empty($kona3conf['plugins.stopwatch.common'])) {
    $kona3conf['plugins.stopwatch.common'] = 1;
    $head = <<<EOS
<style>
.kona3-clock { display: inline-block; margin: 0.3em; padding: 0.4em 0.8em; border: 1px solid #aaa; border-radius: 6px; background: #fff; color: #222; font-family: sans-serif; }
.kona3-clock .kona3-clock-display { font-family: monospace; font-size: 1.8em; text-align: center; }
.kona3-clock .kona3-clock-buttons { text-align: center; }
.kona3-clock.kona3-clock-finished { background: #fdd; }
#kona3-clock-bar { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; display: flex; flex-wrap: wrap; justify-content: flex-end; pointer-events: none; }
#kona3-clock-bar .kona3-clock { pointer-events: auto; margin: 0.2em; padding: 0.1em 0.5em; box-shadow: 0 1px 4px rgba(0,0,0,0.3); }
#kona3-clock-bar .kona3-clock-display { font-size: 1.3em; }
</style>
<script>
window.kona3ClockMount = function(el, always) {
  if (!always) return;
  let bar = document.getElementById('kona3-clock-bar');
  if (!bar) {
    bar = document.createElement('div');
    bar.id = 'kona3-clock-bar';
    document.body.appendChild(bar);
  }
  bar.appendChild(el);
};
window.kona3ClockFormat = function(ms, withCenti) {
  const total = Math.max(0, Math.floor(ms / 10));
  const cs = total % 100;
  const s = Math.floor(total / 100) % 60;
  const m = Math.floor(total / 6000) % 60;
  const h = Math.floor(total / 360000);
  const p = (v) => String(v).padStart(2, '0');
  return (h > 0 ? h + ':' : '') + p(m) + ':' + p(s) + (withCenti ? '.' + p(cs) : '');
};
</script>

EOS;
  }
  return $head . "<div class=\"kona3-clock kona3-{$kind}\" id=\"{$id}\">{$inner}</div>\n"
    . "<script>(() => {\n"
    . "const root = document.getElementById('{$id}');\n"
    . $script
    . "\nwindow.kona3ClockMount(root, {$alwaysJs});\n})();</script>\n";
}

function kona3plugins_stopwatch_execute($args) {
  $always = kona3plugins_stopwatch_is_always($args);
  $inner = <<<EOS
<div class="kona3-clock-display">00:00.00</div>
<div class="kona3-clock-buttons">
<button type="button" data-act="start">スタート</button>
<button type="button" data-act="stop">停止</button>
<button type="button" data-act="reset">リセット</button>
</div>
EOS;
  $script = <<<'EOS'
const disp = root.querySelector('.kona3-clock-display');
let elapsed = 0, startedAt = 0, timer = null;
const render = () => {
  const now = timer ? elapsed + (Date.now() - startedAt) : elapsed;
  disp.textContent = window.kona3ClockFormat(now, true);
};
root.querySelector('[data-act=start]').onclick = () => {
  if (timer) return;
  startedAt = Date.now();
  timer = setInterval(render, 30);
};
root.querySelector('[data-act=stop]').onclick = () => {
  if (!timer) return;
  elapsed += Date.now() - startedAt;
  clearInterval(timer); timer = null;
  render();
};
root.querySelector('[data-act=reset]').onclick = () => {
  if (timer) { clearInterval(timer); timer = null; }
  elapsed = 0;
  render();
};
EOS;
  return kona3plugins_stopwatch_wrap('stopwatch', $inner, $script, $always);
}
