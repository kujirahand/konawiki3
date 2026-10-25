<?php
/** 指定されたURLやデータをQRコードで出力する
 * - [書式] #qrcode(data, size=x)
 * - [引数]
 * -- text ... テキスト
 * -- size=x ... QRコードのサイズ
 */

function kona3plugins_qrcode_execute($args) {
  global $kona3conf;
  $plugkey = "plugins.qrcode.init";
  $text = array_shift($args);
  $sizeParam = array_shift($args);
  $size = 64;
  if (preg_match('#size=(\d+)#', $sizeParam, $m)) {
      $size = $m[1];
  }
  $head = "";
  $domid = "???";
  if (empty($kona3conf[$plugkey])) {
    $kona3conf[$plugkey] = 1;
    $domid = "kona3qrcode-1";
    $head = <<<EOS
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
EOS;
  } else {
    $kona3conf[$plugkey] += 1;
    $domid = "kona3qrcode-".$kona3conf[$plugkey];
    $head = "";
  }
  $textH = htmlspecialchars($text, ENT_QUOTES);
  if (substr($textH, 0, 4) == "http") {
    $textH = "<a href=\"$textH\" target=\"_new\">$textH</a>";
  }
  // <script> 内のJS文字列リテラルへ埋め込むため、JSONとしてエンコードする。
  // 単純な " の置換だけでは </script> や \ で文字列を抜け出せてしまう (#324)。
  $textQ = json_encode((string)$text,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
  if ($textQ === false) $textQ = '""';
  $body = <<<EOS
<div class="kona3qrcode" style="margin: 0.3em; padding: 0.5em;">
    <div id="$domid"></div>
    <div class="memo">$textH</div>
</div>
<script>
(() => {
    const qrdom = document.getElementById("$domid");
    new QRCode(qrdom, {
        text: $textQ,
        width: $size,
        height: $size,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
})();
</script>
EOS;
  return $head . "\n" . $body . "\n";
}


