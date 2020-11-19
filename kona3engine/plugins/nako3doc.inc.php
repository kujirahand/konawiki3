<?php

function kona3plugins_nako3doc_execute($parg) {
  global $kona3conf;
  $page = $kona3conf['page'];
  // check args
  $pa = array_shift($parg);

  if ($pa == 'list-func') {
    return nako3doc_list_func();
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
    $extra_plugin = "> (拡張プラグイン) 以下の宣言が必要:\n" .
      '{{{'."\n!『{$plugin}』を取り込む\n".'}}}'."\n";
  }
  $wiki =<<<EOS
* {$name} ($kana)

{{{#csv(flag=||)
カテゴリ || [[$plugin]] > [[$genre:$plugin/$genre]]
種類 || $type
引数 || $args
説明 || $desc
}}}
{$extra_plugin}
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
  $wiki = "* [[$plug]]/[[$genre:$page]]\n";
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
    } else {
      $wiki .= "- [[$name:$pagename]] -- $desc\n";
    }
  }
  return konawiki_parser_convert($wiki);
}

function nako3doc_list_func() {
  $ra = nako3doc_run(
    "SELECT * FROM commands ".
    "ORDER BY plugin ASC, genre ASC",
    []);
  if (!$ra) {
    return "[ERROR]";
  }
  $wiki = "* [[命令一覧]] / [[機能順:命令一覧/機能順]]\n";

  $pluginLast = '';
  $genreLast = '';
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
    if ($plugin != $pluginLast) {
      $pluginLast = $plugin;
      $wiki .= "** [[$plugin]]\n";
    }
    if ($genre != $genreLast) {
      $genreLast = $genre;
      $wiki .= "*** [[$plugin]] / [[$genre:$plugin/$genre]]\n";
    }
    if ($type == '定数') {
      $wiki .= "- [[$name:$pagename]]\n";
    } else {
      $wiki .= "- [[$name:$pagename]]\n";
    }
  }
  return konawiki_parser_convert($wiki);
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
  return konawiki_parser_convert($wiki);
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
    $wiki .= "*** 🔌[[$plugin]]\n";
    $wiki .= "#html(<blockquote>)";
    $wiki .= "#include($plugin)\n";
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
      $wiki .= "*** [[$plugin]] / [[$genre:$plugin/$genre]]\n";
    }
    if ($type == '定数') {
      $wiki .= "- [[$name:$pagename]]\n";
    } else {
      $wiki .= "- [[($args) $name:$pagename]]\n";
    }
  }
  return konawiki_parser_convert($wiki);
}


function nako3doc_getDB() {
  global $kona3conf;
  global $nako3doc_db;
  if (isset($nako3doc_db)) {
    return $nako3doc_db;
  }
  $dbfile = KONA3_DIR_DATA.'/nako3commands.db';
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




