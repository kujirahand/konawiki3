<?php
/** konawiki plugins -- ピコサクラMML
 * - [書式] {{{#sakuramml(rows=8) データ }}}
 * - [引数]
 * -- rows=n ... エディタの行数
 * -- ver=x.x.x ... サクラのバージョン指定
 * -- データ  ... 再生したいデータ
 * - [使用例]
{{{
_{{{#sakuramml
ドレミファソ
_}}}
}}}
 * - [公開設定] 公開
 */
define("SAKURAMML_VERSION", "0.1.37");

function kona3plugins_sakuramml_execute($params)
{
    $pid = kona3_getPluginInfo("sakuramml", "pid", 1);
    kona3_setPluginInfo("sakuramml", "pid", $pid+1);
    
    $sakura_version = SAKURAMML_VERSION;
    $mml = "";
    $rows = 8;
    foreach ($params as $line) {
        $line = trim($line);
        if (preg_match('#^rows\=(\d+)#', $line, $m)) {
            $rows = intval($m[1]);
            continue;
        }
        if (preg_match('#^ver\=(\d+)#', $line, $m)) {
            $sakura_version = intval($m[1]);
            continue;
        }
        if ($mml == "") {
            $mml = $line;
        }
    }
    
    $html = "";
    $args = [
        "sakura_version" => $sakura_version,
        "pid" => $pid,
        "mml" => htmlspecialchars($mml),
        "rows" => $rows,
    ];
    if ($pid == 1) { // 初回のみヘッダを表示
        $html .= getTemplateHeader($args);
    }
    $html .= getTemplate($args);
    return $html;
}

function getTemplate($args) {
    extract($args);
    return <<< EOS__
<!-- #sakuramml.parts.pid{$pid}-->
<div class="sakuramml_block" id="sakuramml_bock{$pid}">
  <div class="sakuramml_version_outer" style="text-align:right;font-size:0.4em; color:silver; background-color:#fffaf7;">
        sakuramml v.<span class="sakuramml_version"></span>
        <a href="https://sakuramml.com/" target="_blank">♪</a>
  </div>
  <div>
    <textarea id="sakuramml_txt{$pid}" cols="60" rows="{$rows}" style="width:97%;padding:8px;background-color:#fffff7;">{$mml}</textarea>
  </div>
  <div id="player{$pid}" class="sakuramml_player_buttons" style="display:none;">
    <button id="btnPlay{$pid}" style="padding:8px;">▶ Play</button>
    <button id="btnStop{$pid}" style="padding:8px;">Stop</button> &nbsp;
  </div>
  <div id="skr_error_msg{$pid}" style="padding:0.5em; font-size: 0.8em; color: gray; height: 200px; overflow: scroll; display:none;"></div>
</div>
<script type="module">
    window.sakuramml_setup({$pid});
</script>
<!-- end of #sakuramml.parts.pid{$pid}-->

EOS__;
}

function getTemplateHeader($args) {
    extract($args);
    return <<< __EOS__

<!-- #sakuramml (pico sakura) -->
<style>
.sakuramml_block {
    margin-top: 1em;
    margin-bottom: 1em;
    padding-top: 1em;
    padding-bottom: 1em;
    border-top: 1px solid silver;
}
</style>

<!-- pico sakura ------------------------------------------------>
<!-- picoaudio player -->
<script src="https://cdn.jsdelivr.net/npm/picoaudio@1.1.2/dist/browser/PicoAudio.min.js"></script>
<script type="module">
  // load module
  import init, {SakuraCompiler, get_version} from 'https://cdn.jsdelivr.net/npm/sakuramml@{$sakura_version}/sakuramml.js';
  init().then(() => {
    // set global object for sakuramml compiler
    window.__sakuramml = {
      SakuraCompiler,
      version: get_version()
    };
    console.log('sakuramml loaded: ver.', window.__sakuramml.version);
    for (let e of document.querySelectorAll('.sakuramml_player_buttons')) {
        e.style.display = 'block';
    }
    for (let e of document.querySelectorAll('.sakuramml_version')) {
        e.innerHTML = window.__sakuramml.version;
    }
  }).catch(err => {
    console.error(err);
    document.getElementById('skr_error_msg1').innerHTML = '[LOAD_ERROR]' + tohtml(err.toString())
  });
</script>
<script>
  window.sakuramml_pid = 1;
  function tohtml(s) {
    s = s.replace(/&/g,'&amp;')
    s = s.replace(/</g,'&lt;')
    s = s.replace(/>/g,'&gt;')
    s = s.replace(/\\n/g,'<br>\\n')
    return s
  }
  window.sakuramml_setup = function (pid) {
    document.getElementById('btnPlay' + pid).onclick = () => {
      playMML(pid)
    };
    document.getElementById('btnStop' + pid).onclick = () => {
      if (window.__picoaudio !== undefined) {
        window.__picoaudio.initStatus();
      }
    }
  };
  function playMML(pid) {
    window.sakuramml_pid = pid;
    const errorMsg = document.getElementById('skr_error_msg' + pid)
    // --------------------------------------------------
    // init player
    // --------------------------------------------------
    let pico = null;
    if (typeof(window.__picoaudio) !== 'undefined') {
      window.__picoaudio.initStatus();
    }
    pico = window.__picoaudio = new PicoAudio();
    pico.init();
    try {
      // --------------------------------------------------
      // sakuramml compile
      // --------------------------------------------------
      const txt = document.getElementById('sakuramml_txt' + pid)
      const mml = txt.value;
      const com = window.__sakuramml.SakuraCompiler.new()
      const bin = com.compile(mml)
      const logStr = com.get_log()
      if (logStr) {
        console.log('[sakuramml]', logStr)
	errorMsg.innerHTML = tohtml(logStr)
        errorMsg.style.color = 'gray'
        errorMsg.style.display = 'block'
      }
      const smfData = new Uint8Array(bin);
      // --------------------------------------------------
      // set smf to picoaudio
      // --------------------------------------------------
      const parsedData = pico.parseSMF(smfData);
      pico.setData(parsedData);
      pico.play();
    } catch (err) {
      console.error(err);
      errorMsg.style.color = 'red'
      errorMsg.style.display = 'block'
      errorMsg.innerHTML = '[SYSTEM_ERROR]' + tohtml(err.toString())
    }
  }
</script>
__EOS__;
}

