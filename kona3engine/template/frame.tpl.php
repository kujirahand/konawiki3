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
$page_name_ = "<a href='$page_href'>{$page_title_}</a>";

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title><?php echo $head_title ?></title>
  <!-- for kindle html -->
  <style>
    .strong  { text-decoration: underline; font-weight:bold; }
    .strong2 { text-decoration: underline; }
  </style>
  <link rel="stylesheet" href="index.php?kona3.css&skin" type="text/css">
 </head>
<body>

<!-- header.begin -->
<div id="wikiheader">
  <div id="wikititle">
    <?php echo $logo_title_ ?>
    <span id="pagename">&gt; <?php echo $page_name_ ?></span>
  </div>
</div>
<!-- header.end -->

<!-- wikibody.begin -->
<div id="wikiframe"><?php echo $wikibody ?></div>
<!-- wikibody.end -->

<!-- footer.begin -->
<div id="wikifooter">
  <div class="footer_menu"><?php echo kona3getMenu() ?></div>
  <div class="info"><?php echo kona3getSysInfo() ?></div>
</div>
<!-- footer.end -->
</body>
</html>
