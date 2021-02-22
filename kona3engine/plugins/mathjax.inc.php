<?php

function kona3plugins_mathjax_execute($args) {
  global $kona3conf;
  $text = array_shift($args);
  //
  $plugkey = "plugins.mathjax.init";
  if (empty($kona3conf[$plugkey])) {
    $kona3conf[$plugken] = 1;
    // use CDN
    $mj = 'https://cdn.mathjax.org/mathjax/latest/MathJax.js?config=TeX-MML-AM_CHTML';
    $kona3conf['js'][] = $mj;
    $kona3conf['header.tags'][] = <<< EOS
<script type="text/x-mathjax-config">
  MathJax.Hub.Config({
    tex2jax: {
      inlineMath: [['\\$\\$', '\\$\\$']],
      displayMath: [ ['\\\\[\\[\\[','\\\\]\\]\\]'] ]
    }
  });
</script>
EOS;
  }
  $text = htmlspecialchars($text, ENT_QUOTES);
  return "<div class='mathjax'>\\[[[\n{$text}\n\\]]]\n</div>";
}


