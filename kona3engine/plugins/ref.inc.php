<?php
/** 画像を表示するプラグイン
 * - [書式] #ref(image.png, w=400, h=300, 400x300, @https, *caption)
 * - [引数]
 * -- image.png ... 画像ファイル名
 * -- w=xxx ... 画像の幅を指定
 * -- h=xxx ... 画像の高さを指定
 * -- (num)x(num) ... (num)x(num)のサイズで表示
 * -- @link ... 画像をリンクする
 * -- *caption ... キャプションを指定
 * -- left/right ... その後の文章を左寄せ/右寄せする(#clearで解除)
 */
function kona3plugins_ref_execute($args) {
    global $kona3conf;
    $page = kona3getPage();
    // get args
    $size = " width='400px' "; // set default image size
    $caption = "";
    $link = "";
    $url = $rawurl = trim(array_shift($args));
    $float = "";
    while ($args) {
        $arg = array_shift($args);
        $arg = trim($arg);
        $c = substr($arg, 0, 1);
        if ($c == "*") {
            $caption = substr($arg, 1);
            continue;
        }
        if ($c == "@") {
            $link = substr($arg, 1);
            continue;
        }
        if ($arg == "left" || $arg == "right") {
            $float = "style = \"float:$arg; padding:0.5em;\"";
            continue;
        }
        // width x height
        if (preg_match("#(\d+)x(\d+)#", $arg, $m)) {
            $size = " width='{$m[1]}' height='{$m[2]}'";
            continue;
        }
        // width=xx or w=xx
        if (preg_match("#(width|w)\=(\d+)(px|\%)?#", $arg, $m)) {
            $size = " width='{$m[2]}{$m[3]}'";
            continue;
        }
        // height=xx or h=xx
        if (preg_match("#(height|h)\=(\d+)(px|\%)?#", $arg, $m)) {
            $size = " height='{$m[2]}{$m[3]}'";
            continue;
        }
    }
    // make link
    $caption = htmlspecialchars($caption, ENT_QUOTES);
    $ext = pathinfo($url, PATHINFO_EXTENSION);
    $url = htmlspecialchars_url($url);
    if (!preg_match("#^https?\:\/\/#", $url)) {
        // file link
        $url2 = kona3plugins_ref_file_url($page, $url);
        if ($url2 === '') {
            $url = htmlspecialchars($url, ENT_QUOTES);
            return "<div class='error'>#ref({$url})</div>";
        }
        $url = $url2;
    }
    // Is image?
    $image_type = kona3getConf("image_pattern", "(jpg|jpeg|png|gif|ico|svg|webp)");
    $pattern = "#^($image_type)$#";
    if (preg_match($pattern, $ext)) {
        // image
        if ($link == '') { $link = $url; }
        $caph = "<div class='memo'>".$caption."</div>";
        $div = "<div>";
        if ($float) { $div = "<div {$float}>"; }
        $code = $div.
            "<div><a href='$link'><img src='$url' {$size}></a></div>".
            (($caption != "") ? $caph : "").
            "</div>";
    } else {
        // not image
        if ($caption == "") {
            $caption = htmlspecialchars($rawurl);
        }
        $code = "<div><a href='$url'>🔗{$caption}</a></div>";
    }
    return $code;
}

function kona3plugins_ref_file_url($page, $url) {
    global $kona3conf;
    // Disallow up dir!! (1/2)
    $url = trim(str_replace('..', '', $url));

    // in attach_dir?
    $f = kona3path_join($kona3conf["path.attach"], $url);
    if (file_exists($f)) { return kona3path_join($kona3conf["url.attach"], $url); }

    // check absolute file - Is this file in same directory?
    if (strpos($page, "/") !== FALSE) {
        $url2 = dirname($page) . "/" . urldecode($url);
        // Disallow up dir!! (2/2)
        $url2 = str_replace('..', '', $url2);
        $fullpath = KONA3_DIR_DATA . "/" . $url2;
        if (file_exists($fullpath)) {
            $url = "index.php?" . urlencode($url2) . "&data";
            return $url;
        }
    }

    // in data_dir? (full path)
    $f = kona3path_join(KONA3_DIR_DATA, $url);
    if (file_exists($f)) {
        $file_url = "index.php?".urlencode($url)."&data";
        return $file_url;
    }

    /*
    // default data
    $f = kona3getWikiFile($url, false);
    if (file_exists($f)) {
        $url = kona3getWikiUrl($url);
    }
    */
    return '';
}

function htmlspecialchars_url($xss){
    $s = htmlspecialchars($xss, ENT_QUOTES, 'UTF-8');
    $s = str_replace('http:','http<',$s);
    $s = str_replace('https:','https<',$s);
    $s = str_replace(':','',$s);
    $s = str_replace(';','',$s);
    $s = str_replace('http<','http:',$s);
    $s = str_replace('https<','https:',$s);
    return $s;
}

