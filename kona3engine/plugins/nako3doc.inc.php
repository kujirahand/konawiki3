<?php

function kona3plugins_nako3doc_execute($parg) {
  global $kona3conf;
  $page = $kona3conf['page'];
  // check args
  $pa = array_shift($parg);

  if ($pa == 'list-func') {
    $type = array_shift($parg);
    return nako3doc_list_func($type);
  }
  if ($pa == 'list-kana') {
    return nako3doc_list_kana('kana');
  }
  if ($pa == 'list-yomi') {
    return nako3doc_list_kana('yomi');
  }
  if ($pa == 'list-plugins' || $pa == 'plugins') {
    return nako3doc_list_plugins();
  }
  // check page
  $ra = nako3doc_run('SELECT * FROM commands WHERE pagename=?',
    [$page]);
  if (!$ra) {
    return nako3doc_checkGenre($page);
  }
  $r = $ra[0];
  // --- page ---
  $plugin = $r['plugin'];
  $genre = $r['genre'];
  $type = $r['type'];
  $name = $r['name'];
  $args = $r['args'];
  $desc = $r['desc'];
  $kana = $r['kana'];
  $ctime = $r['ctime'];
  $mtime = $r['mtime'];
  // 拡張かどうか
  $extra_plugin = "";
  if ($plugin == 'plugin_system' || $pligin == 'plugin_browser' || $plugin == 'plugin_node' || $plugin == 'plugin_turtle') {
  } else {
    $extra_plugin = "{{{\n".
      "# [拡張プラグイン] 以下の宣言が必要:\n".
      "!『{$plugin}』を取り込む\n".
      "}}}\n";
  }
  // search in nako3storage
  $nameenc = urlencode($name);
  $search_url = "https://nadesi.com/v3/storage/index.php?search_word={$nameenc}&action=search&target=program";
  $search_name = "[[→『{$name}』を貯蔵庫で検索:$search_url]]";
  $wiki =<<<EOS
* {$name} ($kana)

{{{#csv(flag=||)
カテゴリ || [[$plugin]] > [[$genre:$plugin/$genre]]
種類 || $type
引数 || $args
説明 || $desc
}}}
{$extra_plugin}
{$search_name}
EOS;
  if ($type == '定数') {
    $wiki =<<<EOS
* {$name} ($kana)

{{{#csv(flag=||)
カテゴリ || [[$plugin]] > [[$genre:$plugin/$genre]]
種類   || $type
初期値 || $desc
}}}
EOS;
  }
  $s = konawiki_parser_convert($wiki);
  return $s;  
}

function nako3doc_checkGenre($page) {
  $a = explode('/', $page, 2);
  $plug = $a[0];
  $genre = $a[1];
  $ra = nako3doc_run(
    "SELECT * FROM commands ".
    "WHERE plugin=? AND genre=?",
    [$plug, $genre]);
  if (!$ra) {
    return nako3doc_checkPlugin($page);
  }
  $wiki = "* 🔌 [[$plug]] / [[$genre:$page]]\n";
  $wiki .= 
    "#html(<blockquote style='background-color:#fff0f0;'>);\n".
    "#page($plug)\n".
    "#html(</blockquote>);\n".
    "** 🌴 [[$genre:$page]]\n";
  foreach ($ra as $r) {
    $plugin = $r['plugin'];
    $genre = $r['genre'];
    $pagename = $r['pagename'];
    $type = $r['type'];
    $name = $r['name'];
    $args = $r['args'];
    $desc = $r['desc'];
    $kana = $r['kana'];
    $ctime = $r['ctime'];
    $mtime = $r['mtime'];
    if ($type == '定数') {
      $wiki .= "- [[$name:$pagename]]\n";
      $wiki .= "-- 定数\n";
    } else {
      $arg_desc = "";
      if ($args) {$arg_desc="($args)";}
      $wiki .= "- [[$name:$pagename]] $arg_desc\n";
      $wiki .= "-- $desc\n";
    }
  }
  return konawiki_parser_convert($wiki);
}

function nako3doc_list_func($pagetype) {
  if (!$pagetype) { $pagetype = ''; }
  $conf_use_cache = isset($_GET['cache']) ? (intval($_GET['cache']) == 1) : TRUE;
  if ($conf_use_cache) {
    // check cache
    $use_cache = FALSE;
    $cache_dir = KONA3_DIR_CACHE;
    $cache_file = $cache_dir."/nako3doc.cache.list_func_$nakotype.html";
    if (file_exists($cache_file)) {
      $cache_time = filemtime($cache_file);
      $db_time = nako3doc_getDBTime();
      if ($db_time < $cache_time) { $use_cache = TRUE; }
    }
    // use cache
    if ($use_cache) {
      $html = file_get_contents($cache_file);
      if (kona3isLogin()) {
        $page_nocache = kona3getPageURL('', '', '', 'cache=0');
        $html = "<div class='block'>[CACHE mode : <a href='$page_nocache'>nocache</a>]</div>".$html;
      }
      return $html;
    }
  }

  if (!$pagetype) {
    $ra = nako3doc_run(
      "SELECT * FROM commands ".
      "ORDER BY plugin ASC",
      []);
  } else {
    $ra = nako3doc_run(
      "SELECT * FROM commands WHERE nakotype LIKE ?".
      "ORDER BY plugin ASC",
      ["%{$nakotype}%"]);
  }
  if (!$ra) {
    return "[ERROR]";
  }

  $plugins = [];
  $pluginLast = '';
  $genreLast = '';
  $pluginDesc = [];
  $cmd = [];
  foreach ($ra as $r) {
    $plugin = $r['plugin'];
    $nakotype = $r['nakotype'];
    $genre = $r['genre'];
    $pagename = $r['pagename'];
    $type = $r['type'];
    $name = $r['name'];
    $args = $r['args'];
    $desc = $r['desc'];
    $kana = $r['kana'];
    $ctime = $r['ctime'];
    $mtime = $r['mtime'];
    
    // plugin
    if (!isset($cmd[$plugin])) {
      $cmd[$plugin]  = [];
      $pluginDesc[$plugin] = $nakotype;
    }
    // genre
    if (!isset($cmd[$plugin][$genre])) {
      $cmd[$plugin][$genre] = [];
    }
    // command
    $cmd[$plugin][$genre][] = "[[$name:$pagename]]";
  }
  // プラグイン順に出力
  $fn = function ($cmd, $plugin) use($pluginDesc) {
    $type = $pluginDesc[$plugin];
    $type = preg_replace('#([a-z]+)#', '[[$1]]', $type);
    $w = "** 🔌 [[$plugin]]\n[[$plugin]]は{$type}で使えます。\n";
    $t = "| 🔌 [[$plugin]] ($type) | ";
    foreach ($cmd[$plugin] as $genre => $list) {
      $w .= "*** 🌴 [[$genre:$plugin/$genre]]:\n";
      $w .= '{{{#column'."\n";
      $w .= implode(' / ', $list)."\n";
      $w .= '}}}'."\n\n";
      $w .= "\n\n";
      $t .= "([[$genre:$plugin/$genre]]) ";
    }
    $t .= "\n";
    return [$w, $t];
  };
  // 出力
  $index = '';
  $wiki = '';
  list($w, $t) = $fn($cmd, 'plugin_system');
  $wiki .= $w; $index .= $t;
  if (!$pagetype || $pagetype == 'wnako') {
    list($w, $t) = $fn($cmd, 'plugin_browser');
    $wiki .= $w; $index .= $t;
    list($w, $t) = $fn($cmd, 'plugin_turtle');
    $wiki .= $w; $index .= $t;
  }
  foreach ($cmd as $plug => $v) {
    if ($plug == 'plugin_system' || 
        $plug == 'plugin_browser' ||
        $plug == 'plugin_turtle') {
      continue;
    }
    list($w, $t) = $fn($cmd, $plug);
    $wiki .= $w; $index .= $t;
  }
  $wiki = 
    $index."\n\n".
    $wiki."\n\n".
    "";

  $wiki_html = konawiki_parser_convert($wiki);
  $widget = <<<EOS
<iframe height="400" width="100%"
  src="https://nadesi.com/v3/storage/widget.php?453&run=1&mute_name=1"
  style="width:99%; border: none;"></iframe>
EOS;

  // save cache
  if ($conf_use_cache && $cache_dir != '') {
    @file_put_contents($cache_file, $wiki_html);
  }
  return $wiki_html;
}

function nako3doc_list_kana($mode) {
  $ra = nako3doc_run(
    "SELECT * FROM commands ".
    "ORDER BY kana ASC",
    []);
  if (!$ra) {
    return "[ERROR]";
  }
  $wiki = "* [[命令一覧]] / [[カナ順:命令一覧/カナ順]]\n";

  // 同名の命令があればプラグインを明示
  for ($i = 0; $i < count($ra) - 1; $i++) {
    $name1 = $ra[$i+0]['name'];
    $name2 = $ra[$i+1]['name'];
    if ($name1 != $name2) {
      if (isset($ra[$i]['name_show'])) continue;
      $ra[$i]['name_show'] = $ra[$i]['name'];
      $ra[$i]['kana_show'] = $ra[$i]['kana'];
      continue;
    }
    $plugin1 = $ra[$i]['plugin'];
    $plugin2 = $ra[$i+1]['plugin'];
    $kana1 = $ra[$i]['kana'];
    $ra[$i+0]['name_show'] = "$name1($plugin1)";
    $ra[$i+1]['name_show'] = "$name1($plugin2)";
    $ra[$i+0]['kana_show'] = "$kana1($plugin1)";
    $ra[$i+1]['kana_show'] = "$kana1($plugin2)";
  }

  $ch = $chLast = '';
  foreach ($ra as $r) {
    $plugin = $r['plugin'];
    $genre = $r['genre'];
    $pagename = $r['pagename'];
    $type = $r['type'];
    $name = $r['name'];
    $name_show = $r['name_show'];
    $kana_show = $r['kana_show'];
    $args = $r['args'];
    $desc = $r['desc'];
    $kana = $r['kana'];
    $ctime = $r['ctime'];
    $mtime = $r['mtime'];
    $ch = mb_substr($kana, 0, 1);
    if ($ch != $chLast) {
      $wiki .= "** $ch\n";
      $chLast = $ch;
    }
    if ($mode == 'kana') {
      if ($type == '定数') {
        $wiki .= "- [[$name_show:$pagename]]\n";
      } else {
        $wiki .= "- [[$name_show:$pagename]]\n";
      }
    } else {
      if ($type == '定数') {
        $wiki .= "- [[$kana_show - $name:$pagename]]\n";
      } else {
        $wiki .= "- [[$kana_show - $name:$pagename]]\n";
      }
    }
  }
  $wiki_html = konawiki_parser_convert($wiki);
  if ($mode == 'yomi') {
    return $wiki_html;
  }
  return <<<EOS
<iframe height="450" width="100%"
  src="https://nadesi.com/v3/storage/widget.php?451&run=1&mute_name=1"
  style="width:99%; border: none;"></iframe>
{$wiki_html}
EOS;
}

function nako3doc_list_plugins() {
  $ra = nako3doc_run(
    "SELECT DISTINCT plugin FROM commands ",
    []);
  if (!$ra) {
    return "[ERROR]";
  }
  $wiki = "* [[命令一覧]] > プラグイン一覧\n";
  foreach ($ra as $r) {
    $plugin = $r['plugin'];
    $a = nako3doc_run("SELECT * FROM commands WHERE plugin=? LIMIT 1", [$plugin]);
    $type = $a[0]['nakotype'];
    $type = preg_replace('/([a-z]+)/', '[[\1]]', $type);
    $wiki .= "*** 🔌[[$plugin]]\n";
    $wiki .= "#html(<blockquote>)";
    $wiki .= "#include($plugin)\n";
    $wiki .= "($type)\n";
    $wiki .= "#html(</blockquote>)\n";
    $wiki .= "\n";
  }
  return konawiki_parser_convert($wiki);
}

function nako3doc_checkPlugin($page) {
  $ra = nako3doc_run(
    "SELECT * FROM commands ".
    "WHERE plugin=? ".
    "ORDER BY genre ASC,command_id ASC",
    [$page]);
  if (!$ra) {
    return "";
  }
  $wiki = "* [[プラグイン一覧]] > [[$page]]\n";
  $wiki .= "#html(<blockquote>)\n";
  $wiki .= "#include($page)\n";
  $wiki .= "#html(</blockquote>)\n";
  $genreLast = "";
  foreach ($ra as $r) {
    $plugin = $r['plugin'];
    $genre = $r['genre'];
    $pagename = $r['pagename'];
    $type = $r['type'];
    $name = $r['name'];
    $name_show = $r['name_show'];
    $args = $r['args'];
    $desc = $r['desc'];
    $kana = $r['kana'];
    if ($genreLast != $genre) {
      $genreLast = $genre;
      $wiki .= "*** 🌴 [[$genre:$plugin/$genre]]\n";
    }
    if ($type == '定数') {
      $wiki .= "- [[$name:$pagename]] \n";
    } else {
      if ($args) { $args = "($args)"; }
      $wiki .= "- [[$name:$pagename]] $args\n";
    }
  }
  return konawiki_parser_convert($wiki);
}

function nako3doc_getDBFile() {
  $dbfile = KONA3_DIR_DATA.'/nako3commands.db';
  return $dbfile;
}

function nako3doc_getDBTime() {
  return filemtime(nako3doc_getDBFile()); 
}

function nako3doc_getDB() {
  global $kona3conf;
  global $nako3doc_db;
  if (isset($nako3doc_db)) {
    return $nako3doc_db;
  }
  $dbfile = nako3doc_getDBFile();
  $nako3doc_db = new PDO("sqlite:$dbfile");
  return $nako3doc_db;
}

function nako3doc_run($sql, $params = []) {
  $db = nako3doc_getDB();
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $r = $stmt->fetchAll(PDO::FETCH_BOTH);
  if (empty($r)) {
    return [];
  }
  return $r;
}

// action
function kona3plugins_nako3doc_action() {
  $q = isset($_GET['q']) ? $_GET['q'] : '';
  $rows = nako3doc_run(
    'SELECT * FROM commands WHERE name=? OR pagename=?',
    [$q, $q]);
  $html = '<ul>';
  if (!$rows) {
    $html .= '<li>見つかりません</li>';
  } else {
    foreach ($rows as $r) {
      $pagename = $r['pagename'];
      $url = kona3getPageURL($pagename);
      $pagename_html = htmlspecialchars($pagename);
      $html .= "<li><a href='$url'>$pagename_html</a></li>";
    }
  }
  $html .= '</ul>';
  kona3showMessage('#nako3doc', $html, 'white.html');
}






