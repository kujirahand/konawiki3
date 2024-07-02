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
  
});
