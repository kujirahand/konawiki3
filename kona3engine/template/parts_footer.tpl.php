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
<!-- footer.begin -->
<div id="wikifooter">
  <div id="allpage_footer">
    <?php echo $allpage; ?>
  </div><!-- end of #allpage_footer -->
  <div class="footer_menu">
    <nav><?php echo $ctrl_menu ?></nav>
  </div>
  <div id="kona3_show_data_dir">
    <input type="text" value="<?php echo $show_data_dir; ?>" readonly>
  </div>
  <div class="info"><?php echo kona3getSysInfo() ?></div>
</div>
<!-- footer.end -->

</body>
</html>
