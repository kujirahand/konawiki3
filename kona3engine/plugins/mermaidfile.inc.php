<?php

/** mermaid notation from file
 * [Usage] #mermaidfile(file)
 */

require_once(__DIR__ . '/mermaid.inc.php');

function kona3plugins_mermaidfile_execute($args)
{
  global $kona3conf;
  // get parameters
  $name = array_shift($args);
  $name = str_replace('..', '', $name);
  $fname = kona3getWikiFile($name, false);
  if (!file_exists($fname)) {
    return "<div class='error'>Not Exists:" . kona3text2html($name) . "</div>";
  }
  $txt = @file_get_contents($fname);
  return kona3plugins_mermaid_execute([$txt]);
}
