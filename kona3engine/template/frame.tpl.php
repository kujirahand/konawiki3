<?php /* template */
global $kona3conf;
$title = $kona3conf["title"];
$tlink = kona3getPageURL($kona3conf["FrontPage"]);
$title_link = "<a href='$tlink'>".kona3text2html($title)."</a>";

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title><?php echo $title ?></title>
  <link rel="stylesheet" href="skin/def/kona3.css" type="text/css">
 </head>
<body>

<!-- header.begin -->
<div id="wikiheader">
  <div id="wikititle"><?php echo $title_link  ?></div>
</div>
<!-- header.end -->

<div id="wikicontent">
  <div id="wikibody">
  <?php echo $frame_body ?>
  </div>
  <nav id="wikimenu">
  <?php echo $menu ?>
  </nav>
</div>

<!-- footer.begin -->
<div id="wikifooter">
  <a href="">Konawiki3</a>
</div>
<!-- footer.end -->

</body>
</html>
