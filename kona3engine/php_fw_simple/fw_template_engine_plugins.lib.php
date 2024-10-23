<?php
// --- filters ---
// escape value
function t_echo($v)
{
    $v = htmlspecialchars($v);
    return $v;
}
function t_multiline($v)
{
    $v = htmlspecialchars($v);
    $v = preg_replace('#(\r\n|\n|\r)#s', '<br>', $v);
    return $v;
}
function t_check_mudai($v)
{
    if (empty($v)) {
        $v = '(無題)';
    }
    $v = trim_url($v);
    $v = mb_strimwidth($v, 0, 100, ' ... ');
    return t_echo($v);
}
function t_check_nanasi($v)
{
    if (empty($v)) {
        $v = '名無し';
    }
    $v = trim_url($v);
    $v = mb_strimwidth($v, 0, 100, ' ... ');
    return t_echo($v);
}
function t_trim40($v)
{
    if (empty($v)) {
        $v = '(なし)';
    }
    $v = trim_url($v);
    $v = mb_strimwidth($v, 0, 40, ' ... ');
    return t_echo($v);
}
function t_trim50($v)
{
    if (empty($v)) {
        $v = '(なし)';
    }
    $v = trim_url($v);
    $v = mb_strimwidth($v, 0, 50, ' ... ');
    return t_echo($v);
}
function t_trim100($v)
{
    if (empty($v)) {
        $v = '(なし)';
    }
    $v = trim_url($v);
    $v = mb_strimwidth($v, 0, 100, ' ... ');
    return t_echo($v);
}
function trim_url($url)
{
    $url = preg_replace_callback('#([a-zA-Z0-9_\-\/\:\.]{20,})#', function ($m) {
        $s = $m[1];
        $s = preg_replace('#(http://|https://)#', '', $s);
        return substr($s, 0, 17).' ... ';
    }, $url);
    return $url;
}
// raw
function t_raw($v)
{
    return $v;
}
function t_safe($v)
{
    return $v;
}

function t_star($v)
{
    $mi = $ni = (int) $v;
    if ($mi > 3) {
        $mi = 5;
    }
    $s = "";
    for ($i = 0; $i < $mi; $i++) {
        $s .= '⭐';
    }
    if ($ni > 5) {
        $s = "<span class='crown'>{$s}...👍".$ni."</span>";
    }
    return $s;
}

// date
function t_date($v)
{
    return date('Y年m月d日', $v);
}
function t_date2($v)
{
    if ($v == 0) { return '-'; }
    return date('Y-m-d', $v);
}
function t_datetime($v)
{
    if ($v == 0) { return '-'; }
    return date('Y/m/d H:i', $v);
}
// format
function t_number_format($v)
{
    $v = (float) $v;
    return number_format($v);
}
// lang
function t_lang($msg)
{
    return lang($msg);
}
// boolstr
function t_boolstr($v)
{
    if ($v) {
        return "true";
    } else {
        return "false";
    }
}

// --- {{{e:xxx}}} ---
function echo_options($sel_value, $labels, $values)
{
    $ret = '';
    $cnt = count($labels);
    for ($i = 0; $i < $cnt; $i++) {
        $label = htmlspecialchars($labels[$i]);
        $value = htmlspecialchars($values[$i]);
        $selected = '';
        if ($value === $sel_value) {
            $selected = ' selected';
        }
        $ret .= "<option value=\"$value\"$selected>$label</option>\n";
    }
    return $ret;
}
