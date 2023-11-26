<?php
/** HTMLに変換して表示
 * - [書式] {{{#html ... }}}
 */

function kona3plugins_html_execute($args) {
  $html = array_shift($args);
  $t = "<span>".$html."</span>";
  return $t; 
}



