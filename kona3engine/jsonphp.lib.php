<?php
// data format loader and saver

define('JSONPHP_HEAD', "<?php \$jsonphp___=<<<'_____END_OF_JSONPHP__'\n");
define('JSONPHP_FOOT', "\n_____END_OF_JSONPHP__;exit;\n");
define('JSONPHP_ALGO', 'aes-256-ctr');
define('JSONPHP_ENC_HEAD', '::aes256::');

function jsonphp_load($file, $def = null, $autoUpdate = false) {
  if (!file_exists($file)) {
    return $def;
  }
  $txt = trim(file_get_contents($file));
  // chomp head
  $head = substr($txt, 0, strlen(JSONPHP_HEAD));
  if ($head != JSONPHP_HEAD) { return $def; }
  $txt = substr($txt, strlen(JSONPHP_HEAD));
  $txt = substr($txt, 0, strlen($txt) - strlen(JSONPHP_FOOT) + 1);
  $obj = json_decode($txt, TRUE);
  if ($obj === NULL) {
    return $def;
  }
  $iv = jsonphp_getIV();
  $enc_key = base64_decode(isset($obj['enc:key']) ? $obj['enc:key'] : '');
  foreach ($obj as $_key => &$val) {
    if (is_string($val) && $val != '') {
      $val = jsonphp_dec($val, $enc_key, $iv);
    }
    if (is_array($val)) {
      foreach ($val as $_key2 => &$val2) {
        if (is_string($val2)) {
          $val2 = jsonphp_dec($val2, $enc_key, $iv);
        }
      }
    }
  }
  // 定期的に暗号化をアップデートする
  if ($autoUpdate) {
    $mtime = filemtime($file);
    $expire = $mtime + 60*60*24*30; // 30days
    if (time() > $expire) {
      jsonphp_save($file, $obj);
    }
  }
  return $obj;
}

function jsonphp_enc($val, $enc_key, $iv) {
  $val_enc = openssl_encrypt($val, JSONPHP_ALGO, $enc_key, 0, $iv);
  return JSONPHP_ENC_HEAD . base64_encode($val_enc);
}
function jsonphp_dec($val, $enc_key, $iv) {
  $pat = '/^'.preg_quote(JSONPHP_ENC_HEAD, '/').'(.*)$/';
  if (preg_match($pat, $val, $m)) {
    return openssl_decrypt(base64_decode($m[1]), JSONPHP_ALGO, $enc_key, 0, $iv);
  }
  return $val;
}

function jsonphp_save($file, $value) {
  // encrypt value
  $enc_key = random_bytes(16);
  $iv = jsonphp_getIV();
  foreach ($value as $key => &$val) {
    if (is_string($val) && $val != '') {
      $val = jsonphp_enc($val, $enc_key, $iv);
    }
    if (is_array($val)) {
      foreach ($val as $_key2 => &$val2) {
        if (is_string($val2) && $val2 != '') {
          $val2 = jsonphp_enc($val2, $enc_key, $iv);
        }
      }
    }
  }
  $value['enc:key'] = base64_encode($enc_key);
  $json = json_encode($value,
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  file_put_contents($file,
    JSONPHP_HEAD.$json.JSONPHP_FOOT);
}

function jsonphp_getIV() {
  global $kona3conf;
  if (isset($kona3conf['kona3::jsonphp_iv'])) {
    return $kona3conf['kona3::jsonphp_iv'];
  }
  // check path
  $bin_path = dirname(__DIR__) . '/kona3json_iv.bin';
  if (defined("KONA3_DIR_PRIVATE")) {
    $bin_path = KONA3_DIR_PRIVATE . '/kona3json_iv.bin';
  }
  if (!file_exists($bin_path)) {
    file_put_contents($bin_path, random_bytes(16));
  }
  $bin = file_get_contents($bin_path);
  $kona3conf['kona3::jsonphp_iv'] = $bin;
  return $bin;
}
