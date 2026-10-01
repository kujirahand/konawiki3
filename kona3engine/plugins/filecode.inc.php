<?php
/** ソースコードのパスを指定して囲んで表示
 * - [書式] #filecode(path, type, lineno)
 * - [引数]
 * -- path: filepath
 * -- type=(code|plain): code or plain(beta) (optional)
 * -- lineno=nn-mm: extract lines nn to mm (optional)
 * -- nonum: hide line numbers (optional)
 * - [利用例]
 * #filecode(src/ch1/hello.py, type=code, lineno=1-3)
 */

function kona3plugins_filecode_execute($args) {
  global $kona3conf;
  
  // get pid
  $pid = kona3_getPluginInfo("filecode", "pid", 0) + 1;
  kona3_setPluginInfo("filecode", "pid", $pid);
  
  // get parameters
  $name = array_shift($args);
  $type = "code";
  $lineno_from = 0;
  $lineno_to = 0;
  $show_num = true;
  foreach ($args as $arg) {
    if ($arg === 'nonum') {
      $show_num = false;
    } else if (preg_match('/^type=(.*)$/', $arg, $m)) {
      $type = $m[1];
    } else if (preg_match('/^lineno=([0-9\-\:]+?)$/', $arg, $m)) {
      $line = $m[1];
      if (preg_match('/^([0-9]+)[\-\:]([0-9]+)$/', $line, $m)) {
        $lineno_from = intval($m[1]);
        $lineno_to = intval($m[2]);
      } else if (preg_match('/^([0-9]+)$/', $line, $m)) {
        $lineno_from = $lineno_to = intval($m[1]);
      }
    }
  }

  // check parametes
  $name = str_replace('..', '', $name);
  $fname = kona3getWikiFile($name, false);
  if (!file_exists($fname)) {
    $page = kona3getPage();
    $dir = dirname($page);
    if ($dir != '') $dir = $name = $dir.'/'.$name;
    $fname = kona3getWikiFile($name, false);
    if (!file_exists($fname)) {
      return "<div class='error'>Not Exists:".
        kona3text2html($name).
        "</div>";
    }
  }
  $url = kona3getWikiUrl($name);
  $txt = @file_get_contents($fname);
  $omit_head = false;
  $omit_tail = false;
  if ($lineno_from > 0) {
    $lines = explode("\n", trim($txt));
    $line_cnt = count($lines);
    $sublines = array_slice($lines, $lineno_from - 1, $lineno_to - $lineno_from + 1);
    $txt = implode("\n", $sublines);
    if ($lineno_from >= 2) {
      $txt = "…省略…\n".$txt;
      $omit_head = true;
    }
    if ($lineno_to < $line_cnt) {
      $txt = $txt."\n…省略…\n";
      $omit_tail = true;
    }
  }
  $name_ = htmlspecialchars($name, ENT_QUOTES);
  if ($lineno_from > 0) {
    $name_ .= " (lineno=$lineno_from-$lineno_to)";
  }
  $btn = '';
  $tag = ($type == 'beta' || $type == 'plain') ? 'div' : 'pre';
  if (preg_match('#\.php$#', $fname)) {
    // .php file
    $htm = kona3plugins_filecode_php_highlight(trim($txt));
  } else {
    $lang = kona3plugins_filecode_lang($fname);
    $htm = $lang ?
      kona3plugins_filecode_tokenize(trim($txt), $lang) :
      kona3text2html(trim($txt));
    if (preg_match('#\.(nako|nako3)$#', $fname)) {
      // .nako3 file
      $name_u = urlencode($name);
      $link = kona3getPageURL("", "plugin", "",
                "name=nako3&mode=run&nakofile=$name_u&canvas");
      $btn = "<a href='$link'>[実行]</a> ";
      $tag = 'pre';
    }
  }
  $lines = kona3plugins_filecode_lines($htm, max(1, $lineno_from), $omit_head, $omit_tail);
  $cls = $show_num ? 'filecode' : 'filecode nonum';
  $code =
    "<div class='{$cls}'>\n".
    "  <div class='filename'>{$btn}file: {$name_}</div>\n".
    "  <div class='filecode-body'><{$tag} class='code'>{$lines}</{$tag}></div>\n".
    "</div>\n";
  return $code;
}

/** PHPのソースをハイライトして、改行区切りのHTMLを返す */
function kona3plugins_filecode_php_highlight($txt) {
  $htm = highlight_string($txt, true);
  if (strpos($htm, '<br />') !== false) {
    // PHP 8.2以前: 改行は<br />で表現される
    $htm = preg_replace('#[\r\n]#', '', $htm);
    $htm = str_replace('<br />', "\n", $htm);
  }
  // 外側の<pre><code>を取り除く
  $htm = preg_replace('#</?(pre|code)\b[^>]*>#', '', $htm);
  return trim($htm, "\r\n");
}

/** HTMLを1行ずつ行番号付きの要素に分割する
 * (行をまたぐタグは行ごとに閉じて、次の行で開き直す)
 * @param string $htm 改行区切りのHTML
 * @param int $start 先頭行の行番号
 * @param bool $omit_head 先頭が省略行か
 * @param bool $omit_tail 末尾が省略行か
 */
function kona3plugins_filecode_lines($htm, $start, $omit_head, $omit_tail) {
  $lines = explode("\n", str_replace("\r", "", $htm));
  $cnt = count($lines);
  $stack = [];
  $out = '';
  $n = $start;
  foreach ($lines as $i => $line) {
    $open = implode('', $stack);
    preg_match_all('#<(/?)([a-zA-Z0-9]+)\b[^>]*>#', $line, $m, PREG_SET_ORDER);
    foreach ($m as $t) {
      if ($t[1] === '/') {
        // 対応する開始タグを探して取り除く(ネストが崩れていても安全)
        for ($k = count($stack) - 1; $k >= 0; $k--) {
          if (preg_match('#^<'.preg_quote($t[2], '#').'\b#i', $stack[$k])) {
            array_splice($stack, $k, 1);
            break;
          }
        }
      } else if (substr($t[0], -2) !== '/>') {
        $stack[] = $t[0];
      }
    }
    $close = '';
    for ($j = count($stack) - 1; $j >= 0; $j--) {
      preg_match('#^<([a-zA-Z0-9]+)#', $stack[$j], $tm);
      $close .= "</{$tm[1]}>";
    }
    $omit = ($omit_head && $i == 0) || ($omit_tail && $i == $cnt - 1);
    $num = $omit ? '' : $n++;
    $out .= "<span class='line' data-n='{$num}'>{$open}{$line}{$close}</span>";
  }
  return $out;
}

/** ファイル名から、ハイライト対象の言語(py/js)を返す。対象外は空文字 */
function kona3plugins_filecode_lang($fname) {
  $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
  $map = [
    'py' => 'py', 'pyw' => 'py',
    'js' => 'js', 'mjs' => 'js', 'cjs' => 'js', 'jsx' => 'js',
    'ts' => 'js', 'tsx' => 'js',
  ];
  return isset($map[$ext]) ? $map[$ext] : '';
}

/** Python/JavaScriptのソースを簡易的にハイライトしたHTMLを返す
 * (正規表現による簡易字句解析。行をまたぐ文字列やコメントも1つのspanになる)
 * @param string $txt ソースコード
 * @param string $lang 'py' or 'js'
 * @return string HTML (エスケープ済み)
 */
function kona3plugins_filecode_tokenize($txt, $lang) {
  $kw = [
    'py' => 'and as assert async await break class continue def del elif else except finally for from global if import in is lambda nonlocal not or pass raise return try while with yield match case',
    'js' => 'async await break case catch class const continue debugger default delete do else export extends finally for from function if import in instanceof let new of return static super switch throw try typeof var void while with yield type interface enum implements public private protected readonly as',
  ];
  $builtin = [
    'py' => 'True False None self cls print len range int str float list dict set tuple bool type open input super isinstance enumerate zip map filter sorted sum min max abs',
    'js' => 'true false null undefined NaN Infinity this console window document Math JSON Object Array String Number Boolean Promise Map Set Date RegExp Error require module exports',
  ];
  $kw_set = array_flip(explode(' ', $kw[$lang]));
  $bi_set = array_flip(explode(' ', $builtin[$lang]));
  if ($lang == 'py') {
    $comment = '\#[^\n]*';
    $string = '(?:[rRbBuUfF]{0,2})(?:"""(?:\\\\.|[^\\\\])*?"""|\'\'\'(?:\\\\.|[^\\\\])*?\'\'\'|"(?:\\\\.|[^"\\\\\n])*"|\'(?:\\\\.|[^\'\\\\\n])*\')';
    $deco = '(?<=^|\n)[ \t]*@[A-Za-z_][\w.]*';
    $regex = '(?!)';
  } else {
    $comment = '//[^\n]*|/\*.*?\*/';
    $string = '"(?:\\\\.|[^"\\\\\n])*"|\'(?:\\\\.|[^\'\\\\\n])*\'|`(?:\\\\.|[^`\\\\])*`';
    $deco = '(?!)';
    // 正規表現リテラル: 直前が値(単語/閉じ括弧)でなければ、除算ではなくリテラルとみなす
    // (空白を挟んでも判定できるよう、0〜4個の空白を飛ばした直前の文字を見る)
    $v = '[\w$)\]]';
    $regex = '(?:(?<=\breturn\s)|(?<=\btypeof\s)|(?<=\bcase\s)|'.
             "(?<!$v)(?<!$v\\s)(?<!$v\\s{2})(?<!$v\\s{3})(?<!$v\\s{4}))".
             '/(?![/*])(?:\\\\.|\[(?:\\\\.|[^\]\\\\\n])*\]|[^/\\\\\n\[])+/[dgimsuyv]*';
  }
  $re = '~(?<c>'.$comment.')|(?<s>'.$string.')|(?<d>'.$deco.')|(?<r>'.$regex.')'.
        '|(?<n>\b0[xX][0-9a-fA-F_]+\b|\b\d[\d_]*(?:\.\d+)?(?:[eE][+-]?\d+)?\b)'.
        '|(?<w>[A-Za-z_$][\w$]*)(?<p>(?=\())?|(?<o>[^\w"\'`#\/@$\s]+|\s+|.)~su';
  $esc = function ($t) { return htmlspecialchars($t, ENT_QUOTES); };
  $out = preg_replace_callback($re, function ($m) use ($esc, $kw_set, $bi_set) {
    if (isset($m['r']) && $m['r'] !== '') {
      $body = ltrim($m['r']);
      $ws = substr($m['r'], 0, strlen($m['r']) - strlen($body));
      return $ws."<span class='tok-s'>".$esc($body)."</span>";
    }
    foreach (['c' => 'c', 's' => 's', 'd' => 'd', 'n' => 'n'] as $k => $cls) {
      if (isset($m[$k]) && $m[$k] !== '') {
        return "<span class='tok-{$cls}'>".$esc($m[$k])."</span>";
      }
    }
    if (isset($m['w']) && $m['w'] !== '') {
      $w = $m['w'];
      if (isset($kw_set[$w])) $cls = 'k';
      else if (isset($bi_set[$w])) $cls = 'b';
      else if (isset($m['p'])) $cls = 'f'; // 直後が"("なら関数
      else return $esc($w);
      return "<span class='tok-{$cls}'>".$esc($w)."</span>";
    }
    return $esc($m[0]);
  }, $txt);
  // 変換に失敗(不正なUTF-8など)したらプレーン表示
  return $out === null ? $esc($txt) : $out;
}
