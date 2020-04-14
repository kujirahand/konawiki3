<?php
// allpage.footer
if (!empty($kona3conf['allpage.footer'])) {
  $allpage = konawiki_parser_convert($kona3conf['allpage.footer']);
}

// ctrl_menu
$ctrl_menu = kona3getCtrlMenu("bar");
if (KONA3_PARTS_COUNTCHAR) {
  $cnt_txt = number_format($cnt_txt);
  $ctrl_menu .= " - {$cnt_txt}ch";
}

// KONA3_SHOW_DATA_DIR
$show_data_dir = "";
if (kona3isLogin() && KONA3_SHOW_DATA_DIR) {
  $show_data_dir = $page_file;
}
?>

<div style="clear:both;"></div>

<!-- footer.begin -->
<div id="wikifooter">
  <div id="allpage_footer">
    <?php echo $allpage; ?>
  </div><!-- end of #allpage_footer -->

  <div style="clear:both;"></div>

  <div class="footer_menu">
    <nav><?php echo $ctrl_menu ?></nav>
  </div>

  <?php if ($show_data_dir != ''): ?>
  <div id="kona3_show_data_dir">
    <input id="show_data_dir" type="text" 
     value="<?php echo $show_data_dir; ?>" readonly>
  </div>
  <?php endif; ?>

  <div class="info"><?php echo kona3getSysInfo() ?></div>
</div>
<!-- footer.end -->

</body>
</html>
