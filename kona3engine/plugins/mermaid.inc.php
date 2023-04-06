<?php

/** mermaid notation
 * [Usage] {{{#mermaid ... }}}
 */


function kona3plugins_mermaid_execute($args)
{
  global $kona3conf;
  $text = array_shift($args);
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
  return $head . "\n" . $body . "\n";
}
