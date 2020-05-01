<?php
// php7 以降に対応
define('TEMPLATE_VERSION', 'v1_'.filemtime(__FILE__));
define('TEMPLATE_USE_CACHE', TRUE);
define('TEMPLATE_CACHE_TYPE', 'SAMEFILE'); // SAMEFILE | DATETYPE
require_once __DIR__.'/fw_template_engine_plugins.lib.php';

if (!function_exists('preg_replace_callback_array')) {
  function preg_replace_callback_array (array $patterns_and_callbacks, $subject, $limit=-1, &$count=NULL) {
      $count = 0;
      foreach ($patterns_and_callbacks as $pattern => &$callback) {
          $subject = preg_replace_callback($pattern, $callback, $subject, $limit, $partial_count);
          $count += $partial_count;
      }
      return preg_last_error() == PREG_NO_ERROR ? $subject : NULL;
  }
}


function template_render($tpl_filename, $tpl_params) {
  /*
   * [使い方]
   * {{$name}} で変数埋め込み
   * {{$name | filter}} で template_plugins.lib.php の t_filter($name) を実行
   * {{$name | raw || でHTMLをそのまま表示
   * {{$name.k1.k2}}だと$name["k1"]["k2"]と展開される
   * {{if cond}}..{{else}}..{{endif}}
   * {{for $values as $key=>$val}}..{{endfor}}
   * {{# (comment) }}
   */
  global $DIR_TEMPLATE;
  global $DIR_TEMPLATE_CACHE;
  global $TEMPLATE_PARAMS;

  // extract variable
  extract($TEMPLATE_PARAMS);
  extract($tpl_params);
  
  // check template
  $file_template = $DIR_TEMPLATE."/$tpl_filename";
  if (!file_exists($file_template)) {
    throw new Exception("[Template Error] file not found : $tpl_name : $file_template");
  }
  
  // check cache file
  $mtime = filemtime($file_template);
  if (TEMPLATE_CACHE_TYPE == 'DATETYPE') {
    $file_cache = $DIR_TEMPLATE_CACHE.'/'.
        $tpl_filename.'.'.$mtime.'_'.TEMPLATE_VERSION.'.php';
    if (file_exists($file_cache) && TEMPLATE_USE_CACHE) {
      include($file_cache);
      return;
    }
  } else /* if (TEMPLATE_CACHE_TYPE == 'SAMEFILE') */ {
    $file_cache = $DIR_TEMPLATE_CACHE.'/'.$tpl_filename.'.php';
    $mtime_cache = file_exists($file_cache) ? filemtime($file_cache) : 0;
    if ($mtime < $mtime_cache && TEMPLATE_USE_CACHE) {
      include($file_cache);
      return;
    }
  }
  
  // create cache
  $file_plugins = __DIR__ . '/template_engine_plugins.lib.php';
  $body = "<?php /*[template_engine.lib.php] ".TEMPLATE_VERSION.
          "*/ include_once('$file_plugins');";
  $body .= "?>";
  $body .= file_get_contents($file_template);
  $body = preg_replace_callback_array([
    // flow
    // {{ eval code }} {{e:code}}
    '#\{\{\s*(eval|e)[\s\:]+(.+?)}}#is' => function (&$m) {
      $code = $m[2];
      return "<?php $code;?>";
    },
    // {{ include cond }} 
    '#\{\{\s*include\s+[\'\"]?(.+?)[\'\"]?\s*}}#is' => function (&$m) use ($tpl_params){
      $file = $m[1];
      $enc = json_encode($tpl_params);
      return "<?php template_render('$file', []);?>";
    },
    // {{ if cond }} 
    '#\{\{\s*if\s+(.+?)\s*\}\}#is' => function (&$m) {
      $cond = $m[1];
      return "<?php if ($cond): ?>";
    },
    '#\{\{\s*else\s*(.*?)}}#is' => function (&$m) {
      return "<?php else: ?>";
    },
    // {{ for $vars as $key => $val }}
    '#\{\{\s*(for|foreach)\s+\$([a-zA-Z0-9_]+)\s+as\s+\$([a-zA-Z0-9_]+)\s*\=\>\s*\$([a-zA-Z0-9]+)\s*}}#is' => function (&$m) {
      $ary = $m[2];
      $key = $m[3];
      $val = $m[4];
      return "<?php foreach (\${$ary} as \${$key} => \${$val}): ?>";
    },
    // {{ for $vars as $key => $val }}
    '#\{\{\s*(for|foreach)\s+\$([a-zA-Z0-9_]+)\s+as\s+\$([a-zA-Z0-9]+)\s*}}#i' => function (&$m) {
      $ary = $m[2];
      $val = $m[3];
      return "<?php foreach (\${$ary} as \${$val}): ?>";
    },
    '#\{\{\s*(endif|endfor|endforeach|end)\s*(.*?)}}#is' => function (&$m) {
      $end = $m[1];
      if ($end == 'endfor') { $end = 'endforeach'; }
      return "<?php $end; ?>";
    },
    // param filter
    '#\{\{\s*\$([a-zA-Z0-9_.]+)\s*\|\s*([a-zA-Z0-9_]+)\s*}}#is' => function (&$m) {
      $key = template_var_name($m[1]);
      $filter = $m[2];
      return "<?php t_{$filter}(\${$key});?>";
    },
    // param only
    '#\{\{\s*\$([a-zA-Z0-9_.]+)\s*}}#is' => function (&$m) {
      $key = template_var_name($m[1]);
      return "<?php t_echo(\$$key);?>";
    },
    // comment
    '#\{\{\s*\#(.*?)}}#is' => function (&$m) {
      return "";
    },
  ], $body);
  file_put_contents($file_cache, $body);
  include($file_cache);
}

function template_var_name($name) {
  $a = explode('.', $name);
  if (count($a) <= 1) {
    return $name;
  }
  $r = '';
  foreach ($a as $i => $v) {
    if ($i == 0) {
      $r .= $v;
    } else {
      $r .= "['$v']";
    }
  }
  return $r;
}



