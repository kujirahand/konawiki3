<?php
// require_once
require_once(__DIR__.'/fw_rooting.lib.php');
require_once(__DIR__.'/fw_database.lib.php');
require_once(__DIR__.'/fw_template_engine.lib.php');
require_once(__DIR__.'/fw_etc.lib.php');

// check parameters
global $DIR_ACTION, $DIR_TEMPLATE, $DIR_TEMPLATE_CACHE;
if (!isset($DIR_ACTION) || !isset($DIR_TEMPLATE) || !isset($DIR_TEMPLATE_CACHE)) {
  echo "Please set \$DIR_ACTION and \$DIR_TEMPLATE and \$DIR_TEMPLATE_CACHE.";
  exit;
}

// first method
rooting_action();
