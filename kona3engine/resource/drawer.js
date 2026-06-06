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
  
});
