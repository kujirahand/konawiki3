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
    $js .= "<script type=\"text/javascript\" src=\"$j\"></script>\n";
  }
}
$css = "";
if (isset($kona3conf['css'])) {
  $csslist = $kona3conf['css'];
  $csslist = array_unique($csslist);
  foreach($csslist as $c) {
    $css .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$c}\">";
  }
}
$head_tags = "\n";
if (isset($kona3conf['header.tags'])) {
  foreach($kona3conf['header.tags'] as $tag) {
    $head_tags .= $tag."\n";
  }
}
$language = $kona3conf["language"];

?>
<!DOCTYPE html>
<html lang="<?php echo $language ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width">
  <title><?php echo $head_title ?></title>

<link rel="stylesheet" href="index.php?kona3.css&skin" type="text/css">
<?php echo $js . $css . $head_tags ?>

  <!-- for kindle html -->
  <style>
    strong  { text-decoration: underline; font-weight:bold; }
    .strong  { text-decoration: underline; font-weight:bold; }
    .strong2 { text-decoration: underline; }
    .resmark { margin-left: 1em; }
  </style>
 </head>
<body>

<!-- header.begin -->
<?php if (empty($_GET['print']) || !$_GET['print']): ?>
<div id="wikiheader">
  <div id="wikititle">
    <?php echo $logo_title_ ?>
    <span id="pagename">&gt; <?php echo $page_name_ ?></span>
  </div>
</div>
<?php endif; ?>
<!-- header.end -->

<!-- wikibody.begin -->
<div id="wikiframe"><?php echo $wikibody ?></div>
<!-- wikibody.end -->

<!-- footer.begin -->
<div id="wikifooter">
  <?php if (empty($_GET['print']) || !$_GET['print']): ?>
  <div class="footer_menu"><span><?php echo kona3getMenu() ?></span></div>
  <div class="info"><?php echo kona3getSysInfo() ?></div>
  <?php endif; ?>
</div>
<!-- footer.end -->
</body>
</html>


