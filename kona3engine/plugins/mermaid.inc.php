<?php

/** mermaid notation
 * - [Usage] {{{#mermaid(caption,filename) ... }}}
 * caption --- キャプション
 * filename --- もし、mermaid-cliがインストールされているならSVGファイルを出力する
 * 設定ファイルで「mermaid_cli」を指定する必要がある
 * ```
 * npm install -g @mermaid-js/mermaid-cli
 * which mmdc # コマンドの場所を確認して、konawiki3の設定に指定
 * ```
 */

function kona3plugins_mermaid_write_source($full_mmd, $text)
{
    $current_text = file_exists($full_mmd) ? file_get_contents($full_mmd) : false;
    if ($current_text === $text) {
        return false;
    }
    file_put_contents($full_mmd, $text);
    clearstatcache(true, $full_mmd);
    return true;
}

function kona3plugins_mermaid_needs_regenerate($full_mmd, $full_svg)
{
    clearstatcache(true, $full_mmd);
    clearstatcache(true, $full_svg);
    if (!file_exists($full_svg)) {
        return true;
    }
    if (!file_exists($full_mmd)) {
        return true;
    }
    return filemtime($full_mmd) > filemtime($full_svg);
}

function kona3plugins_mermaid_execute($args)
{
    global $kona3conf;
    $caption = "";
    $filename = "";
    $text = "";
    if (count($args) >= 3) {
        $caption = array_shift($args);
        $filename = array_shift($args); 
        $text = array_shift($args);
    } else if (count($args) >= 2) {
        $caption = array_shift($args);
        $text = array_shift($args);
    } else {
        $text = array_shift($args);
    }
    $head = '';
    //
    $text_html = htmlspecialchars($text);
    $plugkey = "plugins.mermaid.init";
    if (empty($kona3conf[$plugkey])) {
        $kona3conf[$plugkey] = 1;
        $head = <<<EOS
<script type="module">
  import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11.15.0/dist/mermaid.esm.min.mjs';
  mermaid.initialize({ startOnLoad: true });
</script>
EOS;
    }
    // export SVGファイル
    $error = "";
    $svg_link = "";
    $mermaid_cli = kona3getConf("mermaid_cli", "");
    if ($filename) {
        // 正規化 - [a-zA-Z0-9\-_]のみにする
        $filename = preg_replace("/^file=/", "", $filename); // file=を削除
        $filename = preg_replace("/\.svg$/", "", $filename); // .svgを削除
        $filename = preg_replace("/[^a-zA-Z0-9\-\_\.]/", "_", $filename);
        // WIKIページから相対的なファイル名を求める
        $page = kona3getPage(); // 現在のページ名を取得
        $pagePath = kona3getWikiFile($page); // ページのフルパスを取得
        $pageDir = dirname($pagePath); // ページのディレクトリを取得
        $full_svg = $pageDir . "/" . $filename . ".svg"; // フルパスを作成
        $full_mmd = $pageDir . "/" . $filename . ".mmd"; // フルパスを作成
        // ファイルへのリンクを作成
        $url_svg = str_replace(KONA3_DIR_DATA, '', $full_svg);
        $url_svg = "index.php?".urlencode($url_svg)."&data";
        $svg_link .= "<span><a href=\"{$url_svg}\" target=\"_blank\">(↓svg)</a></span>\n";
        kona3plugins_mermaid_write_source($full_mmd, $text); // mmdファイルを作成
        // mermaid_cli($full_mmd, $full_svg); // SVGファイルを作成
        // $mermaid_cli = kona3getConf("mermaid_cli", "mmdc");
        $update = kona3plugins_mermaid_needs_regenerate($full_mmd, $full_svg);
        if ($update && $mermaid_cli) {
            $cmd_a = [escapeshellarg($mermaid_cli), "-i", escapeshellarg($full_mmd), "-o", escapeshellarg($full_svg)];
            $cmd = implode(" ", $cmd_a) . " 2>&1";
            $error = htmlspecialchars(system($cmd, $retcode), ENT_QUOTES);
            if ($error) {
                $error = "<div class='error'>$error</div>";
            }
        }
    }
    $body = <<<EOS
<!-- mermaid -->
<div class="svg">
  <pre class="mermaid">{$text_html}</pre>
  <div class="memo">{$caption} {$svg_link} {$error}</div>
</div>
<!-- /mermaid -->
EOS;
    $result = $head . "\n" . $body . "\n";
    return $result;
}
