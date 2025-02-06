<?php

/** KonaWiki3 show */

function kona3_action_show()
{
    global $kona3conf;
    $page = $kona3conf["page"];
    $file_exists = FALSE;

    // check login
    kona3show_check_private($page);

    // detect file type
    $wiki_live = kona3show_detect_file($page, $fname, $ext);

    // body
    if ($wiki_live) {
        // load file
        $txt = kona3lock_load($fname);
        if ($txt === FALSE) {
            $txt = kona3show_file_not_found($page, $ext);
        } else {
            $kona3conf['data_filename'] = $fname;
            $file_exists = TRUE;
        }
    } else {
        $txt = kona3show_file_not_found($page, $ext);
    }

    // convert
    $ext = strtolower($ext);
    if ($ext == ".txt") {
        $page_body = konawiki_parser_convert($txt);
    } else if ($ext == ".md") {
        $page_body = kona3show_markdown_convert($txt);
    } else if ($ext == "__dir__") {
        $txt = "* {$page}\n\n#ls"; // ls
        $page_body = konawiki_parser_convert($txt);
    } else if ($ext == '.png' || $ext == '.gif' || $ext == '.jpg' || $ext == '.jpeg') {
        $txt = "#ref({$page})"; // images
        $page_body = konawiki_parser_convert($txt);
    } else if ($ext == '.pdf' || $ext == '.xlsx' || $ext == '.docx' || $ext == '.xls' || $ext == '.doc') {
        $txt = "{{{#ref\n{$page}\n}}}\n"; // pdf
        $page_body = konawiki_parser_convert($txt);
    } else {
        kona3error($page, "Sorry, System Error.");
        exit;
    }
    // counter
    $txt = str_replace("\r\n", "\n", $txt); // CRLF to LF
    $cnt_txt = mb_strlen($txt);
    if (kona3isLogin()) {
        $cnt_code = 0;
        include_once dirname(__DIR__) . '/plugins/countpages.inc.php';
        kona3countpages_extractFileCode($txt, $cnt_txt, $cnt_code);
    }

    // header and footer
    $allpage_header = '';
    $allpage_footer = '';
    if (!empty($kona3conf['allpage_header'])) {
        $allpage_header =
            "<div class='allpage_header'>" .
            konawiki_parser_convert(
                $kona3conf['allpage_header']
            ) .
            "</div><!-- end of .allpage_hader -->\n";
    }
    if (!empty($kona3conf['allpage_footer'])) {
        $allpage_footer =
            "<div class='allpage_footer'>" .
            konawiki_parser_convert(
                $kona3conf['allpage_footer']
            ) .
            "</div><!-- end of .allpage_footer -->\n";
    }
    // tags
    $tags = '';
    if ($file_exists) {
        $page_id = kona3db_getPageId($page, TRUE); // exists page
        $tags_a = db_get('SELECT * FROM tags WHERE page_id=?', [$page_id]);
        if ($tags_a) {
            $a = [];
            foreach ($tags_a as $t) {
                $tag = $t['tag'];
                $tag_h = htmlspecialchars($tag);
                $tag_u = urlencode($tag);
                $url = kona3getPageURL($page, 'plugin', '', "name=tags&tag=$tag_u");
                $a[] = "<a href='$url'>$tag_h</a>";
            }
            $tags = '<div class="desc" style="text-align:right;font-size:0.8em;color:gray;">Tags: ' .
                implode('/', $a) . '</div>';
        }
    }
    //
    $page_body =
        $allpage_header .
        $page_body .
        $allpage_footer .
        $tags;

    // edit link
    $edit_link = "";
    if (kona3isLogin()) {
        $edit_link = kona3getPageURL($page, "edit", "", "edit_token=" . kona3_getEditToken($page, FALSE));
    }
    // show
    kona3template('show.html', [
        "page_title" => $page,
        "page_body"  => $page_body,
        "cnt_txt"    => $cnt_txt,
        "page_file"  => $fname,
        "edit_link"  => $edit_link,
    ]);
}

function kona3show_check_private($page, $showLoginLink = TRUE)
{
    if (kona3getConf('wiki_private')) {
        if (!kona3isLogin()) {
            header('HTTP/1.0 403 Forbidden');
            if (!$showLoginLink) { // for FILE
                echo "Forbidden";
                exit;
            }
            // show login page
            $url = kona3getPageURL($page, "login");
            $msg_private = lang('Private Mode');
            $msg_please_login = lang('Please login.');
            kona3error(
                $page,
                "<div>$msg_private</div><div>&nbsp;</div>" .
                    "<div><a href='$url'>$msg_please_login</a></div>"
            );
            exit;
        }
    }
}

function kona3show_file_not_found($page, &$ext)
{
    global $kona3conf;
    $kona3conf["page_not_found"] = TRUE;
    // show page not found
    $PageNotFound = lang('Page Not Found.');
    $txt = "* {$page}\n{$PageNotFound}\n";
    return $txt;
}

function kona3show_detect_file($page, &$fname, &$ext)
{
    // is text file?
    $ext = '.' . kona3getConf('def_text_ext', 'txt');
    $fname = koan3getWikiFileText($page);
    if (file_exists($fname)) {
        $ext = "." . kona3getFileExt($fname);
        return TRUE;
    }
    // dir?
    $fname = kona3getWikiFile($page, FALSE);
    if (is_dir($fname)) {
        $ext = '__dir__';
        return TRUE;
    }
    // direct
    $fname = kona3getWikiFile($page, FALSE, '', FALSE);
    if (file_exists($fname)) {
        $ext = '';
        if (preg_match('#(\.[a-zA-Z0-9_]+)$#', $fname, $m)) {
            $ext = $m[1];
        }
        return TRUE;
    }
    return FALSE;
}

function kona3show_markdown_convert($txt)
{
    require_once dirname(__DIR__) . '/kona3parser_md.inc.php';
    return kona3markdown_parser_convert($txt);
}
