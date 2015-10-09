<?php

function kona3plugins_csv_execute($args) {
  $html = "";
  $text = trim(array_shift($args));
  $lines = explode("\n", $text);
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line == "") continue;
    $cells = explode(",", $line);
    $html .= "<tr>";
    foreach ($cells as $cell) {
      $html .= "<td>".kona3text2html($cell)."</td>";
    }
    $html .= "</tr>";
  }
  return "<table>".$html."</table>";
}


