<?php

function kona3plugins_mathjax_execute($args) {
  global $kona3conf;
  $text = array_shift($args);
  //
  $header = "";
  $plugkey = "plugins.mathjax.init";
  if (empty($kona3conf[$plugkey])) {
    $kona3conf[$plugken] = 1;
    $js = KONA3_DIR_PUB.'/MathJax/MathJax.js';
    if (file_exists($js)) {
      // use local
      $pub = $kona3conf["url.pub"];
      $mj = $pub.'/MathJax/MathJax.js?config=TeX-MML-AM_CHTML';
    } else {
      // use CDN
      $mj = 'https://cdn.mathjax.org/mathjax/latest/MathJax.js?config=TeX-MML-AM_CHTML';
    }
    $kona3conf['js'][] = $mj;
    $kona3conf['header.tags'][] = <<< EOS
<script type="text/x-mathjax-config">
  MathJax.Hub.Config({
    tex2jax: {
      displayMath: [ ['\\\\[\\[\\[','\\\\]\\]\\]'] ]
    }
  });
</script>
EOS;
  }
  return "<div>{$header}<p class='code mathjax'>\\[[[\n{$text}\n\\]]]\n</p></div>";
}


