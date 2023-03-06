$(function(){

  const btn = $('#hamburger_icon');
  const win = $("#drawer_background");
  const nav = $("nav.menuitems");
  
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
    nav.css({display: 'block'});
    win.fadeIn(500);
  }
  
  function closeMenu() {
    btn.removeClass("open");
    execMenu(-300);
    win.fadeOut(500, ()=>{
      win.addClass('close');
      nav.css({display: 'none'});
    })
  }
  
  function execMenu(pos) {
    nav.stop().animate({
        right: pos
    }, 200);
  }
  btn.on("click", toggleMenu);
  win.on("click", closeMenu);
  
});
