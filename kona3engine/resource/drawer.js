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

  // 文字サイズと行間の初期化・制御
  const DEFAULT_FONT_SIZE = 1.0;
  const DEFAULT_LINE_HEIGHT = 1.7;
  const FONT_SIZE_STEP = 0.1;
  const LINE_HEIGHT_STEP = 0.1;
  
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
      currentFontSize = Math.min(2.0, currentFontSize + FONT_SIZE_STEP);
      updateTextStyle();
    });
  }
  if (btnFontMinus) {
    btnFontMinus.click(function(e) {
      if (e) { e.preventDefault(); }
      currentFontSize = Math.max(0.7, currentFontSize - FONT_SIZE_STEP);
      updateTextStyle();
    });
  }

  if (btnLinePlus) {
    btnLinePlus.click(function(e) {
      if (e) { e.preventDefault(); }
      currentLineHeight = Math.min(2.5, currentLineHeight + LINE_HEIGHT_STEP);
      updateTextStyle();
    });
  }
  if (btnLineMinus) {
    btnLineMinus.click(function(e) {
      if (e) { e.preventDefault(); }
      currentLineHeight = Math.max(1.2, currentLineHeight - LINE_HEIGHT_STEP);
      updateTextStyle();
    });
  }
  if (btnReset) {
    btnReset.click(function(e) {
      if (e) { e.preventDefault(); }
      currentFontSize = DEFAULT_FONT_SIZE;
      currentLineHeight = DEFAULT_LINE_HEIGHT;
      updateTextStyle();
    });
  }
  
});
