<?php

/** 複数WIKIファイルを取り込んで表示する
 * - [書式] #include(pagename_list)
 * - [引数]
 * -- pagename_list ... inlucde other pages
 * - [利用例] {{{#include flie1 \n file2 \n file3 ... }}}
 *
 * 各行のファイルは、次の順で存在を確認して取り込む (#387):
 *  1. dataフォルダ以下からのパス (名前そのまま、拡張子付きならそのファイル)
 *  2. .md/.txt を付与したパス (メタデータのmode → def_text_ext の優先順)
 *  3. そのWikiファイル (現在ページ、入れ子の場合は取り込み中のファイル)
 *     からの相対パス (1→2 と同じ順で確認する。"./" 始まりは相対指定、
 *     "/" 始まりは data フォルダ以下からのパスとして扱う)
 */

include_once dirname(dirname(__FILE__)) . '/kona3parser.inc.php';
include_once dirname(dirname(__FILE__)) . '/kona3parser_md.inc.php';

function kona3plugins_include_execute($args) {
  $files_str = implode("\n", $args);
  $files = explode("\n", $files_str);
  $txtlist = array();

  foreach($files as $name) {
    $name = trim($name);
    if ($name == "") continue;
    $html = "";
    $b = kona3plugins_include_file($name, $html);
    if ($b) {
      $txtlist[] = $html;
    } else {
      $name_esc = htmlspecialchars($name);
      $txtlist[] = "<div class='kona3plugins_include_error'>Error: $name_esc is not found.</div>";
    }
  }

  return implode("\n", $txtlist);
}

/** #include / #file の入れ子の深さ上限 (自己・相互参照による無限再帰を防ぐ, #324) */
if (!defined('KONA3_INCLUDE_MAX_DEPTH')) {
  define('KONA3_INCLUDE_MAX_DEPTH', 5);
}

/** $fname の実パスが data ディレクトリ配下にある通常ファイルか (シンボリックリンク経由の外部読み取りを防ぐ, #324) */
function kona3plugins_include_inDataDir($fname) {
  $real = realpath($fname);
  $data = realpath(KONA3_DIR_DATA);
  if ($real === false || $data === false) return false;
  if (!is_file($real)) return false;
  $data = rtrim($data, '/\\');
  return strpos($real, $data . DIRECTORY_SEPARATOR) === 0;
}

/** "." と ".." の要素を解決した data フォルダ相対パスを返す (data の外へ出る場合は false) */
function kona3plugins_include_normalize_path($path) {
  $out = array();
  foreach (explode('/', $path) as $seg) {
    if ($seg === '' || $seg === '.') continue;
    if ($seg === '..') {
      if (count($out) === 0) return false;
      array_pop($out);
      continue;
    }
    $out[] = $seg;
  }
  return implode('/', $out);
}

/** 相対パスの基準フォルダを data フォルダ相対で返す (直下なら ".")。
 * 入れ子 include の実行中は取り込み中のファイル、それ以外は現在ページが基準。 */
function kona3plugins_include_get_relative_dir() {
  global $kona3_include_file_stack, $kona3conf;

  if (!empty($kona3_include_file_stack)) {
    $fname = end($kona3_include_file_stack);
    $data = realpath(KONA3_DIR_DATA);
    $real = realpath($fname);
    if ($data !== false && $real !== false) {
      $data = rtrim($data, '/\\') . DIRECTORY_SEPARATOR;
      if (strpos($real, $data) === 0) {
        return dirname(substr($real, strlen($data)));
      }
    }
  }

  $page = isset($kona3conf['page']) ? $kona3conf['page'] : '';
  if ($page === '' || $page === null) return '.';
  return dirname(kona3stripEditableExt($page));
}

/** data フォルダ相対の $name について、名前そのまま→.md/.txt 付与の順で実ファイルを探す */
function kona3plugins_include_find_wiki_file($name) {
  if ($name === '' || $name === false) return false;

  // 1. 名前そのままのパス (拡張子付きで指定された場合はそのファイル自体)
  $fname = kona3getWikiFile($name, false);
  if (file_exists($fname) && kona3plugins_include_inDataDir($fname)) {
    return $fname;
  }

  // 拡張子 (.md/.txt) 付きで指定され、そのファイルが無ければ他の拡張子へは読み替えない
  if (kona3stripEditableExt($name) !== $name) {
    return false;
  }

  // 2. .md/.txt を付与したパス (メタデータのmode → def_text_ext の優先順で解決)
  $fname = koan3getWikiFileText($name);
  if (file_exists($fname) && kona3plugins_include_inDataDir($fname)) {
    return $fname;
  }
  return false;
}

/** #include の1行分について、data 以下→相対パスの順で取り込むファイルを解決する (#387) */
function kona3plugins_include_find_file($name) {
  $name = str_replace('\\', '/', trim($name));
  if ($name === '') return false;

  $candidates = array();
  if (strpos($name, '/') === 0) {
    // "/" 始まりは data フォルダ以下からのパス
    $c = kona3plugins_include_normalize_path(ltrim($name, '/'));
    if ($c !== false && $c !== '') $candidates[] = $c;
  } else if (strpos($name, './') === 0) {
    // "./" 始まりは、そのWikiファイルからの相対パス
    $dir = kona3plugins_include_get_relative_dir();
    $base = ($dir === '.' || $dir === '') ? substr($name, 2) : $dir . '/' . substr($name, 2);
    $c = kona3plugins_include_normalize_path($base);
    if ($c !== false && $c !== '') $candidates[] = $c;
  } else {
    // 1→2. data フォルダ以下からのパス
    $c = kona3plugins_include_normalize_path($name);
    if ($c !== false && $c !== '') $candidates[] = $c;
    // 3. そのWikiファイルからの相対パス
    $dir = kona3plugins_include_get_relative_dir();
    if ($dir !== '.' && $dir !== '' && $dir !== false) {
      $rel = kona3plugins_include_normalize_path($dir . '/' . $name);
      if ($rel !== false && $rel !== '' && !in_array($rel, $candidates, true)) {
        $candidates[] = $rel;
      }
    }
  }

  foreach ($candidates as $candidate) {
    $fname = kona3plugins_include_find_wiki_file($candidate);
    if ($fname !== false) return $fname;
  }
  return false;
}

function kona3plugins_include_file($name, &$html) {
  $fname = kona3plugins_include_find_file($name);
  if ($fname === false) {
    return false;
  }
  // 入れ子が深すぎる場合は変換しない (循環参照によるメモリ枯渇を防ぐ)
  global $kona3_include_depth;
  global $kona3_include_file_stack;
  if (!isset($kona3_include_depth)) $kona3_include_depth = 0;
  if (!isset($kona3_include_file_stack)) $kona3_include_file_stack = array();
  if ($kona3_include_depth >= KONA3_INCLUDE_MAX_DEPTH) {
    $html = "<div class='kona3plugins_include_error'>Error: include depth limit exceeded.</div>";
    return true;
  }
  $txt = file_get_contents($fname);
  $kona3_include_depth++;
  $kona3_include_file_stack[] = $fname;
  try {
    if (preg_match('/\.md$/', $fname)) {
      $html = kona3plugins_include_markdown_convert($txt);
    } else {
      $html = konawiki_parser_convert($txt);
    }
  } finally {
    array_pop($kona3_include_file_stack);
    $kona3_include_depth--;
  }
  return true;
}

function kona3plugins_include_markdown_convert($txt) {
  return kona3markdown_parser_convert($txt);
}
