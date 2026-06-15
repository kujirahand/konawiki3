<?php
/** konawiki plugins -- テキスト音楽サクラMML (SoundFont)
 * - [書式] {{{#sakuramml_sf(rows=8) データ }}}
 * - [引数]
 * -- rows=n ... エディタの行数
 * -- ver=x.x.x ... sakuramml-libplayerのバージョン指定
 * -- sf=url ... SoundFontのURL
 * -- データ  ... 再生したいデータ
 * - [使用例]
{{{
_{{{#sakuramml_sf
ドレミファソ
_}}}
}}}
 * - [公開設定] 公開
 */
if (!defined("SAKURAMML_SF_LIBPLAYER_VERSION")) {
    define("SAKURAMML_SF_LIBPLAYER_VERSION", "0.1.1");
}
if (!defined("SAKURAMML_SF_SOUNDFONT_URL")) {
    define("SAKURAMML_SF_SOUNDFONT_URL", "https://sakuramml.com/player/fonts/TimGM6mb.sf2");
}

function kona3plugins_sakuramml_sf_execute($params)
{
    global $kona3conf;
    $pid = kona3_getPluginInfo("sakuramml_sf", "pid", 1);
    kona3_setPluginInfo("sakuramml_sf", "pid", $pid + 1);

    $libplayer_version = SAKURAMML_SF_LIBPLAYER_VERSION;
    $soundfont_url = kona3plugins_sakuramml_sf_getSoundFontProxyUrl($kona3conf["page"]);
    $mml_lines = [];
    $rows = 8;
    foreach ($params as $line) {
        $line = trim($line);
        if (preg_match('#^rows\=(\d+)#', $line, $m)) {
            $rows = intval($m[1]);
            continue;
        }
        if (preg_match('#^ver\=([0-9.]+)#', $line, $m)) {
            $libplayer_version = $m[1];
            continue;
        }
        if (preg_match('#^sf\=(.+)#', $line, $m)) { continue; }
        $mml_lines[] = $line;
    }
    $mml = implode("\n", $mml_lines);

    $args = [
        "libplayer_version" => $libplayer_version,
        "pid" => $pid,
        "mml" => htmlspecialchars($mml, ENT_QUOTES, 'UTF-8'),
        "rows" => $rows,
        "soundfont_url" => $soundfont_url,
    ];

    $html = "";
    if ($pid == 1) {
        $html .= kona3plugins_sakuramml_sf_getTemplateHeader($args);
    }
    $html .= kona3plugins_sakuramml_sf_getTemplate($args);
    return $html;
}

function kona3plugins_sakuramml_sf_getSoundFontProxyUrl($page)
{
    return "index.php?" . urlencode($page) . "&plugin&name=sakuramml_sf&m=soundfont";
}

function kona3plugins_sakuramml_sf_action()
{
    $mode = isset($_GET['m']) ? $_GET['m'] : '';
    if ($mode !== 'soundfont') {
        header('HTTP/1.0 404 Not Found');
        echo 'Not Found';
        exit;
    }
    kona3plugins_sakuramml_sf_sendSoundFont();
}

function kona3plugins_sakuramml_sf_sendSoundFont()
{
    $cache_file = KONA3_DIR_CACHE . '/sakuramml_sf_TimGM6mb.sf2';
    if (!file_exists($cache_file) || filesize($cache_file) === 0) {
        $data = kona3plugins_sakuramml_sf_fetchUrl(SAKURAMML_SF_SOUNDFONT_URL);
        if ($data === false || $data === '') {
            header('HTTP/1.0 502 Bad Gateway');
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'SoundFont download failed.';
            exit;
        }
        kona3lock_save($cache_file, $data);
    }
    clearstatcache(true, $cache_file);
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($cache_file));
    header('Cache-Control: public, max-age=86400');
    readfile($cache_file);
    exit;
}

function kona3plugins_sakuramml_sf_fetchUrl($url)
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    return @file_get_contents($url, false, $context);
}

function kona3plugins_sakuramml_sf_getTemplate($args)
{
    extract($args);
    return <<< EOS__
<!-- #sakuramml_sf.parts.pid{$pid}-->
<div class="sakuramml_sf_block" id="sakuramml_sf_block{$pid}">
  <div class="sakuramml_sf_version_outer">
    sakuramml-libplayer v.<span class="sakuramml_sf_version"></span>
    <a href="https://sakuramml.com/" target="_blank" rel="noopener">♪</a>
  </div>
  <div>
    <textarea id="sakuramml_sf_txt{$pid}" cols="60" rows="{$rows}" class="sakuramml_sf_textarea">{$mml}</textarea>
  </div>
  <div id="sakuramml_sf_player{$pid}" class="sakuramml_sf_player_buttons" style="display:none;">
    <button id="sakuramml_sf_btnPlay{$pid}" type="button" class="sakuramml_sf_button">▶ Play</button>
    <button id="sakuramml_sf_btnStop{$pid}" type="button" class="sakuramml_sf_button">Stop</button>
  </div>
  <div id="sakuramml_sf_status{$pid}" class="sakuramml_sf_status" style="display:none;"></div>
</div>
<script type="module">
  window.sakuramml_sf_setup({$pid});
</script>
<!-- end of #sakuramml_sf.parts.pid{$pid}-->

EOS__;
}

function kona3plugins_sakuramml_sf_getTemplateHeader($args)
{
    extract($args);
    $libplayer_url = "https://cdn.jsdelivr.net/npm/sakuramml-libplayer@{$libplayer_version}/sakura-mml-player.js";
    $json_flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $libplayer_url_json = json_encode($libplayer_url, $json_flags);
    $soundfont_url_json = json_encode($soundfont_url, $json_flags);
    $libplayer_version_json = json_encode($libplayer_version, $json_flags);
    return <<< __EOS__

<!-- #sakuramml_sf (sakuramml-libplayer) -->
<style>
.sakuramml_sf_block {
    margin-top: 1em;
    margin-bottom: 1em;
    padding-top: 1em;
    padding-bottom: 1em;
    border-top: 1px solid silver;
}
.sakuramml_sf_version_outer {
    text-align: right;
    font-size: 0.4em;
    color: silver;
    background-color: #fffaf7;
}
.sakuramml_sf_textarea {
    width: 97%;
    padding: 8px;
    background-color: #fffff7;
}
.sakuramml_sf_button {
    padding: 8px;
}
.sakuramml_sf_status {
    padding: 0.5em;
    font-size: 0.8em;
    color: gray;
    height: 200px;
    overflow: scroll;
}
</style>

<script type="module">
  import { SakuraPlayer } from {$libplayer_url_json};

  window.__sakuramml_sf = {
    SakuraPlayer,
    version: {$libplayer_version_json},
    soundfontUrl: {$soundfont_url_json},
    players: {},
    midiCache: {}
  };
  console.log('sakuramml-libplayer loaded: ver.', window.__sakuramml_sf.version);
  for (const e of document.querySelectorAll('.sakuramml_sf_player_buttons')) {
    e.style.display = 'block';
  }
  for (const e of document.querySelectorAll('.sakuramml_sf_version')) {
    e.innerHTML = window.__sakuramml_sf.version;
  }
</script>
<script>
  function sakurammlSfToHtml(s) {
    s = String(s)
    s = s.replace(/&/g, '&amp;')
    s = s.replace(/</g, '&lt;')
    s = s.replace(/>/g, '&gt;')
    s = s.replace(/\\n/g, '<br>\\n')
    return s
  }
  function sakurammlSfSetStatus(pid, message, color) {
    const status = document.getElementById('sakuramml_sf_status' + pid)
    status.innerHTML = sakurammlSfToHtml(message)
    status.style.color = color || 'gray'
    status.style.display = message ? 'block' : 'none'
  }
  async function sakurammlSfGetPlayer(pid) {
    if (!window.__sakuramml_sf) {
      throw new Error('sakuramml-libplayer is not loaded yet.')
    }
    if (!window.__sakuramml_sf.players[pid]) {
      const player = new window.__sakuramml_sf.SakuraPlayer()
      await player.init()
      player.__sakurammlSfSoundFontLoaded = false
      window.__sakuramml_sf.players[pid] = player
    }
    return window.__sakuramml_sf.players[pid]
  }
  async function sakurammlSfEnsureSoundFont(pid) {
    const player = await sakurammlSfGetPlayer(pid)
    if (!player.__sakurammlSfSoundFontLoaded) {
      sakurammlSfSetStatus(pid, 'Loading SoundFont...', 'gray')
      await player.loadSoundFont(window.__sakuramml_sf.soundfontUrl)
      player.__sakurammlSfSoundFontLoaded = true
    }
    return player
  }
  async function sakurammlSfCompile(pid) {
    const player = await sakurammlSfGetPlayer(pid)
    const mml = document.getElementById('sakuramml_sf_txt' + pid).value
    const result = await player.compileMML(mml)
    window.__sakuramml_sf.midiCache[pid] = {
      source: mml,
      midi: result.midi
    }
    if (result.log) {
      sakurammlSfSetStatus(pid, result.log, 'gray')
    } else {
      sakurammlSfSetStatus(pid, 'Compile OK', 'gray')
    }
    return result.midi
  }
  async function sakurammlSfPlay(pid) {
    const player = await sakurammlSfEnsureSoundFont(pid)
    const mml = document.getElementById('sakuramml_sf_txt' + pid).value
    const cache = window.__sakuramml_sf.midiCache[pid]
    const midi = cache && cache.source === mml ? cache.midi : await sakurammlSfCompile(pid)
    player.loadMIDI(midi)
    await player.play()
  }
  window.sakuramml_sf_setup = function (pid) {
    document.getElementById('sakuramml_sf_btnPlay' + pid).onclick = () => {
      sakurammlSfSetStatus(pid, '', 'gray')
      sakurammlSfPlay(pid).catch((err) => {
        console.error(err)
        sakurammlSfSetStatus(pid, '[SYSTEM_ERROR]' + err.toString(), 'red')
      })
    }
    document.getElementById('sakuramml_sf_btnStop' + pid).onclick = () => {
      const player = window.__sakuramml_sf && window.__sakuramml_sf.players[pid]
      if (player) {
        player.stop()
      }
    }
  }
</script>
__EOS__;
}
