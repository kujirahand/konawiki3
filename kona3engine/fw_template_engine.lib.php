<?php
// php7 以降に対応
define('TEMPLATE_VERSION', 'v2_'.filemtime(__FILE__));
define('TEMPLATE_USE_CACHE', FALSE);
define('TEMPLATE_CACHE_TYPE', 'SAMEFILE'); // SAMEFILE | DATETYPE
require_once __DIR__.'/fw_template_engine_plugins.lib.php';

// テンプレートの表示メソッド
function template_render($tpl_filename, $tpl_params) {
  /*
   * [使い方]
   * {{$name}} で変数埋め込み
   * {{e code}} でeval
   * {{eval code}} でeval
   * {{$name | filter}} で template_plugins.lib.php の t_filter($name) を実行
   * {{$name | safe }} でHTMLをそのまま表示
   * {{$name.k1.k2}}だと$name["k1"]["k2"]と展開される
   * {{if cond}}..{{else}}..{{endif}}
   * {{for $values as $key=>$val}}..{{endfor}}
   * {{"..." | filter}} で文字列をFilterにかける
   * {{'...' | filter}} で文字列をFilterにかける
   * {{# (comment) }}
   * {{ include filename }} で外部ファイルの取り込み
   */
  global $DIR_TEMPLATE;
  global $DIR_TEMPLATE_CACHE;
  global $FW_TEMPLATE_PARAMS;
  // extract variable
  extract($FW_TEMPLATE_PARAMS);
  extract($tpl_params);
  // check template
  $file_template = $DIR_TEMPLATE."/$tpl_filename";
  if (!file_exists($file_template)) {
    $msg = "FileNotFound : $tpl_filename";
    template_error($msg);
    throw new Exception($msg);
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
  $fw_contents = "<?php /*[fw_template_engine.lib.php] ".TEMPLATE_VERSION.
          "*/";
  $fw_contents .= "?>";
  $fw_contents .= file_get_contents($file_template);
  $fw_contents = preg_replace_callback_array([
    // flow
    // {{ eval code }} {{e:code}}
    '#\{\{\s*(eval|e)[\s\:]+(.+?)}}#is' => function ($m) {
      $code = $m[2];
      return "<?php $code;?>";
    },
    // {{ include filename }} 
    '#\{\{\s*include\s+[\'\"]?(.+?)[\'\"]?\s*}}#is' => function ($m) use ($tpl_params){
      $file = $m[1];
      // $enc = json_encode($tpl_params);
      return "<?php template_render('$file', []);?>";
    },
    // {{ if $var.name cond }} 
    '#\{\{\s*if\s+\$([a-zA-Z0-9_\.]+)(.*?)\}\}#is' => function ($m) {
      $var = template_var_name($m[1]);
      $cond = check_eq_flag($m[2]);
      return "<?php if (\${$var} {$cond}):/*if_var_cond*/ ?>";
    },
    // {{ if cond }} 
    '#\{\{\s*if\s+(.+?)\s*\}\}#is' => function ($m) {
      $cond = check_eq_flag($m[1]);
      return "<?php if ($cond): ?>";
    },
    '#\{\{\s*else\s*(.*?)}}#is' => function ($m) {
      return "<?php else: ?>";
    },
    // {{ for $vars as $key => $val }}
    '#\{\{\s*(for|foreach)\s+\$([a-zA-Z0-9_\.]+)\s+as\s+\$([a-zA-Z0-9_]+)\s*\=\>\s*\$([a-zA-Z0-9]+)\s*}}#is' => function ($m) {
      $ary = template_var_name($m[2]);
      $key = $m[3];
      $val = $m[4];
      return "<?php foreach (\${$ary} as \${$key} => \${$val}): ?>";
    },
    // {{ for $vars as $key => $val }}
    '#\{\{\s*(for|foreach)\s+\$([a-zA-Z0-9_\.]+)\s+as\s+\$([a-zA-Z0-9]+)\s*}}#i' => function ($m) {
      $ary = template_var_name($m[2]);
      $val = $m[3];
      return "<?php foreach (\${$ary} as \${$val}): ?>";
    },
    '#\{\{\s*(endif|endfor|endforeach|end)\s*(.*?)}}#is' => function ($m) {
      $end = $m[1];
      if ($end == 'endfor') { $end = 'endforeach'; }
      return "<?php $end; ?>";
    },
    // varname with filter
    '#\{\{\s*\$([a-zA-Z0-9_.]+)\s*\|\s*([a-zA-Z0-9_]+)\s*}}#is' => function ($m) {
      $key = template_var_name($m[1]);
      $filter = $m[2];
      return "<?php echo t_{$filter}(\$$key);?>";
    },
    // varname only
    '#\{\{\s*\$([a-zA-Z0-9_.]+)\s*}}#is' => function ($m) {
      $key = template_var_name($m[1]);
      return "<?php echo t_echo(\$$key);?>";
    },
    // string with filter {{ "..." | filter }}
    '#\{\{\s*(\".*?\"|\'.*?\')\s*\|\s*([a-zA-Z0-9_]+)\s*}}#is' => function (&$m) {
      $str = $m[1];
      $filter = $m[2];
      return "<?php echo t_{$filter}($str);?>";
    },
    // comment
    '#\{\{\s*\#(.*?)}}#is' => function ($m) {
      return "";
    },
  ], $fw_contents);
  file_put_contents($file_cache, $fw_contents);
  include($file_cache);
}

function check_eq_flag($cond) {
  $cond = " ".$cond;
  $cond = preg_replace('#\\s+eq\\s+#is', '==', $cond);
  $cond = preg_replace('#\\s+ne\\s+#is', '!=', $cond);
  $cond = preg_replace('#\\s+gt\\s+#is', '>', $cond);
  $cond = preg_replace('#\\s+(gteq|eqgt)\\s+#is', '>=', $cond);
  $cond = preg_replace('#\\s+lt\\s+#is', '<', $cond);
  $cond = preg_replace('#\\s+(lteq|eqlt)\\s+#is', '<=', $cond);
  return $cond;
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

function template_error($msg, $title = '') {
  echo <<<__EOS__
<div style="background-color:#fee; padding:1em;">
  <h3 style="color:red">Template Error $title</h3>
  <p>$msg</p>
</div>
__EOS__;
}

// PHPの互換性のため
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

