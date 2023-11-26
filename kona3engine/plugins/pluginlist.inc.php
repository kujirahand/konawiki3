<?php
/** プラグイン一覧を列挙
 * - [書式] #pluginlist
 * - [備考]
 * - [公開設定] 公開
 */

global $GITHUB_PLUGIN_URL;
$GITHUB_PLUGIN_URL = 'https://github.com/kujirahand/konawiki3/blob/master/kona3engine/plugins';

// wiki page から実行される
function kona3plugins_pluginlist_execute($params) {
    $html = kona3__get_pluginlist();
    return $html;
}

// ?xxx&plugin&name=nako3&... より実行されるaction
// ?xxx&plugin&name=nako3&filecode=xxx にてdataフォルダ以下のxxx.nako3を読み込む
// Plugin APIを実装するのに使われる
function kona3plugins_pluginlist_action() {
    $html = kona3__get_pluginlist();
    kona3showMessage('Plugin List', $html);
}

function kona3__get_pluginlist() {
    global $GITHUB_PLUGIN_URL;
    $files = glob(__DIR__.'/*.inc.php');
    sort($files);
    $index = '<ul>';
    $descript = '';
    foreach ($files as $f) {
        $base = basename($f);
        $base_url = urlencode($base);
        $pname = preg_replace('#\.inc\.php$#', '', $base);
        $pname2 = urldecode($pname);
        $url = htmlspecialchars("$GITHUB_PLUGIN_URL/$base_url");
        $txt = file_get_contents($f);
        $descript = '?';
        if (preg_match('#\/\*\* (.+)#', $txt, $m)) {
            $s = $m[1];
            $s = preg_replace('#\*\/\s*$#', '', $s);
            $descript = htmlspecialchars($s, ENT_QUOTES);
        }
        $index .= "<li><a href='{$url}'>$pname2</a> --- {$descript}</li>";
    }
    $index .= '</ul>';
    return <<<__EOS__
{$index}
__EOS__;
}
