<?php

/** 画像を表示するプラグイン #ref と同じ
 */ 
include_once(dirname(__FILE__).'/ref.inc.php');

function kona3plugins__E7_94_BB_E5_83_8F_execute($args) {
  return kona3plugins_ref_execute($args);
}
