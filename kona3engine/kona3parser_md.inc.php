<?php
/**
 * konawki3 markdown parser (UTF-8)
 */
include_once 'kona3lib.inc.php';

// Title and list marker constants for Japanese mode
define('KONA3_MD_H1_MARK', '■');
define('KONA3_MD_H2_MARK', '●');
define('KONA3_MD_H3_MARK', '▲');
define('KONA3_MD_UL_MARK', '・');

/**
 * convert text to html
 */
global $kona3markdown_parser_depth;
if (empty($kona3markdown_parser_depth)) {
    $kona3markdown_parser_depth = 0;
}

function kona3markdown_parser_convert($text, $flag_isContents = TRUE)
{
    global $kona3markdown_parser_depth;
    // parse & render
    $kona3markdown_parser_depth++;
    $tokens = kona3markdown_parser_parse($text);
    $html   = kona3markdown_parser_render($tokens, $flag_isContents);
    $kona3markdown_parser_depth--;
    return $html;
}
/** get raw text */
function kona3markdown_getRawText()
{
    return kona3markdown_public('raw_text');
}
/** get raw tokens */
function kona3markdown_getRawTokens()
{
    return kona3markdown_public('raw_tokens');
}

/**
 * 構文を解析して配列に入れる
 */
function kona3markdown_parser_parse($text)
{
    global $eol;

    // Japanese header levels mapping
    $ja_headers = [
        KONA3_MD_H1_MARK => 1,
        KONA3_MD_H2_MARK => 2,
        KONA3_MD_H3_MARK => 3,
    ];

    // convert CRLF to LF
    $text = preg_replace('#(\r\n|\r)#',"\n", $text)."\n";
    kona3markdown_addPublic('EOL', "\n");
    kona3markdown_addPublic('raw_text', $text);
    $eol = kona3markdown_public("EOL", "\n");
    $para_br = kona3markdown_param('para_enabled_br', true);
    //
    $level = 0;

    // main loop
    $tokens = array();
    $oldtext = '';
    while ( $text != "") {
        // check parse error
        if ($text == $oldtext) {
            $sb = mb_substr($text, 0, 32)."..\n";
            $sb = str_replace("\n", '[LF]', $sb);
            echo "<pre>[PARSE ERROR] ".$sb;
            echo "<a href='https://github.com/kujirahand/konawiki3/issues'>Please report...</a></pre>";
            break;
        }
        $oldtext = $text;
        // check line head
        $c = mb_substr($text, 0, 1);
        $c2 = mb_substr($text, 0, 2);
        // TITLE (Standard Markdown)
        if ($c == "#") {
            $level = kona3markdown_parser_count_level($text, $c);
            kona3markdown_parser_skipSpace($text);
            $tokens[] = array("cmd"=>"*", "text"=>kona3markdown_parser_token($text, $eol), "level"=>$level);
            kona3markdown_parser_skipEOL($text);
            continue;
        }
        // TITLE (Japanese Mode)
        else if (isset($ja_headers[$c])) {
            $level = $ja_headers[$c];
            kona3markdown_parser_getchar($text);
            kona3markdown_parser_skipSpace($text);
            $tokens[] = array("cmd"=>"*", "text"=>kona3markdown_parser_token($text, $eol), "level"=>$level);
            kona3markdown_parser_skipEOL($text);
            continue;
        }
        // LIST <ul>
        if ($c == '-' || $c2 == '* ' || $c == KONA3_MD_UL_MARK) {
            // hr
            if ($c == '-' && preg_match('#^(-{5,})\n#', $text, $m)) {
                $text = substr($text, strlen($m[0]));
                kona3markdown_parser_skipEOL($text);
                $tokens[] = array("cmd"=>"hr", "text"=>"", "level"=>$level);
            } else {
                if ($c == KONA3_MD_UL_MARK) {
                    $level = kona3markdown_parser_count_level($text, KONA3_MD_UL_MARK);
                } else {
                    $text = mb_substr($text, 1);
                    $level = 1;
                }
                kona3markdown_parser_skipSpace($text);
                $tokens[] = array("cmd"=>"-", "text"=>kona3markdown_parser_token($text, $eol), "level"=>$level);
            }
        }
        // LIST <ol>
        else if ($c == "+") {
            $text = mb_substr($text, 1);
            kona3markdown_parser_skipSpace($text);
            $tokens[] = array("cmd"=>"+", "text"=>kona3markdown_parser_token($text, $eol), "level"=>1);
        }
        else if ($c == ' ') {
            // indent + list
            if (preg_match('#^(\s{1,})\-#', $text, $m)) {
                $text = mb_substr($text, mb_strlen($m[0]));
                $level = intval(strlen($m[1]) / 2) + 1;
                $tokens[] = array("cmd"=>"-", "text"=>kona3markdown_parser_token($text, $eol), "level"=>$level);
            }
            else if (preg_match('#^(\s{1,})\+#', $text, $m)) {
                $text = substr($text, strlen($m[0]));
                $level = intval(strlen($m[1]) / 2) + 1;
                $tokens[] = array("cmd"=>"+", "text"=>kona3markdown_parser_token($text, $eol), "level"=>$level);
            }
            else {
                // skip
                kona3markdown_parser_skipSpace($text);
                continue;
            }
        }
        // 1. 2. 3. ...
        else if (is_numeric($c) && preg_match('#^(\d+)\.#', $text, $m)) {
            $level = 1;
            kona3markdown_parser_skipSpace($text);
            $tokens[] = array("cmd"=>"-", "text"=>kona3markdown_parser_token($text, $eol), "level"=>$level);
        }
        // TABLE
        else if (kona3markdown_parser_tryTable($text, $eol, $tokens)) {
            continue;
        }
        // SOURCE LINE
        else if ($c == " " || $c == "\t") { // src (source) line
            kona3markdown_parser_getchar($text);
            $tokens[] = array("cmd"=>"src", "text"=>kona3markdown_parser_token($text, $eol));
        }
        // resmark or conflict mark
        else if ($c == ">") {
            if (mb_substr($text, 0, 3) == ">>>") {
                $text = mb_substr($text, 3);
                $flag = mb_substr($text, 0, 3);
                $text = mb_substr($text, 3);
                $tokens[] = array("cmd"=>"conflict", "text"=>kona3markdown_parser_token($text, $eol), "flag"=>$flag);
            } else {
                kona3markdown_parser_skipSpace($text);
                kona3markdown_parser_getchar($text); // skip ">"
                $line = kona3markdown_parser_token($text, $eol);
                $tokens[] = array("cmd"=>"resmark", "text"=>$line, "flag"=>">");
            }
        }
        // skip CR LF
        else if ($c == "\r" || $c == "\n") {
            kona3markdown_parser_skipEOL($text);
            $tokens[] = array("cmd"=>"eol", "text"=>"\n");
            continue;
        }
        // PLUG-INS
        else if ($c == "♪") { // plugins
            kona3markdown_parser_getStr($text, mb_strlen($c)); // skip '♪'
            $tokens[] = kona3markdown_parser_plugins($text, $c);
        }
        else if ($c2 == "!!") { // plugins
            kona3markdown_parser_getStr($text, mb_strlen($c2)); // skip mark
            $tokens[] = kona3markdown_parser_plugins($text, $c);
        }
        // SOURCE BLOCK
        else if ($c == "`" && substr($text, 0, 3) == "```") {
            $tokens[] = kona3markdown_parser_sourceBlock($text);
        }
        else if ($c == "~" && substr($text, 0, 3) == "~~~") {
            $tokens[] = kona3markdown_parser_sourceBlock($text);
        }
        else if ($c == ":" && substr($text, 0, 3) == ":::") {
            $tokens[] = kona3markdown_parser_sourceBlock($text);
        }
        else { // plain block
            $line = kona3markdown_parser_token($text, $eol);
            $tokens[] = array("cmd"=>"plain","text"=>$line);
        }
    }
    return $tokens;
}

/**
 * 解析済の配列データを HTML に変換する
 */
function kona3markdown_parser_render($tokens, $flag_isContents = TRUE)
{
    kona3markdown_addPublic('raw_tokens', $tokens);
    $eol = kona3markdown_public("EOL");
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
            $html .= kona3markdown_parser_render_hx($value);
        }
        else if ($cmd == "-" || $cmd == "+") {
            $html .= kona3markdown_parser_render_li($tokens, $index, $cmd);
        }
        else if ($cmd == "|") {
            // Collect all consecutive table rows
            $table_rows = [];
            $index--; // back to this line
            while ($index < count($tokens)) {
                $value = $tokens[$index];
                $cmd  = $value["cmd"];
                if ($cmd != "|") break;

                $text = rtrim($value["text"]);
                $cells = kona3markdown_parser_splitTableRow($text);
                $table_rows[] = $cells;
                $index++;
            }

            // Check if the second row is a separator row
            $is_separator = false;
            $aligns = [];
            if (count($table_rows) >= 2) {
                $sep_row = $table_rows[1];
                $is_separator = true;
                foreach ($sep_row as $cell) {
                    $trimmed = trim($cell);
                    if (!preg_match('/^:?-{3,}:?$/', $trimmed)) {
                        $is_separator = false;
                        break;
                    }
                    if (preg_match('/^:-{3,}:$/', $trimmed)) {
                        $aligns[] = 'center';
                    } elseif (preg_match('/^-{3,}:$/', $trimmed)) {
                        $aligns[] = 'right';
                    } else {
                        $aligns[] = 'left';
                    }
                }
            }

            // Render table HTML
            $html .= "<table class='grid'>".$eol;
            if ($is_separator) {
                // Header row
                $html .= "<thead><tr>";
                $header_row = $table_rows[0];
                foreach ($header_row as $i => $cell) {
                    $align = isset($aligns[$i]) ? $aligns[$i] : null;
                    $style = $align ? " style='text-align:$align;'" : "";
                    $html .= "<th{$style}>" . kona3markdown_parser_tohtml(trim($cell)) . "</th>";
                }
                $html .= "</tr></thead>".$eol;

                // Body rows (skip the separator row at index 1)
                $html .= "<tbody>";
                for ($row_idx = 2; $row_idx < count($table_rows); $row_idx++) {
                    $html .= "<tr>";
                    foreach ($table_rows[$row_idx] as $i => $cell) {
                        $align = isset($aligns[$i]) ? $aligns[$i] : null;
                        $style = $align ? " style='text-align:$align;'" : "";
                        $html .= "<td{$style}>" . kona3markdown_parser_tohtml(trim($cell)) . "</td>";
                    }
                    $html .= "</tr>".$eol;
                }
                $html .= "</tbody>".$eol;
            } else {
                // Fallback for simple tables without separator row
                $html .= "<tbody>";
                foreach ($table_rows as $row) {
                    $html .= "<tr>";
                    foreach ($row as $cell) {
                        $html .= "<td>" . kona3markdown_parser_tohtml(trim($cell)) . "</td>";
                    }
                    $html .= "</tr>".$eol;
                }
                $html .= "</tbody>".$eol;
            }
            $html .= "</table>".$eol;
        }
        else if ($cmd == "src") {
            $html .= kona3markdown_param("source_tag_begin") . kona3markdown_parser_tosource($text). $eol;
            while ($index < count($tokens)) {
                $value = $tokens[$index];
                if ($value["cmd"] == "src") {
                    $html .= kona3markdown_parser_tosource($value["text"]) . $eol;
                    $index++;
                    continue;
                } else {
                    break;
                }
            }
            $html .= kona3markdown_param("source_tag_end").$eol;
        }
        else if ($cmd == "block") {
            $html .= kona3markdown_parser_tosource_block($text, $value['params']);
        }
        else if ($cmd == "hr") {
            $html .= "<hr>".$eol;
        }
        else if ($cmd == "eol") {
            $html .= $eol;
        }
        else if ($cmd == "plugin") {
            $html .= kona3markdown_parser_render_plugin($value);
        }
        else if ($cmd == "conflict") {
            $text = kona3htmlspecialchars($text);
            if (trim($text) == "") { $text = "&nbsp;"; }
            if ($value["flag"] == "[+]") {
                $html .= "<div class='conflictadd'>+ $text</div>".$eol;
            }
            else { //if ($value["flag"] == "[-]") {
                $html .= "<div class='conflictsub'>- $text</div>".$eol;
            }

        }
        else if ($cmd == "resmark") {
            $s = kona3markdown_parser_tohtml($text)."<br/>\n";
            while ($index < count($tokens)) {
                $value = $tokens[$index];
                if ($value["cmd"] == "resmark") {
                    $s .= kona3markdown_parser_tohtml($value["text"]) . "<br/>" . $eol;
                    $index++;
                    continue;
                } else {
                    break;
                }
            }
            $html .= "<div class='resmark'>".$s."</div>{$eol}";
        }
        else {
            $block = $text;
            while ($index < count($tokens)) {
                $value = $tokens[$index];
                if ($value["cmd"] == "plain") {
                    $block .= $eol.$value["text"];
                    $index++;
                    continue;
                } else {
                    break;
                }
            }
            $block_html = kona3markdown_parser_tohtml($block);
            $block_html = preg_replace('#\n#', '<br/>', $block_html);
            $html .= "<p>{$block_html}</p>{$eol}";
        }
    }
    if ($flag_isContents) {
        $html .= "</div>\n";
    }
    return $html;
}

function kona3markdown_parser_render_hx(&$value)
{
    global $kona3markdown_headers, $eol;
    if (empty($kona3markdown_headers)) $kona3markdown_headers = array();

    $level_from = 1;
    $level = $value["level"];
    $i = $level + ($level_from - 1);
    $text  = $value["text"];
    // calc title hash
    $kona3markdown_headers[$level] = $text;
    $all_text = "/";
    for ($j = 1; $j <= $level; $j++) {
        $all_text .= $kona3markdown_headers[$level]."/";
    }
    $hash   = sprintf("%x",crc32($all_text));
    $uri = kona3htmlspecialchars(kona3getPageURL())."#h{$hash}";
    $anchor = "<a id='h{$hash}' name='h{$hash}' href='$uri' class='anchor_super'>&nbsp;*</a>";
    $noanchor = kona3markdown_param("noanchor", FALSE) || kona3markdown_public('noanchor', FALSE);
    if ($noanchor) $anchor = "";
    return "<h$i>".kona3markdown_parser_tohtml($text)."{$anchor}</h$i>{$eol}";
}

function kona3markdown_parser_tryTable(&$text, $eol, &$tokens)
{
    $lines = explode($eol, $text);
    if (count($lines) == 0 || $lines[0] === "") {
        return false;
    }

    $rows = [];
    $line0 = $lines[0];
    $is_leading_pipe_table = (substr($line0, 0, 1) === "|");
    if ($is_leading_pipe_table) {
        $has_separator = false;
        $line_count = count($lines);
        for ($i = 0; $i < $line_count; $i++) {
            $line = $lines[$i];
            if ($line === "") {
                break;
            }
            if (substr($line, 0, 1) === "|") {
                $rows[] = $line;
                if ($i == 1 && kona3markdown_parser_isTableSeparatorRow($line)) {
                    $has_separator = true;
                }
                continue;
            }
            if ($has_separator && kona3markdown_parser_hasUnescapedPipe($line)) {
                $rows[] = $line;
                continue;
            }
            break;
        }
    } else {
        if (!kona3markdown_parser_hasUnescapedPipe($line0) ||
            !isset($lines[1]) ||
            !kona3markdown_parser_isTableSeparatorRow($lines[1])) {
            return false;
        }

        $rows[] = $line0;
        $rows[] = $lines[1];
        $line_count = count($lines);
        for ($i = 2; $i < $line_count; $i++) {
            $line = $lines[$i];
            if ($line === "" || !kona3markdown_parser_hasUnescapedPipe($line)) {
                break;
            }
            $rows[] = $line;
        }
    }

    if (count($rows) == 0) {
        return false;
    }

    $text = implode($eol, array_slice($lines, count($rows)));
    foreach ($rows as $row) {
        $tokens[] = array("cmd"=>"|", "text"=>$row);
    }
    return true;
}

function kona3markdown_parser_isTableSeparatorRow($line)
{
    $cells = kona3markdown_parser_splitTableRow($line);
    if (count($cells) == 0) {
        return false;
    }
    foreach ($cells as $cell) {
        if (!preg_match('/^:?-{3,}:?$/', trim($cell))) {
            return false;
        }
    }
    return true;
}

function kona3markdown_parser_hasUnescapedPipe($line)
{
    $len = strlen($line);
    $escaped = false;
    for ($i = 0; $i < $len; $i++) {
        $ch = $line[$i];
        if ($escaped) {
            $escaped = false;
            continue;
        }
        if ($ch === "\\") {
            $escaped = true;
            continue;
        }
        if ($ch === "|") {
            return true;
        }
    }
    return false;
}

function kona3markdown_parser_splitTableRow($line)
{
    $line = trim($line);
    if ($line !== "" && substr($line, 0, 1) === "|") {
        $line = substr($line, 1);
    }
    if ($line !== "" && substr($line, -1) === "|" &&
        !kona3markdown_parser_isEscapedPipeAt($line, strlen($line) - 1)) {
        $line = substr($line, 0, -1);
    }

    $cells = [];
    $cell = "";
    $escaped = false;
    $len = strlen($line);
    for ($i = 0; $i < $len; $i++) {
        $ch = $line[$i];
        if ($escaped) {
            $cell .= ($ch === "|") ? "|" : "\\".$ch;
            $escaped = false;
            continue;
        }
        if ($ch === "\\") {
            $escaped = true;
            continue;
        }
        if ($ch === "|") {
            $cells[] = $cell;
            $cell = "";
            continue;
        }
        $cell .= $ch;
    }
    if ($escaped) {
        $cell .= "\\";
    }
    $cells[] = $cell;
    return $cells;
}

function kona3markdown_parser_isEscapedPipeAt($line, $pos)
{
    $slashes = 0;
    for ($i = $pos - 1; $i >= 0 && $line[$i] === "\\"; $i--) {
        $slashes++;
    }
    return ($slashes % 2) == 1;
}

function kona3markdown_parser_render_li(&$tokens, &$index, &$cmd)
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
        $text = kona3markdown_parser_tohtml($value["text"]);
        $html .= $_html . $text;
        $level = $value["level"];
        $index++;
    }
    for ($i = 0; $i < $level; $i++) {
        $html .= "</li>\n{$li_end}";
    }
    return $html;
}


function kona3markdown_parser_skipEOL(&$text)
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

function kona3markdown_parser_skipSpace(&$text)
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

function kona3markdown_parser_getchar(&$text)
{
    $c = mb_substr($text, 0, 1);
    $text = mb_substr($text, 1);
    return $c;
}

function kona3markdown_parser_getStr(&$text, $len)
{
    $result = mb_substr($text, 0, $len);
    $text = mb_substr($text, $len);
    return $result;
}

function kona3markdown_parser_ungetchar(&$text, $ch)
{
    $text = $ch . $text;
}

function kona3markdown_parser_count_level(&$text, $ch)
{
    $level = 0;
    for(;;){
        $c = kona3markdown_parser_getchar($text);
        if ($c == $ch) {
            $level++;
            continue;
        }
        kona3markdown_parser_ungetchar($text, $c);
        break;
    }
    return $level;
}

function kona3markdown_parser_count_level2(&$text, $ch_array)
{
    $level = 0;
    for(;;){
        $c = kona3markdown_parser_getchar($text);
        $r = array_search($c, $ch_array);
        if ($r !== FALSE) {
            $level++;
            continue;
        }
        kona3markdown_parser_ungetchar($text, $c);
        break;
    }
    return $level;
}

function kona3markdown_parser_token(&$text, $sub)
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
function __kona3markdown_parser_tohtml(&$text, $level)
{
    // make link
    $result = "";
    while ($text <> "") {
        $c1 = mb_substr($text, 0, 1);
        $c2 = mb_substr($text, 0, 2);
        // escape
        if ($c1 == '\\') {
            $result .= kona3htmlspecialchars(mb_substr($text, 1, 1));
            $text = mb_substr($text, 2);
            continue;
        }
        // inline code
        if ($c1 == '`') {
            $text = mb_substr($text, 1);
            $s = kona3markdown_parser_token($text, "`");
            $str = kona3htmlspecialchars($s);
            $result .= "<span class='code'><code>$str</code></span>";
            continue;
        }
        // strong1 **
        if ($c2 == "**") {
            $text = mb_substr($text, 2);
            $s = kona3markdown_parser_token($text, "**");
            $str = kona3markdown_parser_tohtml($s);
            $result .= "<strong class='strong1'>$str</strong>";
            continue;
        }
        // strong2
        if ($c2 == "__" && kona3markdown_param('md_underscore_emphasis', TRUE)) {
            $text = mb_substr($text, 2);
            $s = kona3markdown_parser_token($text, "__");
            $str = kona3markdown_parser_tohtml($s);
            $result .= "<strong class='strong2'>$str</strong>";
            continue;
        }
        // strike
        if ($c2 == '~~') {
            $text = mb_substr($text, 2);
            $s = kona3markdown_parser_token($text, "~~");
            $str = kona3markdown_parser_tohtml($s);
            $result .= "<del>$str</del>";
            continue;
        }
        // wikilink
        if (preg_match('#^\[(.+?)\]\((.+?)\)#',$text, $m)) {
            $text = substr($text, strlen($m[0]));
            $label = $m[1];
            $link = $m[2];
            $result .= kona3markdown_parser_makeWikiLink($label, $link);
            continue;
        }
        // image link
        if (preg_match('#^\!\[(.*?)\]\((.+?)\)#',$text, $m)) {
            $text = substr($text, strlen($m[0]));
            $alt = $m[1];
            $link = $m[2];
            $plugin = kona3markdown_parser_getPlugin('ref');
            $param_ary = [$link, '*'.$alt];
            include_once($plugin["file"]);
            $p = array("cmd"=>"plugin", "text"=>"ref", "params"=>$param_ary);
            $s = kona3markdown_parser_render_plugin($p);
            $result .= $s;
            continue;
        }
        // url
        if (preg_match('@^(http|https)\://[\w\d\.\#\$\%\&\(\)\-\=\_\~\^\|\,\.\/\?\+\!\[\]\@]+@', $text, $m) > 0) {
            $result .= kona3markdown_parser_makeUriLink($m[0]);
            $text = substr($text, strlen($m[0]));
            continue;
        }
        // mailto
        if (preg_match('/^(mailto)\:[\w\d\.\#\$\%\&\-\=\_\~\^\.\/\?\+\@]+/', $text, $m) > 0) {
            $result .= kona3markdown_parser_makeUriLink($m[0]);
            $text = substr($text, strlen($m[0]));
            continue;
        }
        // ~
        if ($c2 == "~\n" || $c2 == "~\r") {
            $result .= "<br/>";
            $text = substr($text, strlen($c2));
            continue;
        }
        // 1chars replace
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


function kona3markdown_parser_tohtml($text)
{
    $r = "";
    while ($text != "") {
        $r .= __kona3markdown_parser_tohtml($text, 0);
    }
    return $r;
}

function kona3markdown_parser_tosource($src)
{
    $src = kona3htmlspecialchars($src);
    return $src;
}

function kona3markdown_parser_tosource_block($src, $params = [])
{
    global $eol;
    // plugin ?
    $fname = array_shift($params);
    $blockType = array_shift($params);
    $fileType = array_shift($params);
    $fileName = array_shift($params);

    if ($fname == null) { $fname = ''; }
    if ($blockType === 'plugin') {
        $fname = mb_substr($fname, 1);
        $line  = kona3markdown_parser_token($fname, "\n");
        $pname = trim(kona3markdown_parser_token($line, "("));
        $arg_str = kona3markdown_parser_token($line, ")");
        if ($arg_str != "") {
            $args = explode(",", $arg_str);
        } else {
            $args = array();
        }
        array_push($args, $src);
        // Call plugin function
        $pinfo = kona3markdown_parser_getPlugin($pname);
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
    $fname = $fileName;
    $fname = kona3htmlspecialchars($fname);
    if ($fname != '') {
        $css = 'font-size:0.7em; background-color:#f0f0f0; color:#909090; padding:2px;';
        $fname = "<div style='$css'>$fname</div>";
    }
    $src = kona3htmlspecialchars($src);
    $begin = "<div>$fname<pre class='code'>";
    $end   = "</pre></div>" . $eol;
    return $begin.$src.$end;
}

function kona3markdown_parser_makeUriLink($url)
{
    $disp = mb_strimwidth($url, 0, 60, "..");
    $disp = kona3htmlspecialchars($disp);
    $link = kona3markdown_parser_checkURL($url, ['http', 'https', 'mailto']);
    return "<a href='$link'>$disp</a>";
}

function kona3markdown_parser_makeWikiLink($name, $linkto)
{
    $caption = kona3markdown_parser_tohtml($name);
    $link = $linkto;
    if (strpos($link, '://') === FALSE && parse_url($link, PHP_URL_SCHEME) === null) {
        // wiki link
        $link = kona3getPageURL($linkto);
    }

    // check link
    $link = kona3markdown_parser_checkURL($link);
    return "<a href='$link'>$caption</a>";
}

function kona3markdown_parser_checkURL($url, $allowed_schemes = ['http', 'https'])
{
    // allow relative URLs and known-safe schemes only.
    $url = trim($url);
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme !== null) {
        $scheme = strtolower($scheme);
        $allowed_schemes = array_map('strtolower', $allowed_schemes);
        if (!in_array($scheme, $allowed_schemes, true)) {
            $url = preg_replace('#[^a-zA-Z0-9_]#', '_', $url);
            $url = "?WIKI_LINK_ERROR_".$url;
        }
    } else if (preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*\s*:#', $url)) {
        $url = preg_replace('#[^a-zA-Z0-9_]#', '_', $url);
        $url = "?WIKI_LINK_ERROR_".$url;
    }
    $url = kona3htmlspecialchars($url);
    return $url;
}

function kona3markdown_parser_disp_url($url)
{
    $omit = kona3markdown_param("omit_longurl", TRUE);
    if ($omit) {
        $len = kona3markdown_param("omit_longurl_len", 80);
        $url = mb_strimwidth($url, 0, $len, "..");
        return $url;
    } else {
        return $url;
    }
}

function kona3markdown_parser_plugins(&$text, $flag)
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
    kona3markdown_parser_skipSpace($text);
    $c = mb_substr($text, 0, 1);
    if ($c == "(") { // has params
        kona3markdown_parser_getStr($text, 1); // skip '('
        if ($flag === "&") {
            if (strpos($text, ");") >= 0) {
                $param_str = kona3markdown_parser_token($text, ');');
                $res["params"] = explode("\,", $param_str);
            }
        }
        else { // $flag == "#"
            if (strpos($text, ")") >= 0) {
                $param_str = kona3markdown_parser_token($text, ')');
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
        $eol = kona3markdown_public("EOL");
        $line = kona3markdown_parser_token($text, $eol);
        $line = mb_substr($line, 1);
        $res["params"] = explode("～", $line);
    }

    // check plugins
    kona3markdown_parser_pluginInit($word, $res);
    return $res;
}

function kona3markdown_parser_plugin_error($pname, &$res, $reason = 'No Plugin')
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

function kona3markdown_parser_pluginInit($pname, &$res)
{
    // check plugin
    $plugin = kona3markdown_parser_getPlugin($pname);
    $f = $plugin["file"];
    if ($plugin['disallow'] || !file_exists($f)) {
        return kona3markdown_parser_plugin_error($pname, $res);
    }
    // include plugin file
    include_once($f);
    // callable?
    if (is_callable($plugin["init"])) {
        @call_user_func($plugin["init"]);
    }
    return $res;
}

function kona3markdown_parser_render_plugin($value)
{
    $pname  = $value['text'];
    $params = $value['params'];

    $info = kona3markdown_parser_getPlugin($pname);
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

function kona3markdown_parser_getPlugin($pname)
{
    return kona3getPluginPathInfo($pname);
}

/**
 * ソースコードのブロックを抽出する
 * @param $text
 * @return array
 */
function kona3markdown_parser_sourceBlock(&$text)
{
    $eol = kona3markdown_public("EOL");
    $endmark = kona3markdown_parser_getStr($text, 3); // skip "```" or "~~~" or ":::"
    $blockType = "code";
    $fileType = "text";
    $fileName = "";
    
    // get block name
    $name = trim(kona3markdown_parser_token($text, $eol));
    $ch = substr($name, 0, 1);

    // :::plugin
    if ($endmark === ':::') { // plugin
        $blockType = 'plugin';
        $fileType = 'kona3plugin';
        if ($ch !== '#' && $ch !== '♪') {
            $name = '#'.$name;
        }
    } else {
        // check markdown source code ```type:filename ... ```
        if (preg_match('/^([a-zA-Z_\-]+)\:(.+)$/', $name, $m)) {
            $fileType = $m[1];
            $fileName = $m[2];
        } else if (preg_match('/^[0-9a-zA-Z_\-]+/', $name)) {
            $fileType = $name;
        } else if ($ch == '#' || $ch == '♪') {
            // KonaWiki Plugin format
            $blockType = 'plugin';
            $fileType = 'kona3plugin';
        }
    }
    
    // create end mark
    $endmark .= $eol;
    $src = kona3markdown_parser_token($text, $endmark);
    $src = str_replace("\n\`\`\`", "\n```", $src);
    return array("cmd"=>"block", "text"=>$src, "params" =>[$name, $blockType, $fileType, $fileName]);
}

function kona3markdown_public($key, $def = "") {
    global $kona3markdown_data;
    return isset($kona3markdown_data[$key]) ?
        $kona3markdown_data[$key] : $def;
}
function kona3markdown_addPublic($key, $val) {
    global $kona3markdown_data;
    $kona3markdown_data[$key] = $val;
}
function kona3markdown_param($key, $def = "") {
    global $kona3conf;
    return isset($kona3conf[$key]) ? $kona3conf[$key] : $def;
}
