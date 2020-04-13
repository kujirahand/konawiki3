<?php
/** frame template */
global $kona3conf;

// Parameters
if (empty($page_title)) $page_title = "?";
if (empty($page_body))  $page_body = "page_body is empty";

$wiki_title_ = kona3text2html($kona3conf['title']);
$page_title_ = Kona3text2html($page_title);

$logo_href = kona3getPageURL(KONA3_WIKI_FRONTPAGE);
$page_href = kona3getPageURL($page_title);

// Is FrontPage?
if ($page_title == KONA3_WIKI_FRONTPAGE) {
  // FrontPage
  $head_title = "{$wiki_title_}";
} else {
  // Normal page
  $head_title = "{$page_title}-{$wiki_title_}";
}
//
$logo_title_ = "<a href='$logo_href'>{$wiki_title_}</a>";

// if has directories
if (strpos($page_title, '/') >= 0) {
  $titles = explode('/', $page_title);
  $title_a = array();
  $title_links = array();
  foreach ($titles as $title) {
    $title_a[] = $title;
    $name = implode('/', $title_a);
    $name_html = kona3text2html($title);
    $name_link = kona3getPageURL($name);
    $title_links[] = "<a href='$name_link'>$name_html</a>";
  }
  $page_name_ = implode('/', $title_links);
} else {
  $page_name_ = "<a href='$page_href'>{$page_title_}</a>";
}
// js & css & header tags
$js = "";
if (isset($kona3conf['js'])) {
  $jslist = $kona3conf['js'];
  $jslist = array_unique($jslist);
  foreach($jslist as $j) {
    $js .= "<script type=\"text/javascript\"\n".
           " src=\"$j\"></script>\n";
  }
}
$css = "";
if (isset($kona3conf['css'])) {
  $csslist = $kona3conf['css'];
  $csslist = array_unique($csslist);
  foreach($csslist as $c) {
    $css .= "<link rel=\"stylesheet\" type=\"text/css\"\n".
            " href=\"{$c}\">\n";
  }
}
$head_tags = "\n";
if (isset($kona3conf['header.tags'])) {
  foreach($kona3conf['header.tags'] as $tag) {
    $head_tags .= $tag."\n";
  }
}
$language = KONA3_LANG;

?>
<!DOCTYPE html>
<html lang="<?php echo $language ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $head_title ?></title>

<!-- include files -->
<?php echo $js . $css . $head_tags ?>
</head>
<body>

<!-- header.begin -->
<div id="wikiheader"><div class="header_pad">
  <div id="wikititle">
    <?php echo $logo_title_ ?>
    <span id="pagename">&gt; <?php echo $page_name_ ?></span>
  </div>
</div></div><!-- end of wikiheader -->
<!-- header.end -->
