<?php

/** mermaid notation
 * - [Usage] {{{#mermaid(filename) ... }}}
 * filename --- もし、mermaid-cliがインストールされているなら、SVGファイルを出力する
 * 設定ファイルで「mermaid_cli」を指定する必要がある
 */


function kona3plugins_mermaid_execute($args)
{
    global $kona3conf;
    $text = array_shift($args);
    $filename = array_shift($args);
    $head = '';
    //
    $plugkey = "plugins.mermaid.init";
    if (empty($kona3conf[$plugkey])) {
        $kona3conf[$plugkey] = 1;
        $head = <<<EOS
<script type="module">
  import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs';
  mermaid.initialize({ startOnLoad: true });
</script>
EOS;
    }
    $body = <<<EOS
<div class="svg">
  <pre class="mermaid">{$text}</pre>
</div>
EOS;
    // export SVGファイル
    $mermaid_cli = kona3getConf("mermaid_cli", "");
    if ($filename && $mermaid_cli) {
        // 正規化 - [a-zA-Z0-9\-_]のみにする
        $filename = preg_replace("\.svg$", "", $filename); // .svgを削除
        $filename = preg_replace("/[^a-zA-Z0-9\-_.]/", "_", $filename);
        // WIKIページから相対的なファイル名を求める
        $page = kona3getPage(); // 現在のページ名を取得
        $pagePath = kona3getWikiFile($page); // ページのフルパスを取得
        $pageDir = dirname($pagePath); // ページのディレクトリを取得
        $full_svg = $pageDir . "/" . $filename . ".svg"; // フルパスを作成
        $full_mmd = $pageDir . "/" . $filename . ".mmd"; // フルパスを作成
        // mermaild-cliを使ってSVGファイルを出力
        file_put_contents($full_mmd, $text); // mmdファイルを作成
        mermaid_cli($full_mmd, $full_svg); // SVGファイルを作成
    }
    return $head . "\n" . $body . "\n";
}

function mermaid_cli($infile, $outfile)
{
    $mermaid_cli = kona3getConf("mermaid_cli", "");
    $cmd = "$mermaid_cli -i $infile -o $outfile";
    shell_exec($cmd); // コマンドを実行
}
