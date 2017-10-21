<?php
/**
 * konawki3 parser (UTF-8)
 * 日本語のマークアップも認識します
 */
include_once 'kona3lib.inc.php';

/**
 * convert text to html
 */
global $konawiki_parser_depth;
if (empty($konawiki_parser_depth)) {
    $konawiki_parser_depth = 0;
}

function konawiki_parser_convert($text, $flag_isContents = TRUE)
{
    global $konawiki_parser_depth;
    // parse & render
    $konawiki_parser_depth++;
    $tokens = konawiki_parser_parse($text);
    $html   = konawiki_parser_render($tokens, $flag_isContents);
    $konawiki_parser_depth--;
    return $html;
}
/** get raw text */
function konawiki_getRawText()
{
    return konawiki_public('raw_text');
}
/** get raw tokens */
function konawiki_getRawTokens()
{
    return konawiki_public('raw_tokens');
}

/**
 * 構文を解析して配列に入れる
 */
function konawiki_parser_parse($text)
{
    // convert CRLF to LF
    $text = preg_replace('#(\r\n|\r)#',"\n", $text);
    konawiki_addPublic('EOL', "\n");
    konawiki_addPublic('raw_text', $text);
    $eol = konawiki_public("EOL");
    // get mark config
    $ul_mark1 = '・';
    $ul_mark2 = '≫';
    $h1_mark1 = '■';
    $h1_mark2 = '□';
    $h2_mark1 = '●';
    $h2_mark2 = '○';
    $h3_mark1 = '▲';
    $h3_mark2 = '△';
    $h4_mark1 = '▼';
    $h4_mark2 = '▽';
    //
    $level = 0;

    // main loop
    $tokens = array();
    while ( $text != "") {
        $c = mb_substr($text, 0, 1);
        // TITLE
        if ($c == "*") {
            $level = konawiki_parser_count_level($text, $c);
            konawiki_parser_skipSpace($text);
            $tokens[] = array("cmd"=>"*", "text"=>konawiki_parser_token($text, $eol), "level"=>$level);
            konawiki_parser_skipEOL($text);
        }
        else if ($c == $h1_mark1 || $c == $h1_mark2) { // title1
            konawiki_parser_getchar($text); // skip flag
            konawiki_parser_skipSpace($text);
            $str = konawiki_parser_token($text, $eol);
            $tokens[] = array("cmd"=>"*", "text"=>$str, "level"=>1);
            konawiki_parser_skipEOL($text);
        }
        else if ($c == $h2_mark1 || $c == $h2_mark2) { // title2
            konawiki_parser_getchar($text); // skip flag
            konawiki_parser_skipSpace($text);
            $str = konawiki_parser_token($text, $eol);
            $tokens[] = array("cmd"=>"*", "text"=>$str, "level"=>2);
            konawiki_parser_skipEOL($text);
        }
        else if ($c == $h3_mark1 || $c == $h3_mark2) { // title4
            konawiki_parser_getchar($text); // skip flag
            konawiki_parser_skipSpace($text);
            $str = konawiki_parser_token($text, $eol);
            $tokens[] = array("cmd"=>"*", "text"=>$str, "level"=>3);
            konawiki_parser_skipEOL($text);
        }
        else if ($c == $h4_mark1 || $c == $h4_mark2) { // title4
            konawiki_parser_getchar($text); // skip flag
            konawiki_parser_skipSpace($text);
            $str = konawiki_parser_token($text, $eol);
            $tokens[] = array("cmd"=>"*", "text"=>$str, "level"=>4);
            konawiki_parser_skipEOL($text);
        }
        // LIST <ul>
        else if ($c == '-' || $c == $ul_mark1 || $c == $ul_mark2) {
            if (preg_match('#^(-{2,})\n#', $text, $m)) {
                $text = substr($text, strlen($m[0]));
                konawiki_parser_skipEOL($text);
                $tokens[] = array("cmd"=>"hr", "text"=>"", "level"=>$level);
            } else {
                $level = konawiki_parser_count_level2($text, array("-","・","　"));
                konawiki_parser_skipSpace($text);
                $tokens[] = array("cmd"=>"-", "text"=>konawiki_parser_token($text, $eol), "level"=>$level);
            }
        }
        // LIST <ol>
        else if ($c == "+" || $c == "＋") {
            $level = konawiki_parser_count_level($text, $c);
            konawiki_parser_skipSpace($text);
            $tokens[] = array("cmd"=>"+", "text"=>konawiki_parser_token($text, $eol), "level"=>$level);
        }
        // TABLE
        else if ($c == "|") {
            konawiki_parser_getchar($text);
            $line = konawiki_parser_token($text, $eol);
            $tokens[] = array("cmd"=>"|", "text"=>$line);
        }
        // SOURCE LINE
        else if ($c == " " || $c == "\t") { // src (source) line
            konawiki_parser_getchar($text);
            $tokens[] = array("cmd"=>"src", "text"=>konawiki_parser_token($text, $eol));
        }
        // resmark or conflict mark
        else if ($c == ">" || $c == "＞") {
            if (substr($text, 0, 3) == ">>>") {
                $text = substr($text, 3);
                $flag = substr($text, 0, 3);
                $text = substr($text, 3);
                $tokens[] = array("cmd"=>"conflict", "text"=>konawiki_parser_token($text, $eol), "flag"=>$flag);
            } else {
                konawiki_parser_skipSpace($text);
                konawiki_parser_getchar($text); // skip ">"
                $line = konawiki_parser_token($text, $eol);
                $tokens[] = array("cmd"=>"resmark", "text"=>$line, "flag"=>">");
            }
        }
        // skip CR LF
        else if ($c == "\r" || $c == "\n") {
            konawiki_parser_skipEOL($text);
        }
        // PLUG-INS
        else if ($c == "#" || $c == "♪") { // plugins
            konawiki_parser_getStr($text, strlen($c)); // skip '#'
            $tokens[] = konawiki_parser_plugins($text, $c);
        }
        // SOURCE BLOCK
        else if ($c == "{" && substr($text, 0, 3) == "{{{") {
            $tokens[] = konawiki_parser_sourceBlock($text);
        }
        else { // plain block
            $plain = "";
            while ($text != "") {
                $line = konawiki_parser_token($text, $eol);
                $eos  = substr($line, strlen($line) - 1, 1);
                $plain .= $line;
                if ($eos == "~") { $plain .= $eol; }
                // eol ?
                if (substr($text, 0, strlen($eol)) === $eol) break;
                // command ?
                $c = substr($text, 0, 1);
                if ($c == '') continue;
                if (strpos("*-+# \t\{",$c) === FALSE) {
                    continue;
                } else {
                    break;
                }
            }
            $tokens[] = array("cmd"=>"plain","text"=>$plain);
            konawiki_parser_skipEOL($text);
        }
    }
    return $tokens;
}

/**
 * 解析済の配列データを HTML に変換する
 */
function konawiki_parser_render($tokens, $flag_isContents = TRUE)
{
    konawiki_addPublic('raw_tokens', $tokens);
    $eol = konawiki_public("EOL");
    $html = "";
    if ($flag_isContents) {
        $html = '<div class="contents">'."\n";
    }

    $index = 0;
    while($index < count($tokens)) {
        $value = $tokens[$index++];
        $cmd  = $value["cmd"];
        $text = $value["text"];
        if ($cmd == "*") { // title header
            $html .= konawiki_parser_render_hx($value);
        }
        else if ($cmd == "-" || $cmd == "+") {
            $html .= konawiki_parser_render_li($tokens, $index, $cmd);
        }
        else if ($cmd == "|") {
            $html .= "<table class='grid'>".$eol;
            $index--; // back to this line
            while ($index < count($tokens)) {
                $value = $tokens[$index];
                $cmd  = $value["cmd"];
                $text = rtrim($value["text"]);
                if ($cmd != "|") break;
                if (substr($text, strlen($text) - 1, 1) == "|") {
                    $text = substr($text, 0, strlen($text) - 1);
                }
                $html .= "<tr>";
                $cells = explode("|", $text);
                foreach ($cells as $i => $cell) {
                    $html .= "<td>".konawiki_parser_tohtml($cell). "</td>";
                }
                $html .= "</tr>".$eol;
                $index++;
            }
            $html .= "</table>".$eol;
        }
        else if ($cmd == "src") {
            $html .= konawiki_param("source_tag_begin") . konawiki_parser_tosource($text). $eol;
            while ($index < count($tokens)) {
                $value = $tokens[$index];
                if ($value["cmd"] == "src") {
                    $html .= konawiki_parser_tosource($value["text"]) . $eol;
                    $index++;
                    continue;
                } else {
                  break;
                }
            }
            $html .= konawiki_param("source_tag_end").$eol;
        }
        else if ($cmd == "block") {
            $html .= konawiki_parser_tosource_block($text);
        }
        else if ($cmd == "hr") {
            $html .= "<hr>".$eol;
        }
        else if ($cmd == "plugin") {
            $html .= konawiki_parser_render_plugin($value);
        }
        else if ($cmd == "conflict") {
            $text = htmlspecialchars($text);
            if (trim($text) == "") { $text = "&nbsp;"; }
            if ($value["flag"] == "[+]") {
                $html .= "<div class='conflictadd'>+ $text</div>".$eol;
            }
            else { //if ($value["flag"] == "[-]") {
                $html .= "<div class='conflictsub'>- $text</div>".$eol;
            }

        }
        else if ($cmd == "resmark") {
            $s = konawiki_parser_tohtml($text)."<br/>\n";
            while ($index < count($tokens)) {
                $value = $tokens[$index];
                if ($value["cmd"] == "resmark") {
                    $s .= konawiki_parser_tohtml($value["text"]) . "<br/>" . $eol;
                    $index++;
                    continue;
                } else {
                  break;
                }
            }
            $html .= "<div class='resmark'>".$s."</div>{$eol}";
        }
        else {
            $html .= "<p>".konawiki_parser_tohtml($text)."</p>{$eol}";
        }
    }
    if ($flag_isContents) {
        $html .= "</div>\n";
    }
    return $html;
}

function konawiki_parser_render_hx(&$value)
{
    global $konawiki_headers, $eol;
    if (empty($konawiki_headers)) $konawiki_headers = array();

    $level_from = 1;
    $level = $value["level"];
    $i = $level + ($level_from - 1);
    $text  = $value["text"];
    // calc title hash
    $konawiki_headers[$level] = $text;
    $all_text = "/";
    for ($j = 1; $j <= $level; $j++) {
        $all_text .= $konawiki_headers[$level]."/";
    }
    $hash   = sprintf("%x",crc32($all_text));
    $uri = htmlspecialchars(kona3getPageURL())."#h{$hash}";
    $anchor = "<a id='h{$hash}' name='h{$hash}' href='$uri' class='anchor_super'>&nbsp;*</a>";
    $noanchor = konawiki_param("noanchor", FALSE) || konawiki_public('noanchor', FALSE);
    if ($noanchor) $anchor = "";
    return "<h$i>".konawiki_parser_tohtml($text)."{$anchor}</h$i>{$eol}";
}

function konawiki_parser_render_li(&$tokens, &$index, &$cmd)
{
    $html = "";
    if ($cmd == "-") {
        $li_begin = "<ul>";
        $li_end   = "</ul>";
    }
    else {
        $li_begin = "<ol>";
        $li_end   = "</ol>";
    }
    $level = 0;
    $index--; // back to this command
    $num = count($tokens);
    while ($index < $num) {
        $value = $tokens[$index];
        if ($value["cmd"] != $cmd) break;
        $_html = "";
        $sa = $value["level"] - $level;
        if ($sa > 0) {
            for ($i = 0; $i < $sa; $i++) {
                $html .= "\n{$li_begin}\n<li>";
            }
        } else if ($sa < 0){
            $sa = $sa * -1;
            for ($i = 0; $i < $sa; $i++) {
                $html .= "</li>\n{$li_end}\n";
            }
            if ($value["level"] != 0) {
                $_html = "<li>";
                $html .= "</li>\n";
            }
        } else {
            $_html = "<li>";
            $html .= "</li>\n";
        }
        $text = konawiki_parser_tohtml($value["text"]);
        $html .= $_html . $text;
        $level = $value["level"];
        $index++;
    }
    for ($i = 0; $i < $level; $i++) {
        $html .= "</li>\n{$li_end}";
    }
    return $html;
}


function konawiki_parser_skipEOL(&$text)
{
    for (;;) {
        $c = substr($text, 0, 1);
        if ($c == "\r" || $c == "\n") {
            $text = substr($text, 1);
        } else {
            break;
        }
    }
}

function konawiki_parser_skipSpace(&$text)
{
    for (;;) {
        $c = substr($text, 0, 1);
        if ($c == " " || $c == "\t") {
            $text = substr($text, 1);
        } else {
            break;
        }
    }
}

function konawiki_parser_getchar(&$text)
{
    $c = mb_substr($text, 0, 1);
    $text = mb_substr($text, 1);
    return $c;
}

function konawiki_parser_getStr(&$text, $len)
{
    $result = substr($text, 0, $len);
    $text = substr($text, $len);
    return $result;
}

function konawiki_parser_ungetchar(&$text, $ch)
{
    $text = $ch . $text;
}

function konawiki_parser_count_level(&$text, $ch)
{
    $level = 0;
    for(;;){
        $c = konawiki_parser_getchar($text);
        if ($c == $ch) {
            $level++;
            continue;
        }
        konawiki_parser_ungetchar($text, $c);
        break;
    }
    return $level;
}

function konawiki_parser_count_level2(&$text, $ch_array)
{
    $level = 0;
    for(;;){
        $c = konawiki_parser_getchar($text);
        $r = array_search($c, $ch_array);
        if ($r !== FALSE) {
            $level++;
            continue;
        }
        konawiki_parser_ungetchar($text, $c);
        break;
    }
    return $level;
}

function konawiki_parser_token(&$text, $sub)
{
    $i = strpos($text, $sub);
    if ($i === FALSE) {
        $res  = $text;
        $text = "";
    } else {
        $res  = substr($text, 0, $i);
        $text = substr($text, ($i + strlen($sub)));
    }
    return $res;
}

if (!function_exists("htmlspecialchars_decode")) {
    function htmlspecialchars_decode($s) {
        $s = str_replace('&gt;', '>', $s);
        $s = str_replace('&lt;', '<', $s);
        $s = str_replace('&quot;', '"', $s);
        $s = str_replace('&#039;', "'", $s);
        $s = str_replace('&amp;', '&', $s);
        return $s;
    }
}

/**
 * inline
 */
function __konawiki_parser_tohtml(&$text, $level)
{
    // make link
    $result = "";
    while ($text <> "") {
        // wikiname
        $c1 = mb_substr($text, 0, 1);
        $c2 = mb_substr($text, 0, 2);
        // Wiki Link
        if ($c2 == "[[") {
            // description mode ?
            if (substr($text, 0, 3) === "[[[") { // with desctiption
                $text = substr($text, 3);
                $page = konawiki_parser_token($text, "]]]");
                $result .= konawiki_parser_showPageDescription($page);
            }
            else { // simple name
                $text = substr($text, 2);
                $s = konawiki_parser_token($text, "]]");
                $result .= konawiki_parser_makeWikiLink($s);
            }
            continue;
        }
        // end of inline plugin
        if ($c2 == ");" && $level > 0) {
            $text = substr($text, 2);
            return $result;
        }
        // inline plugin
        if (($c1 == '&') &&
            preg_match('#^\&([\d\w_]+?)\(#',$text, $m)) {
            $pname  = trim($m[1]);
            $plugin = konawiki_parser_getPlugin($pname);
            $text   = substr($text, strlen($m[0]));
            if ($plugin['disallow'] || !file_exists($plugin["file"])) {
                $result .= htmlspecialchars("&".$pname."(");
            } else {
                $pparam = __konawiki_parser_tohtml($text, $level + 1);
                $param_ary = explode(",", $pparam);
                include_once($plugin["file"]);
                $p = array("cmd"=>"plugin", "text"=>$pname, "params"=>$param_ary);
                $s = konawiki_parser_render_plugin($p);
                $result .= $s;
            }
            continue;
        }
        // strong1
        if ($c2 == "''") {
            $text = substr($text, 2);
            $s = konawiki_parser_token($text, "''");
            $str = konawiki_parser_tohtml($s);
            $result .= "<strong class='strong1'>$str</strong>";
            continue;
        }
        // strong2
        if ($c2 == "``") {
            $text = substr($text, 2);
            $s = konawiki_parser_token($text, "``");
            $str = konawiki_parser_tohtml($s);
            $result .= "<strong class='strong2'>$str</strong>";
            continue;
        }
        if ($c2 == '%%') {
            $text = substr($text, 2);
            $s = konawiki_parser_token($text, "%%");
            $str = konawiki_parser_tohtml($s);
            $result .= "<span class='red'>$str</span>";
            continue;
        }
        // url
        if (preg_match('@^(http|https|ftp)\://[\w\d\.\#\$\%\&\(\)\-\=\_\~\^\|\,\.\/\?\+\!\[\]\@]+@', $text, $m) > 0) {
            $result .= konawiki_parser_makeUriLink($m[0]);
            $text = substr($text, strlen($m[0]));
            continue;
        }
        // mailto
        if (preg_match('/^(mailto)\:[\w\d\.\#\$\%\&\-\=\_\~\^\.\/\?\+\@]+/', $text, $m) > 0) {
            $result .= konawiki_parser_makeUriLink($m[0]);
            $text = substr($text, strlen($m[0]));
            continue;
        }
        // ~
        if ($c2 == "~\n" || $c2 == "~\r") {
            $result .= "<br/>";
            $text = substr($text, 1);
            continue;
        }
        // escape ?
        $c = $c1;
        switch ($c) {
            case '>': $c = '&gt;'; break;
            case '<': $c = '&lt;'; break;
            case '&': $c = '&amp;'; break;
            case '"': $c = '&quot;'; break;
        }
        $result .= $c;
        $text = mb_substr($text, 1);
    }
    return $result;
}


function konawiki_parser_tohtml($text)
{
    $r = "";
    while ($text != "") {
        $r .= __konawiki_parser_tohtml($text, 0);
    }
    return $r;
}

function konawiki_parser_tosource($src)
{
    $src = htmlspecialchars($src, ENT_QUOTES);
    return $src;
}

function konawiki_parser_tosource_block($src)
{
    global $eol;
    // plugin ?
    if (substr($src,0,1) == "#") {
        $src     = substr($src, 1);
        $line    = konawiki_parser_token($src, "\n");
        $pname   = trim(konawiki_parser_token($line, "("));
        $arg_str = konawiki_parser_token($line, ")");
        if ($arg_str != "") {
            $args = explode(",", $arg_str);
        } else {
            $args = array();
        }
        array_push($args, $src);
        // Call plugin function
        $pinfo = konawiki_parser_getPlugin($pname);
        $path = $pinfo['file'];
        $func = $pinfo['func'];
        if (!$pinfo['disallow'] && file_exists($path)) {
            include_once($path);
            if (is_callable($func)) {
                $res = @call_user_func($func, $args);
                return $res;
            }
        }
    }
    // no plugin
    $src = htmlspecialchars($src, ENT_QUOTES);
    $begin = "<div><pre class='code'>";
    $end   = "</pre></div>" . $eol;
    return $begin.$src.$end;
}

function konawiki_parser_makeUriLink($url)
{
    $disp = mb_strimwidth($url, 0, 60, "..");
    // $disp = htmlspecialchars($url);
    $link = htmlspecialchars($url);
    return "<a href='$link'>$disp</a>";
}

function konawiki_parser_showPageDescription($page)
{
    return konawiki_parser_makeWikiLink($page);
}

function konawiki_parser_makeWikiLink($name)
{
    // check pattern
    // -[[wikiname]]
    // -[[name:url]]
    // -[[caption:wikiname]]
    // -[[caption:wikiname]]
    $caption  = ""; // display name
    $link     = ""; // link
    $wikiname = ""; // wikiname
    $wikilink = TRUE;

    // [[xxx:xxxx]]
    if (strpos($name, ":") === FALSE) { // simple ?
        // [[wikiname]]
        $caption = $wikiname = $name;
        $link = kona3getPageURL($name);
    }
    else {
        // [[xxx:xxx]]
        preg_match('|^(.*?)\:(.*)$|', $name, $e);
        $caption = $e[1];
        $link    = $e[2];
        // protocol ?
        if ($caption == 'http' || $caption == 'https' || $caption == 'ftp') {
            $link = $caption = $name;
        }
        // check all url
        if (strpos($link, '://') !== FALSE) {
            // url
            $caption = konawiki_parser_disp_url($caption);
            $link = $link;
            $wikilink = FALSE;
        }
        else {
            // wiki link
            // [[caption:WikiPage]]
            $wikiname = $link;
            $link = kona3getPageURL($link);
            $wikilink = TRUE;
        }
    }

    // wikipage exists ?
    if ($wikilink === TRUE) {
        // TODO: check extra tag
    }
    return "<a href='$link'>$caption</a>";
}

function konawiki_parser_disp_url($url)
{
    $omit = konawiki_param("omit_longurl", TRUE);
    if ($omit) {
        $len = konawiki_param("omit_longurl_len", 80);
        $url = mb_strimwidth($url, 0, $len, "..");
        return $url;
    } else {
        return $url;
    }
}

function konawiki_parser_plugins(&$text, $flag)
{
    mb_regex_encoding("UTF-8");
    $word = "";
    if (mb_ereg('^[\w\d\_\-]+', $text, $m) == 0) {
        return array("cmd"=>"", "text"=>"#");
    }
    //
    $word = $m[0];
    $res  = array("cmd"=>"plugin", "text"=>$word, "params"=>array());
    $text = substr($text, strlen($word)); // skip $word
    konawiki_parser_skipSpace($text);
    $c = mb_substr($text, 0, 1);
    if ($c == "(") { // has params
        konawiki_parser_getStr($text, 1); // skip '('
        if ($flag === "&") {
            if (strpos($text, ");") >= 0) {
                $param_str = konawiki_parser_token($text, ');');
                $res["params"] = explode("\,", $param_str);
            }
        }
        else { // $flag == "#"
            if (strpos($text, ")") >= 0) {
                $param_str = konawiki_parser_token($text, ')');
                $res["params"] = explode(",", $param_str);
                if (substr($text,0,1) == ";") { // (xx); の形式なら";"を削る
                    $text = substr($text, 1);
                }
            }
        }
    }
    else if ($c == "{" && substr($text,0,3) === "{{{") {
      // todo
    }
    else if ($c == "～") {
        $eol = konawiki_public("EOL");
        $line = konawiki_parser_token($text, $eol);
        $line = mb_substr($line, 1);
        $res["params"] = explode("～", $line);
    }

    // check plugins
    konawiki_parser_pluginInit($word, $res);
    return $res;
}

function konawiki_parser_plugin_error($pname, &$res, $reason = 'No Plugin')
{
    $res["cmd"] = "";
    $eword = urlencode($pname);
    if ($pname != $eword) {
      $pname .= "($eword)";
    }
    $pname_ = htmlentities($pname);
    $res["text"] = "[$reason:{$pname_}]";
    return $res;
}

function konawiki_parser_pluginInit($pname, &$res)
{
  // check plugin
  $plugin = konawiki_parser_getPlugin($pname);
  $f = $plugin["file"];
  if ($plugin['disallow'] || !file_exists($f)) {
    return konawiki_parser_plugin_error($pname, $res);
  }
  // include plugin file
  include_once($f);
  // callable?
  if (is_callable($plugin["init"])) {
    @call_user_func($plugin["init"]);
  }
  return $res;
}

function konawiki_parser_render_plugin($value)
{
  $pname  = $value['text'];
  $params = $value['params'];
  
  $info = konawiki_parser_getPlugin($pname);
  $func = $info['func'];
  $res = "[Plugin Error:".kona3text2html($pname)."($func)]";

  // check    
  if ($info['disallow']) {
    $res = "[Plugin Error:".kona3text2html($pname)."]";
    return $res;
  }

  // execute 
  if (is_callable($func)) {
    $res = @call_user_func($func, $params);
  }
  return $res;
}

function konawiki_parser_getPlugin($pname)
{
  global $kona3conf;
  $uname = urlencode($pname);
  
  // path
  $path  = $kona3conf["path.engine"]."/plugins/$uname.inc.php";
  $func  = str_replace("%", "_", $uname);

  // check disabled
  $disallow = FALSE;
  $pd = $kona3conf['plugin.disallow'];
  if (isset($pd[$pname]) && $pd[$pname]) {
    $path = '';
    $disallow = TRUE;
  }
  return array(
    "file" => $path,
    "init" => "kona3plugins_{$func}_init",
    "func" => "kona3plugins_{$func}_execute",
    "disallow" => $disallow,
  );
}

/**
 * ソースコードのブロックを抽出する
 * @param $text
 * @return array
 */
function konawiki_parser_sourceBlock(&$text)
{
    $eol = konawiki_public("EOL");
    // get source mark begin
    preg_match('#^(\{+)#', $text, $m);
    $mark = $m[1];
    $mark_len = strlen($mark);
    konawiki_parser_getStr($text, $mark_len); // skip "{{{"
    // create end mark
    $endmark = "";
    for ($i = 1; $i <= $mark_len; $i++) { $endmark .= '}'; }
    $endmark .= $eol;
    $src = konawiki_parser_token($text, $endmark);
    //
    return array("cmd"=>"block", "text"=>$src);
}

function konawiki_public($key, $def = "") {
  global $konawiki_data;
  return isset($konawiki_data[$key]) ?
    $konawiki_data[$key] : $def;
}
function konawiki_addPublic($key, $val) {
  global $konawiki_data;
  $konawiki_data[$key] = $val;
}
function konawiki_param($key, $def = "") {
  global $kona3conf;
  return isset($kona3conf[$key]) ? $kona3conf[$key] : $def;
}

/* vim:set expandtab ts=2 sw=2: */
