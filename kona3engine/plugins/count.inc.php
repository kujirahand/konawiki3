<?php
/** count plugin
[USAGE] with ID
{{{#count(id=xxx)
abcdefg
abcdefg
}}}

[USAGE] no ID
{{{#count
abcdefg
abcdefg
}}}
 */
function kona3plugins_count_execute($args) {
    $text = "";
    $id = "";
    while ($args) {
        $line = trim(array_shift($args));
        if (preg_match('/^id\=(\w+)/', $line, $m)) {
            $id = $m[1];
        } else {
            $text = $line;
        }
    }
    // count
    $len  = strlen($text);
    $mlen = mb_strlen($text);
    $html = konawiki_parser_convert($text);
    // length
    $counter = "{$mlen}字";
    return <<<EOS
<div class="kona3count" data-id="$id">
    {$html}
    <div class="kona3count-foot">{$counter}</div>
</div>
EOS;
}


