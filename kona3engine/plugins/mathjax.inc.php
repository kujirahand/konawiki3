<?php

/** mathjax
 * - [Usage] {{{#mathjax X_{i+1} = X_i^2 }}}
 * - [Usage] $$$ X_{i+1} = (X_i + X^2) mod A $$$
 */

function kona3plugins_mathjax_execute($args)
{
  $text = array_shift($args);
  kona3plugins_mathjax_include();
  $text = htmlspecialchars($text, ENT_QUOTES);
  return "<div class='mathjax'>$$$$ {$text} $$$$</div>";
}

function kona3plugins_mathjax_include() {
  global $kona3conf;
  $plugkey = "plugins.mathjax.init";
  if (empty($kona3conf[$plugkey])) {
    $kona3conf[$plugkey] = 1;
    // use CDN
    $mj = 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js';
    $kona3conf['js'][] = $mj;
    $kona3conf['header.tags'][] = <<< EOS
<script>
window.MathJax = {
  tex: {
    inlineMath: [['$$$', '$$$']],
    displayMath: [['$$$$', '$$$$']]
  },
  svg: {
    fontCache: 'global'
  }
};
</script>
EOS;
  }
}
