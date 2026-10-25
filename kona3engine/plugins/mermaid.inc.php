<?php

/** mermaid notation
 * - [Usage] {{{#mermaid(caption,filename) ... }}}
 * caption --- キャプション
 * filename --- ブラウザで生成したSVGファイルを出力する
 */

function kona3plugins_mermaid_write_source($full_mmd, $text)
{
    $current_text = file_exists($full_mmd) ? file_get_contents($full_mmd) : false;
    if ($current_text === $text) {
        return false;
    }
    file_put_contents($full_mmd, $text);
    clearstatcache(true, $full_mmd);
    return true;
}

function kona3plugins_mermaid_needs_regenerate($full_mmd, $full_svg)
{
    clearstatcache(true, $full_mmd);
    clearstatcache(true, $full_svg);
    if (!file_exists($full_svg)) {
        return true;
    }
    if (!file_exists($full_mmd)) {
        return true;
    }
    return filemtime($full_mmd) > filemtime($full_svg);
}

function kona3plugins_mermaid_normalize_filename($filename)
{
    $filename = preg_replace("/^file=/", "", $filename);
    $filename = preg_replace("/\.svg$/", "", $filename);
    return preg_replace("/[^a-zA-Z0-9\-\_\.]/", "_", $filename);
}

function kona3plugins_mermaid_get_paths($page, $filename)
{
    $filename = kona3plugins_mermaid_normalize_filename($filename);
    if ($filename === "") {
        return false;
    }
    $pagePath = kona3getWikiFile($page);
    $pageDir = dirname($pagePath);
    return [
        'filename' => $filename,
        'full_svg' => $pageDir . "/" . $filename . ".svg",
        'full_mmd' => $pageDir . "/" . $filename . ".mmd",
    ];
}

function kona3plugins_mermaid_get_data_url($full_path)
{
    $url = str_replace(KONA3_DIR_DATA, '', $full_path);
    return "index.php?" . urlencode($url) . "&data";
}

// 検査用に属性値などを正規化する (#325)
// ブラウザはURL中のタブ/改行などの制御文字を取り除き、HTML実体参照を
// 解釈してからスキームを判定するため、検査側も同じ正規化をしないと
// "java<TAB>script:" や "&#106;avascript:" のような分割表記が素通りする。
function kona3plugins_mermaid_normalize_for_check($value)
{
    $v = (string)$value;
    // 実体参照を繰り返しデコードする (二重エンコード対策は1段で十分だが念のため最大3回)
    for ($i = 0; $i < 3; $i++) {
        $decoded = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === $v) break;
        $v = $decoded;
    }
    // 制御文字と空白を取り除き、小文字化する
    $v = preg_replace('/[\x00-\x20\x7F]/', '', $v);
    return strtolower($v === null ? '' : $v);
}

// href系の属性値として安全かどうか
// - フラグメント (#...) は許可
// - <a> のリンクだけ http/https/mailto を許可
// - それ以外 (javascript: / vbscript: / data: など) は拒否
function kona3plugins_mermaid_href_is_safe($value, $is_anchor)
{
    $n = kona3plugins_mermaid_normalize_for_check($value);
    if ($n === '') return true;
    if ($n[0] === '#') return true;
    if ($is_anchor && preg_match('/^(https?|mailto):/', $n)) return true;
    return false;
}

function kona3plugins_mermaid_validate_svg($svg)
{
    $svg = trim($svg);
    if ($svg === "" || strlen($svg) > 1024 * 1024 * 5) {
        return false;
    }
    $svg_without_xml = preg_replace('/^\s*<\?xml[^>]*\?>\s*/i', '', $svg);
    if (!preg_match('/^<svg[\s>]/i', $svg_without_xml)) {
        return false;
    }
    // --- 従来のブラックリスト (後方互換の第一防衛線) ---
    $danger_patterns = [
        '/<script\b/i',
        '/<foreignObject\b/i',
        '/\son[a-z]+\s*=/i',
        '/javascript\s*:/i',
    ];
    foreach ($danger_patterns as $pattern) {
        if (preg_match($pattern, $svg)) {
            return false;
        }
    }
    // --- 構造的な検証 (#325) ---
    // ブラックリストだけでは、data: URIで入れ子にしたSVG画像 (<image>) や
    // タブで分割した javascript: href が素通りした。XMLとして走査し、
    // 要素・属性ごとに検証する。
    if (!class_exists('DOMDocument')) {
        return false;
    }
    // DOCTYPE (実体宣言) を持つSVGは受け付けない
    if (preg_match('/<!DOCTYPE/i', $svg)) {
        return false;
    }
    $prev = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$loaded || $dom->documentElement === null) {
        return false;
    }
    if (strtolower($dom->documentElement->localName) !== 'svg') {
        return false;
    }
    // 許可しない要素: スクリプト実行・外部埋め込み・入れ子画像など。
    // 特に <image> は data:image/svg+xml の入れ子でスクリプトを持ち込める。
    $blocked_elements = [
        'script', 'foreignobject', 'image', 'iframe', 'frame', 'frameset',
        'object', 'embed', 'form', 'input', 'button', 'select', 'textarea',
        'video', 'audio', 'source', 'track', 'link', 'meta', 'base',
        'animate', 'animatetransform', 'animatemotion', 'set',
    ];
    $all = $dom->getElementsByTagName('*');
    foreach ($all as $el) {
        $tag = strtolower($el->localName);
        if (in_array($tag, $blocked_elements, true)) {
            return false;
        }
        // <style> の中身: 外部参照や危険なスキームを許可しない
        // (mermaid が使う url(#id) のフラグメント参照だけは許可する)
        if ($tag === 'style') {
            $css = kona3plugins_mermaid_normalize_for_check($el->textContent);
            if (strpos($css, 'javascript:') !== false
                || strpos($css, 'vbscript:') !== false
                || strpos($css, 'data:') !== false
                || strpos($css, 'expression(') !== false
                || strpos($css, '-moz-binding') !== false
                || strpos($css, '@import') !== false) {
                return false;
            }
            if (preg_match_all('/url\(([^)]*)\)/', $css, $mm)) {
                foreach ($mm[1] as $u) {
                    $u = trim($u, "\"'");
                    if ($u === '' || $u[0] !== '#') return false;
                }
            }
        }
        if (!$el->hasAttributes()) continue;
        for ($ai = 0; $ai < $el->attributes->length; $ai++) {
            $attr = $el->attributes->item($ai);
            $aname = strtolower($attr->localName);
            $avalue = $attr->value;
            // イベントハンドラ属性は名前の分割 (on&#x09;load等) も含めて拒否
            $aname_norm = kona3plugins_mermaid_normalize_for_check($aname);
            if (strpos($aname_norm, 'on') === 0) {
                return false;
            }
            // href / xlink:href の検証
            if ($aname_norm === 'href') {
                if (!kona3plugins_mermaid_href_is_safe($avalue, $tag === 'a')) {
                    return false;
                }
                continue;
            }
            // style 属性も <style> と同じ検証をする
            if ($aname_norm === 'style') {
                $css = kona3plugins_mermaid_normalize_for_check($avalue);
                if (strpos($css, 'javascript:') !== false
                    || strpos($css, 'vbscript:') !== false
                    || strpos($css, 'data:') !== false
                    || strpos($css, 'expression(') !== false) {
                    return false;
                }
                if (preg_match_all('/url\(([^)]*)\)/', $css, $mm)) {
                    foreach ($mm[1] as $u) {
                        $u = trim($u, "\"'");
                        if ($u === '' || $u[0] !== '#') return false;
                    }
                }
                continue;
            }
            // その他の属性値に危険なスキームが正規化後に現れたら拒否
            $nval = kona3plugins_mermaid_normalize_for_check($avalue);
            if (strpos($nval, 'javascript:') !== false
                || strpos($nval, 'vbscript:') !== false) {
                return false;
            }
        }
    }
    return true;
}

function kona3plugins_mermaid_save_svg($full_svg, $svg)
{
    if (!kona3isLogin()) {
        return [
            'ok' => false,
            'status' => 'forbidden',
            'message' => 'Login is required.',
        ];
    }
    $svg = trim($svg);
    if (!kona3plugins_mermaid_validate_svg($svg)) {
        return [
            'ok' => false,
            'status' => 'invalid_svg',
            'message' => 'Invalid SVG data.',
        ];
    }
    $dir = dirname($full_svg);
    if (!is_dir($dir)) {
        return [
            'ok' => false,
            'status' => 'directory_not_found',
            'message' => 'Output directory was not found.',
        ];
    }
    $ok = file_put_contents($full_svg, $svg) !== false;
    clearstatcache(true, $full_svg);
    return [
        'ok' => $ok,
        'status' => $ok ? 'saved' : 'save_failed',
        'message' => $ok ? 'SVG saved.' : 'Failed to save SVG.',
    ];
}

function kona3plugins_mermaid_execute($args)
{
    global $kona3conf;
    $caption = "";
    $filename = "";
    $text = "";
    if (count($args) >= 3) {
        $caption = array_shift($args);
        $filename = array_shift($args); 
        $text = array_shift($args);
    } else if (count($args) >= 2) {
        $caption = array_shift($args);
        $text = array_shift($args);
    } else {
        $text = array_shift($args);
    }
    $head = '';
    //
    $text_html = htmlspecialchars($text);
    $plugkey = "plugins.mermaid.init";
    if (empty($kona3conf[$plugkey])) {
        $kona3conf[$plugkey] = 1;
        $head = <<<EOS
<script type="module">
  import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11.15.0/dist/mermaid.esm.min.mjs';
  mermaid.initialize({
    startOnLoad: false,
    securityLevel: 'strict',
    htmlLabels: false,
    flowchart: { htmlLabels: false },
    class: { htmlLabels: false },
    state: { htmlLabels: false }
  });
  const sanitizeMermaidSvgForSave = (svg) => {
    const clone = svg.cloneNode(true);
    clone.querySelectorAll('foreignObject').forEach((foreignObject) => {
      const text = (foreignObject.textContent || '').replace(/\s+/g, ' ').trim();
      const width = Number.parseFloat(foreignObject.getAttribute('width') || '0');
      const height = Number.parseFloat(foreignObject.getAttribute('height') || '0');
      const svgText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      svgText.setAttribute('x', String(width / 2));
      svgText.setAttribute('y', String(height / 2));
      svgText.setAttribute('text-anchor', 'middle');
      svgText.setAttribute('dominant-baseline', 'middle');
      svgText.setAttribute('class', 'nodeLabel');
      const lines = text.split(/\\n/).filter((line) => line !== '');
      if (lines.length <= 1) {
        svgText.textContent = text;
      } else {
        lines.forEach((line, index) => {
          const tspan = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
          tspan.setAttribute('x', String(width / 2));
          tspan.setAttribute('dy', index === 0 ? String(-(lines.length - 1) * 0.6) + 'em' : '1.2em');
          tspan.textContent = line;
          svgText.appendChild(tspan);
        });
      }
      foreignObject.replaceWith(svgText);
    });
    return clone;
  };
  const saveMermaidSvg = async (el) => {
    const url = el.dataset.saveUrl;
    if (!url || el.dataset.saveStarted) return;
    el.dataset.saveStarted = '1';
    const status = el.querySelector('.mermaid-ajax-status');
    const svg = el.querySelector('svg');
    if (!svg) {
      if (status) status.textContent = 'SVG取得失敗';
      return;
    }
    if (status) status.textContent = 'SVG保存中...';
    try {
      const params = new URLSearchParams();
      const sanitizedSvg = sanitizeMermaidSvgForSave(svg);
      params.append('svg', new XMLSerializer().serializeToString(sanitizedSvg));
      params.append('mermaid_svg', el.dataset.editToken || '');
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
      });
      const json = await res.json();
      if (!res.ok || !json.ok) {
        throw new Error(json.message || 'SVG保存に失敗しました');
      }
      if (status) status.textContent = 'SVG保存完了';
    } catch (err) {
      if (status) status.textContent = err.message;
      if (status) status.classList.add('error');
    }
  };
  window.addEventListener('DOMContentLoaded', async () => {
    await mermaid.run({ querySelector: '.mermaid' });
    document.querySelectorAll('.kona3-mermaid-async[data-save-url]').forEach(saveMermaidSvg);
  });
</script>
EOS;
    }
    // export SVGファイル
    $svg_link = "";
    $ajax_attr = "";
    $ajax_status = "";
    if ($filename) {
        $page = kona3getPage(); // 現在のページ名を取得
        $paths = kona3plugins_mermaid_get_paths($page, $filename);
        if ($paths === false) {
            $filename = "";
        } else {
            $filename = $paths['filename'];
            $full_svg = $paths['full_svg'];
            $full_mmd = $paths['full_mmd'];
            $url_svg = kona3plugins_mermaid_get_data_url($full_svg);
            $url_svg_html = htmlspecialchars($url_svg, ENT_QUOTES);
            $svg_link .= "<span><a href=\"{$url_svg_html}\" target=\"_blank\">(↓svg)</a></span>\n";
            if (kona3isLogin()) {
                kona3plugins_mermaid_write_source($full_mmd, $text);
            }
            $update = kona3plugins_mermaid_needs_regenerate($full_mmd, $full_svg);
            if ($update && kona3isLogin()) {
                $ajax_url = kona3getPageURL($page, "mermaid_ajax", "", kona3getURLParams(['file' => $filename]));
                $ajax_url_html = htmlspecialchars($ajax_url, ENT_QUOTES);
                $edit_token = htmlspecialchars(kona3_getEditToken('mermaid_svg'), ENT_QUOTES);
                $ajax_attr = " data-save-url=\"{$ajax_url_html}\" data-edit-token=\"{$edit_token}\"";
                $ajax_status = "<span class=\"mermaid-ajax-status\">SVG保存待ち</span>";
            }
        }
    }
    $caption_html = htmlspecialchars($caption, ENT_QUOTES);
    $body = <<<EOS
<!-- mermaid -->
<div class="svg kona3-mermaid-async"{$ajax_attr}>
  <pre class="mermaid">{$text_html}</pre>
  <div class="memo">{$caption_html} {$svg_link} {$ajax_status}</div>
</div>
<!-- /mermaid -->
EOS;
    $result = $head . "\n" . $body . "\n";
    return $result;
}
