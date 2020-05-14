<?php

function kona3plugins_nako3doc_execute() {
  global $kona3conf;
  $page = $kona3conf['page'];
  // check page
  $db = nako3doc_getDB();
  $stmt = $db->prepare('SELECT * FROM commands WHERE pagename=?');
  $stmt->execute([$page]);
  $r = $stmt->fetch(PDO::FETCH_ASSOC);
  if (empty($r)) {
    return "";
  }
  $plugin = $r['plugin'];
  $genre = $r['genre'];
  $type = $r['type'];
  $name = $r['name'];
  $args = $r['args'];
  $desc = $r['desc'];
  $kana = $r['kana'];
  $ctime = $r['ctime'];
  $mtime = $r['mtime'];

  $wiki =<<<EOS
* {$name} ($kana)

{{{#csv(flag=||)
カテゴリ || [[$plugin]] > [[$genre:$plugin/$genre]]
種類 || $type
引数 || $args
説明 || $desc
}}}
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

function nako3doc_getDB() {
  global $kona3conf;
  global $nako3doc_db;
  if (isset($nako3doc_db)) {
    return $nako3doc_db;
  }
  $dbfile = $kona3conf['path.data'].'/nako3commands.db';
  $nako3doc_db = new PDO("sqlite:$dbfile");
  return $nako3doc_db;
}


