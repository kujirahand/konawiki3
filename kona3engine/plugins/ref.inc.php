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
    return kona3plugins_ref_render($args, FALSE, 'ref');
}

/**
 * ref/image プラグイン共通の描画処理
 *
 * @param array<int, string> $args
 * @param bool $force_image 拡張子にかかわらず画像として描画する
 * @param string $plugin_name エラー表示に使うプラグイン名
 * @param bool $zoom クリックで画像を拡大表示する(@link 指定が無い場合のみ)
 * @return string
 */
function kona3plugins_ref_render($args, $force_image = FALSE, $plugin_name = 'ref', $zoom = FALSE) {
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
    $caption = kona3htmlspecialchars($caption);
    $url_path = parse_url($rawurl, PHP_URL_PATH);
    if ($url_path === null || $url_path === false) {
        $url_path = $rawurl;
    }
    $ext = strtolower(pathinfo($url_path, PATHINFO_EXTENSION));
    $url = kona3plugins_ref_external_url($rawurl);
    if ($url === '') {
        // file link
        $url2 = kona3plugins_ref_file_url($page, $rawurl);
        if ($url2 === '') {
            $url = kona3plugins_ref_error_url($rawurl);
            return "<div class='error'>#{$plugin_name}({$url})</div>";
        }
        $url = kona3htmlspecialchars($url2);
    }
    // Is image?
    $image_type = kona3getConf("image_pattern", "(jpg|jpeg|png|gif|ico|svg|webp)");
    $pattern = "#^($image_type)$#";
    if ($force_image || preg_match($pattern, $ext)) {
        // image
        $use_zoom = $zoom && ($link == '');
        if ($link != '') {
            $link_safe = kona3plugins_ref_external_url($link);
            $link = ($link_safe === '') ? $url : $link_safe;
        } else {
            $link = $url;
        }
        $caph = "<div class='memo'>".$caption."</div>";
        $div = "<div>";
        if ($float) { $div = "<div {$float}>"; }
        $a_attr = $use_zoom ? " class='kona3-image-zoom'" : "";
        $head = $use_zoom ? kona3plugins_ref_zoom_head() : "";
        $code = $head . $div.
            "<div><a href='$link'{$a_attr}><img src='$url' {$size}></a></div>".
            (($caption != "") ? $caph : "").
            "</div>";
    } else {
        // not image
        if ($caption == "") {
            $caption = kona3htmlspecialchars($rawurl);
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
    $attach_path = isset($kona3conf["path.attach"]) ? $kona3conf["path.attach"] : '';
    $attach_url = isset($kona3conf["url.attach"]) ? $kona3conf["url.attach"] : '';
    if ($attach_path !== '' && $attach_url !== '') {
        $f = kona3path_join($attach_path, $url);
        if (file_exists($f)) { return kona3path_join($attach_url, $url); }
    }

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

function kona3plugins_ref_external_url($url) {
    $url = trim($url);
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme === null || $scheme === false) {
        return '';
    }
    $scheme = strtolower($scheme);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return '';
    }
    return kona3htmlspecialchars($url);
}

function kona3plugins_ref_error_url($url) {
    $url = trim($url);
    $url = preg_replace('#^([a-zA-Z][a-zA-Z0-9+\-.]*)\s*:#', '$1_', $url);
    return kona3htmlspecialchars($url);
}

/**
 * 画像拡大表示用のCSS/JSを返す(1ページにつき最初の1回だけ出力)
 *
 * @return string
 */
function kona3plugins_ref_zoom_head() {
    global $kona3conf;
    $key = 'plugins.image.zoom.init';
    if (!empty($kona3conf[$key])) { return ''; }
    $kona3conf[$key] = 1;
    return <<<'EOS'
<style>
.kona3-image-zoom { cursor: zoom-in; }
#kona3-image-zoom-overlay {
    position: fixed; inset: 0; z-index: 10000;
    background: rgba(0, 0, 0, 0.85);
    display: flex; align-items: center; justify-content: center;
}
#kona3-image-zoom-overlay img {
    max-width: 96vw; max-height: 90vh; object-fit: contain;
    background: #fff; cursor: zoom-out;
}
#kona3-image-zoom-overlay .kona3-zoom-buttons {
    position: absolute; top: 0.6em; right: 0.8em; display: flex; gap: 0.5em;
}
#kona3-image-zoom-overlay .kona3-zoom-buttons a,
#kona3-image-zoom-overlay .kona3-zoom-buttons button {
    font-size: 1em; padding: 0.4em 0.9em; cursor: pointer;
    color: #fff; background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.6); border-radius: 4px;
    text-decoration: none; font-family: inherit;
}
#kona3-image-zoom-overlay .kona3-zoom-buttons a:hover,
#kona3-image-zoom-overlay .kona3-zoom-buttons button:hover {
    background: rgba(255, 255, 255, 0.4);
}
</style>
<script>
(() => {
    let overlay = null;
    const close = () => {
        if (overlay) { overlay.remove(); overlay = null; }
        document.removeEventListener('keydown', onKey);
    };
    const onKey = (e) => { if (e.key === 'Escape') { close(); } };
    const open = (src) => {
        close();
        overlay = document.createElement('div');
        overlay.id = 'kona3-image-zoom-overlay';
        const img = document.createElement('img');
        img.src = src;
        const buttons = document.createElement('div');
        buttons.className = 'kona3-zoom-buttons';
        const dl = document.createElement('a');
        dl.href = src;
        dl.download = '';
        dl.target = '_blank';
        dl.rel = 'noopener';
        dl.textContent = 'ダウンロード';
        const cl = document.createElement('button');
        cl.type = 'button';
        cl.textContent = '閉じる';
        cl.addEventListener('click', close);
        buttons.append(dl, cl);
        overlay.append(img, buttons);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target === img) { close(); }
        });
        document.body.appendChild(overlay);
        document.addEventListener('keydown', onKey);
    };
    document.addEventListener('click', (e) => {
        const a = e.target.closest && e.target.closest('a.kona3-image-zoom');
        if (!a) { return; }
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
        e.preventDefault();
        open(a.href);
    });
})();
</script>

EOS;
}
