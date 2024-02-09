<?php
require __DIR__.'/show.inc.php';

function kona3_action_pdf() {
    // インストールチェック
    $lib = dirname(__DIR__).'/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (!file_exists($lib)) {
      echo "Please install TCPDF lib. ".
        "<a href='http://kujirahand.com/konawiki3/go.php?10'>(more)</a>";
      exit;
    }
    include_once $lib;
    // チェック
    global $kona3conf;
    $page = $kona3conf["page"];
    $page_h = htmlspecialchars($page);
  
    // check login
    kona3show_check_private($page);
  
    // detect file type
    $wiki_live = kona3show_detect_file($page, $fname, $ext);
    if (!$wiki_live) {
        echo "wiki file not found"; exit;
    }

    // TODO: PDFの出力キャッシュを作る?
    // $mtime = filemtime($fname);
    // $pdf_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $page_h)."__{$mtime}.pdf";
    // $pdf_path = KONA3_DIR_CACHE.'/'.$pdf_name;

    // body
    if ($wiki_live) {
      $txt = @file_get_contents($fname);
      $kona3conf['data_filename'] = $fname;
    } else {
      $txt = kona3show_file_not_found($page, $ext);
    }
  
    // convert
    $ext = strtolower($ext);
    if ($ext == ".txt") {
      $page_body = konawiki_parser_convert($txt);
    } else if ($ext == ".md") {
      $page_body = kona3show_markdown_convert($txt);
    } else {
      kona3error($page, "Sorry, System Error."); exit;
    }
  
    // header and footer
    $allpage_header = '';
    $allpage_footer = '';
    if (!empty($kona3conf['allpage_header'])) {
      $allpage_header =
        "<div class='allpage_header'>". 
        konawiki_parser_convert(
          $kona3conf['allpage_header']).
        "</div><!-- end of .allpage_hader -->\n";
    }
    if (!empty($kona3conf['allpage_footer'])) {
      $allpage_footer = 
        "<div class='allpage_footer'>".
        konawiki_parser_convert(
          $kona3conf['allpage_footer']).
        "</div><!-- end of .allpage_footer -->\n";
    }
    $style =<<<EOS
<style>
h1 {
  background-color: blue;
  color: white;
}
h2 {
  background-color: blue;
  color: white;
}
h3 {
  background-color: blue;
  color: white;
}
.resmark {
  color: blue;
  background-color: yellow;
  margin-left: 1em;
}
</style>
EOS;
    // ---
    //$page_body = preg_replace(
    // '#<div class=\'resmark\'>(.+?)</div>#g',
    // '<blockquote>&gt; $1</blockquote>', $page_body);
    $page_body = str_replace("<div class='resmark'>",'<div class="resmark">', $page_body);
    // ---
    $page_body = 
      $style.
      $allpage_header.
      $page_body.
      $allpage_footer;
    
    // ==================
    // Output PDF
    // ==================
    // 用紙の方向、用紙サイズを指定する
    $tcpdf = new TCPDF('H', "mm",'A5');
    $tcpdf->setPrintHeader(false);
    $tcpdf->setPrintFooter(false);
    $tcpdf->AddPage();

    // === カスタマイズできるように考慮する ===
    // ユニコードフォントがあるかチェック
    $fontfile = '';
    $font_dir = dirname(__DIR__).'/vendor/fonts';
    $fonts = getFileList($font_dir, '/\.ttf$/');
    if (!$fonts) {
      // フォントがなければ適当にダウンロードする
      // ZIPファイルをダウンロード
      $font_url = "https://moji.or.jp/wp-content/ipafont/IPAexfont/ipaexg00401.zip";
      $font_zip = $font_dir. '/ipaexg00401.zip';
      if (!file_exists($font_zip)) {
        // download
        file_put_contents($font_zip, file_get_contents($font_url));
      }
      // 解凍
      $zip = new ZipArchive;
      if ($zip->open($font_zip) === TRUE) {
        $zip->extractTo($font_dir);
        $zip->close();
      }
      $fonts = getFileList($font_dir, '/\.ttf$/');
    }
    foreach ($fonts as $f) {
      if (file_exists($f)) {
        $fontfile = $f;
      }
    }
    if ($fontfile) {
      $font = new TCPDF_FONTS();
      $fontX = $font->addTTFfont($fontfile);  
      $tcpdf->SetFont($fontX , '', 16);
    }
    // 不要なタグを削除
    $page_body = preg_replace("/<strong.*?>/", "**", $page_body);
    $page_body = preg_replace("/<\/strong>/", "**", $page_body);
    //
    $tcpdf->WriteHTML($page_body);
    // 出力用バッファの内容を消去
    ob_end_clean();
    // $tcpdf->Output($page.".pdf", "D"); // ダウンロード
    $tcpdf->Output($page.".pdf", "I"); // ブラウザ
}

// 再帰的にファイルを検索
function getFileList($dir, $pattern = "/^.+$/") {
  $files = scandir($dir);
  $files = array_filter($files, function ($file) {
    return !in_array($file, array('.', '..'));
  });
  $list = array();
  foreach ($files as $file) {
    $fullpath = rtrim($dir, '/') . '/' . $file;
    if (is_file($fullpath)) {
      if (preg_match($pattern, $file)) {
        $list[] = $fullpath;
      }
    }
    if (is_dir($fullpath)) {
      $list = array_merge($list, getFileList($fullpath));
    }
  }
  return $list;
}
