<?php /* template */
global $kona3conf;

// Parameters
if (empty($page_title)) $page_title = "?";
if (empty($page_body))  $page_body = "page_body is empty";

$wiki_title_ = htmlspecialchars($kona3conf['title'], ENT_QUOTES);
$page_title_ = htmlspecialchars($page_title, ENT_QUOTES);
$logo_href = kona3getPageURL(KONA3_WIKI_FRONTPAGE);
$page_href = kona3getPageURL($page_title);

// check FrontPage
if ($page_title == KONA3_WIKI_FRONTPAGE) {
  // FrontPage
  $head_title = "{$wiki_title_}";
} else {
  // normal page
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
  <link rel="stylesheet" href="skin/def/kona3.css" type="text/css">
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
  <a href="http://kujirahand.com/konawiki/">Konawiki3 v.<?php echo KONA3_SYSTEM_VERSION ?></a>
</div>
<!-- footer.end -->

</body>
</html>
