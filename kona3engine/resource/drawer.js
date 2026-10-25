qq(function(){

  const btn = qq('#hamburger_icon');
  const win = qq("#drawer_background");
  const nav = qq("nav.menuitems");
  
  function toggleMenu() {
    if (btn.hasClass("open")) {
      closeMenu();
    } else {
      openMenu();
    }
  }
  
  function openMenu() {
    btn.addClass("open");
    execMenu(0);
    win.css('left', '0px');
    win.css('top', '0px');
    win.css('height', '100%');
    win.css('width', '100%');
    nav.css({'display': 'block'});
    win.fadeIn(100);
  }
  
  function closeMenu() {
    btn.removeClass("open");
    execMenu(-300);
    win.fadeOut(100, ()=>{
      win.addClass('close');
      nav.css({'display': 'none'});
    })
  }
  
  function execMenu(pos) {
    nav.stop().animate({
        right: pos
    }, 100);
  }
  btn.click(toggleMenu);
  win.click(closeMenu);
  
  // ダークモード切り替え
  const themeToggle = qq('#dark_mode_toggle');
  if (themeToggle) {
    themeToggle.click(function(e) {
      if (e) { e.preventDefault(); }
      const body = qq('body');
      if (body.hasClass('dark-theme')) {
        body.removeClass('dark-theme');
        try {
          localStorage.setItem('kona3_theme', 'light');
        } catch (err) {
          console.warn("Storage access failed:", err);
        }
        themeToggle.text('🌙 Dark Mode');
        themeToggle.attr('aria-pressed', 'false');
      } else {
        body.addClass('dark-theme');
        try {
          localStorage.setItem('kona3_theme', 'dark');
        } catch (err) {
          console.warn("Storage access failed:", err);
        }
        themeToggle.text('☀️ Light Mode');
        themeToggle.attr('aria-pressed', 'true');
      }
    });

    // 初期状態のボタン表示調整
    if (qq('body').hasClass('dark-theme')) {
      themeToggle.text('☀️ Light Mode');
      themeToggle.attr('aria-pressed', 'true');
    } else {
      themeToggle.text('🌙 Dark Mode');
      themeToggle.attr('aria-pressed', 'false');
    }
  }

  // 文字サイズと行間の初期化・制御 (#330: スライダーで調整)
  const DEFAULT_FONT_SIZE = 1.0;
  const DEFAULT_LINE_HEIGHT = 1.7;
  const FONT_SIZE_MIN = 0.7;
  const FONT_SIZE_MAX = 2.0;
  const FONT_SIZE_STEP = 0.1;
  const LINE_HEIGHT_MIN = 1.2;
  const LINE_HEIGHT_MAX = 2.5;
  const LINE_HEIGHT_STEP = 0.1;

  // 0.1刻みの計算で出る浮動小数点の誤差 (1.2000000002など) を取り除く
  function roundStep(v) { return Math.round(v * 10) / 10; }

  // 現在の設定値を取得（localStorageまたはデフォルト）
  let currentFontSize = DEFAULT_FONT_SIZE;
  let currentLineHeight = DEFAULT_LINE_HEIGHT;

  try {
    var savedFontSize = localStorage.getItem('kona3_font_size');
    if (savedFontSize) {
      var val = parseFloat(savedFontSize);
      if (!isNaN(val)) {
        currentFontSize = Math.min(2.0, Math.max(0.7, val));
      }
    }
  } catch (err) {
    console.warn("Storage read failed for font_size:", err);
  }

  try {
    var savedLineHeight = localStorage.getItem('kona3_line_height');
    if (savedLineHeight) {
      var val = parseFloat(savedLineHeight);
      if (!isNaN(val)) {
        currentLineHeight = Math.min(2.5, Math.max(1.2, val));
      }
    }
  } catch (err) {
    console.warn("Storage read failed for line_height:", err);
  }

  // スライダーの位置・塗りつぶし・読み上げ用テキストを値に同期する
  function syncSlider(range, value, min, max, text) {
    if (!range) return;
    if (parseFloat(range.value) !== value) { range.value = String(value); }
    range.setAttribute('aria-valuetext', text);
    range.style.setProperty('--fill', ((value - min) / (max - min) * 100) + '%');
  }

  // disabledにすると押した直後にフォーカスが外れるので、端のボタンはaria-disabledで表す
  function setStepAriaDisabled(id, flag) {
    const el = document.getElementById(id);
    if (el) { el.setAttribute('aria-disabled', flag ? 'true' : 'false'); }
  }
  function isStepDisabled(id) {
    const el = document.getElementById(id);
    return el && el.getAttribute('aria-disabled') === 'true';
  }

  // ダイアログ内の現在の設定値表示を更新する (#330)
  function updateTextStyleDisplay() {
    const fontText = Math.round(currentFontSize * 100) + '%';
    const lineText = currentLineHeight.toFixed(1);
    const fontVal = document.getElementById('font_size_value');
    if (fontVal) {
      fontVal.textContent = fontText;
    }
    const lineVal = document.getElementById('line_height_value');
    if (lineVal) {
      lineVal.textContent = lineText;
    }
    syncSlider(document.getElementById('font_size_range'),
      currentFontSize, FONT_SIZE_MIN, FONT_SIZE_MAX, fontText);
    syncSlider(document.getElementById('line_height_range'),
      currentLineHeight, LINE_HEIGHT_MIN, LINE_HEIGHT_MAX, lineText);
    // ダイアログ内のサンプル文にもその場で反映する
    const preview = document.getElementById('text_style_preview');
    if (preview) {
      preview.style.fontSize = currentFontSize.toFixed(1) + 'rem';
      preview.style.lineHeight = currentLineHeight.toFixed(1);
    }
    const eps = 0.001;
    setStepAriaDisabled('font_size_minus', currentFontSize <= FONT_SIZE_MIN + eps);
    setStepAriaDisabled('font_size_plus', currentFontSize >= FONT_SIZE_MAX - eps);
    setStepAriaDisabled('line_height_minus', currentLineHeight <= LINE_HEIGHT_MIN + eps);
    setStepAriaDisabled('line_height_plus', currentLineHeight >= LINE_HEIGHT_MAX - eps);
    setStepAriaDisabled('text_style_reset',
      Math.abs(currentFontSize - DEFAULT_FONT_SIZE) < 0.01 &&
      Math.abs(currentLineHeight - DEFAULT_LINE_HEIGHT) < 0.01);
  }

  function updateTextStyle() {
    let css = '';
    // デフォルト値と異なる場合のみCSSを適用
    if (Math.abs(currentFontSize - DEFAULT_FONT_SIZE) > 0.01) {
      css += '#kona3_layout_show, #wikibody { font-size: ' + currentFontSize.toFixed(1) + 'em !important; }\n';
      try {
        localStorage.setItem('kona3_font_size', currentFontSize.toFixed(1));
      } catch (err) {
        console.warn("Storage write failed for font_size:", err);
      }
    } else {
      try {
        localStorage.removeItem('kona3_font_size');
      } catch (err) {
        console.warn("Storage remove failed for font_size:", err);
      }
    }
    
    if (Math.abs(currentLineHeight - DEFAULT_LINE_HEIGHT) > 0.01) {
      css += '#kona3_layout_show, #wikibody { line-height: ' + currentLineHeight.toFixed(1) + ' !important; }\n';
      try {
        localStorage.setItem('kona3_line_height', currentLineHeight.toFixed(1));
      } catch (err) {
        console.warn("Storage write failed for line_height:", err);
      }
    } else {
      try {
        localStorage.removeItem('kona3_line_height');
      } catch (err) {
        console.warn("Storage remove failed for line_height:", err);
      }
    }

    let styleEl = document.getElementById('kona3-text-style-override');
    if (css) {
      if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = 'kona3-text-style-override';
        document.head.appendChild(styleEl);
      }
      styleEl.textContent = css;
    } else {
      if (styleEl) {
        styleEl.parentNode.removeChild(styleEl);
      }
    }
    updateTextStyleDisplay();
  }

  // ボタンイベントの設定
  const btnFontPlus = qq('#font_size_plus');
  const btnFontMinus = qq('#font_size_minus');
  const btnLinePlus = qq('#line_height_plus');
  const btnLineMinus = qq('#line_height_minus');
  const btnReset = qq('#text_style_reset');

  if (btnFontPlus) {
    btnFontPlus.click(function(e) {
      if (e) { e.preventDefault(); }
      if (isStepDisabled('font_size_plus')) return;
      currentFontSize = roundStep(Math.min(FONT_SIZE_MAX, currentFontSize + FONT_SIZE_STEP));
      updateTextStyle();
    });
  }
  if (btnFontMinus) {
    btnFontMinus.click(function(e) {
      if (e) { e.preventDefault(); }
      if (isStepDisabled('font_size_minus')) return;
      currentFontSize = roundStep(Math.max(FONT_SIZE_MIN, currentFontSize - FONT_SIZE_STEP));
      updateTextStyle();
    });
  }

  if (btnLinePlus) {
    btnLinePlus.click(function(e) {
      if (e) { e.preventDefault(); }
      if (isStepDisabled('line_height_plus')) return;
      currentLineHeight = roundStep(Math.min(LINE_HEIGHT_MAX, currentLineHeight + LINE_HEIGHT_STEP));
      updateTextStyle();
    });
  }
  if (btnLineMinus) {
    btnLineMinus.click(function(e) {
      if (e) { e.preventDefault(); }
      if (isStepDisabled('line_height_minus')) return;
      currentLineHeight = roundStep(Math.max(LINE_HEIGHT_MIN, currentLineHeight - LINE_HEIGHT_STEP));
      updateTextStyle();
    });
  }

  // スライダー: 動かした瞬間に反映・保存する (inputイベント)
  function bindTextStyleRange(id, apply) {
    const range = document.getElementById(id);
    if (!range) return;
    range.addEventListener('input', function() {
      const v = parseFloat(range.value);
      if (isNaN(v)) return;
      apply(roundStep(v));
      updateTextStyle();
    });
  }
  bindTextStyleRange('font_size_range', function(v) {
    currentFontSize = Math.min(FONT_SIZE_MAX, Math.max(FONT_SIZE_MIN, v));
  });
  bindTextStyleRange('line_height_range', function(v) {
    currentLineHeight = Math.min(LINE_HEIGHT_MAX, Math.max(LINE_HEIGHT_MIN, v));
  });

  // 「標準」の目印をデフォルト値の位置に置く
  function placeDefaultMark(rangeId, def, min, max) {
    const range = document.getElementById(rangeId);
    if (!range || !range.parentNode) return;
    range.parentNode.style.setProperty('--default-p', String((def - min) / (max - min)));
  }
  placeDefaultMark('font_size_range', DEFAULT_FONT_SIZE, FONT_SIZE_MIN, FONT_SIZE_MAX);
  placeDefaultMark('line_height_range', DEFAULT_LINE_HEIGHT, LINE_HEIGHT_MIN, LINE_HEIGHT_MAX);
  if (btnReset) {
    btnReset.click(function(e) {
      if (e) { e.preventDefault(); }
      if (isStepDisabled('text_style_reset')) return;
      currentFontSize = DEFAULT_FONT_SIZE;
      currentLineHeight = DEFAULT_LINE_HEIGHT;
      updateTextStyle();
    });
  }
  updateTextStyleDisplay();

  // --- 設定ダイアログの制御 (#330) ---
  // ハンバーガーメニューの項目はダイアログを開くだけにし、調整はダイアログ内で行う
  function openSettingsDialog(dlg) {
    if (!dlg) return;
    if (typeof dlg.showModal === 'function') {
      if (!dlg.open) { dlg.showModal(); }
    } else {
      dlg.setAttribute('open', '');
    }
  }
  function closeSettingsDialog(dlg) {
    if (!dlg) return;
    if (typeof dlg.close === 'function' && dlg.open) {
      dlg.close();
    } else {
      dlg.removeAttribute('open');
    }
  }
  function setupSettingsDialog(openId, dialogId, closeId) {
    const opener = document.getElementById(openId);
    const dlg = document.getElementById(dialogId);
    if (!opener || !dlg) return;
    opener.addEventListener('click', function(e) {
      if (e) { e.preventDefault(); }
      // ダイアログの背面にメニューが残らないよう、先にメニューを閉じる
      if (btn.hasClass("open")) { closeMenu(); }
      openSettingsDialog(dlg);
    });
    const closer = document.getElementById(closeId);
    if (closer) {
      closer.addEventListener('click', function(e) {
        if (e) { e.preventDefault(); }
        closeSettingsDialog(dlg);
      });
    }
    // ダイアログの背景(backdrop)クリックでも閉じる
    dlg.addEventListener('click', function(e) {
      if (e.target === dlg) { closeSettingsDialog(dlg); }
    });
  }
  setupSettingsDialog('text_style_open', 'text_style_dialog', 'text_style_close');
  setupSettingsDialog('cache_settings_open', 'cache_settings_dialog', 'cache_settings_close');

  // --- オフラインキャッシュの制御 ---
  const DB_NAME = 'kona3_offline_db';
  const DB_VERSION = 1;

  function openDB(callback) {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onupgradeneeded = function(e) {
      const db = e.target.result;
      if (!db.objectStoreNames.contains('settings')) {
        db.createObjectStore('settings', { keyPath: 'key' });
      }
      if (!db.objectStoreNames.contains('pages')) {
        db.createObjectStore('pages', { keyPath: 'url' });
      }
    };
    request.onsuccess = function(e) {
      callback(e.target.result);
    };
    request.onerror = function(e) {
      console.warn('Database error: ', e.target.error);
    };
  }

  function getOfflineCacheSetting(callback) {
    openDB(function(db) {
      const tx = db.transaction('settings', 'readonly');
      const store = tx.objectStore('settings');
      const req = store.get('offline_cache');
      req.onsuccess = function() {
        const res = req.result;
        callback(res ? res.value : false);
      };
      req.onerror = function() {
        callback(false);
      };
    });
  }

  function setOfflineCacheSetting(value, callback) {
    openDB(function(db) {
      const tx = db.transaction('settings', 'readwrite');
      const store = tx.objectStore('settings');
      store.put({ key: 'offline_cache', value: value });
      tx.oncomplete = function() {
        if (callback) callback();
      };
    });
  }

  function clearOfflineCache(callback) {
    openDB(function(db) {
      const tx = db.transaction('pages', 'readwrite');
      const store = tx.objectStore('pages');
      const req = store.clear();
      req.onsuccess = function() {
        if (window.caches) {
          window.caches.delete('kona3-assets-v1').then(function() {
            if (callback) callback();
          }).catch(function() {
            if (callback) callback();
          });
        } else {
          if (callback) callback();
        }
      };
    });
  }

  const cacheToggle = qq('#offline_cache_toggle');
  const cacheClear = qq('#offline_cache_clear');

  const KONA3_LANG = window.KONA3_LANG || {
    offline_cache: "Offline Cache",
    clear_cache: "Clear Cache",
    cache_cleared: "Cache cleared",
    offline_cache_enabled: "Offline Cache Enabled",
    offline_cache_disabled: "Offline Cache Disabled",
    enabled: "ON",
    disabled: "OFF"
  };

  function updateToggleUI(enabled) {
    if (cacheToggle) {
      const statusText = enabled ? KONA3_LANG.enabled : KONA3_LANG.disabled;
      cacheToggle.text(KONA3_LANG.offline_cache + ': ' + statusText);
      cacheToggle.attr('aria-pressed', enabled ? 'true' : 'false');
    }
  }

  if (cacheToggle) {
    getOfflineCacheSetting(function(enabled) {
      updateToggleUI(enabled);
    });

    cacheToggle.click(function(e) {
      if (e) { e.preventDefault(); }
      getOfflineCacheSetting(function(enabled) {
        const nextState = !enabled;
        setOfflineCacheSetting(nextState, function() {
          updateToggleUI(nextState);
          if (nextState) {
            alert(KONA3_LANG.offline_cache_enabled);
            saveCurrentPage();
          } else {
            alert(KONA3_LANG.offline_cache_disabled);
          }
        });
      });
    });
  }

  if (cacheClear) {
    cacheClear.click(function(e) {
      if (e) { e.preventDefault(); }
      if (confirm(KONA3_LANG.clear_cache + '?')) {
        clearOfflineCache(function() {
          alert(KONA3_LANG.cache_cleared);
        });
      }
    });
  }

  function isCacheablePage() {
    const params = new URLSearchParams(location.search);
    // KonaWiki3 uses positional params: ?Page&action&status (action may not be action=...)
    const emptyKeys = [];
    for (const [k, v] of params.entries()) {
      if (v === '') { emptyKeys.push(k); }
    }
    const action = params.get('action') || emptyKeys[1] || 'show';
    if (action !== 'show' && action !== 'print') {
      return false;
    }
    if (document.querySelector('.kona3_error')) {
      return false;
    }
    return true;
  }

  function saveCurrentPage() {
    if (!isCacheablePage()) return;
    if (navigator.onLine === false) return; // Do not save cache when offline
    
    getOfflineCacheSetting(function(enabled) {
      if (!enabled) return;
      
      const mainEl = document.getElementById('kona3_main');
      if (!mainEl) return;
      
      const url = location.pathname + location.search;
      
      // Clone the document to safely manipulate DOM and generate shell HTML
      const docClone = document.documentElement.cloneNode(true);
      
      // 1. Remove the offline banner if it exists in the clone to avoid duplication
      const cloneBanner = docClone.querySelector('.kona3_offline_banner');
      if (cloneBanner) {
        cloneBanner.remove();
      }
      
      // 2. Extract contentHtml from main container (without banner if it exists in mainEl)
      let contentHtml = mainEl.innerHTML;
      const banner = document.querySelector('.kona3_offline_banner');
      if (banner) {
        const tempMain = mainEl.cloneNode(true);
        const tempBanner = tempMain.querySelector('.kona3_offline_banner');
        if (tempBanner) {
          tempBanner.remove();
        }
        contentHtml = tempMain.innerHTML;
      }
      
      // 3. Set the placeholder in the clone's main container to generate shellHtml
      const cloneMain = docClone.querySelector('#kona3_main');
      if (cloneMain) {
        cloneMain.innerHTML = '<!-- KONA3_CONTENT -->';
      }
      const shellHtml = docClone.outerHTML;
      
      const timestamp = Date.now();
      const lang = document.documentElement.lang || 'en';
      const msg_tpl = (window.KONA3_LANG && window.KONA3_LANG.showing_offline_cache) || '';
      
      openDB(function(db) {
        const tx = db.transaction(['pages', 'settings'], 'readwrite');
        tx.objectStore('pages').put({
          url: url,
          html: contentHtml,
          timestamp: timestamp,
          lang: lang,
          msg_tpl: msg_tpl
        });
        tx.objectStore('settings').put({
          key: 'shell_html',
          value: shellHtml
        });
      });
    });
  }

  saveCurrentPage();

  if ('serviceWorker' in navigator && !window.KONA3_STATIC) {
    navigator.serviceWorker.register('./sw.js').then(function(reg) {
      // success
    }).catch(function(err) {
      console.warn('ServiceWorker registration failed: ', err);
    });
  }
  
});
