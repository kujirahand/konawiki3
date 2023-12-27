<?php
/** 指定されたURLやデータをQRコードで出力する
 * - [書式] #qrcode(data, size=x)
 * - [引数]
 * -- lang ... 言語名(省略可)
 * -- text ... テキスト
 */

function kona3plugins_qrcode_execute($args) {
  global $kona3conf;
  $plugkey = "plugins.qrcode.init";
  $text = array_shift($args);
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
  $text = str_replace("\"", "\\\"", $text);
  $body = <<<EOS
<div id="$domid" class="kona3qrcode"></div>
<script>
(() => {
    const qrdom = document.getElementById("$domid");
    new QRCode(qrdom, {
        text: "$text",
        width: 128,
        height: 128,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
})();
</script>
EOS;
  return $head . "\n" . $body . "\n";
}


