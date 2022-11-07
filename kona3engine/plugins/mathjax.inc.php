<?php

function kona3plugins_mathjax_execute($args) {
  global $kona3conf;
  $text = array_shift($args);
  //
  $plugkey = "plugins.mathjax.init";
  if (empty($kona3conf[$plugkey])) {
    $kona3conf[$plugken] = 1;
    // use CDN
    $mj = 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js';
    $kona3conf['js'][] = $mj;
    $kona3conf['header.tags'][] = <<< EOS
<script>
window.MathJax = {
  tex: {
    inlineMath: [['$$$', '$$$'], ['\\($', '$\\)']],
    displayMath: [['$$$', '$$$']]
  },
  svg: {
    fontCache: 'global'
  }
};
</script>
EOS;
  }
  $text = htmlspecialchars($text, ENT_QUOTES);
  return "<div class='mathjax'> $$$ {$text} $$$ </div>";
}


