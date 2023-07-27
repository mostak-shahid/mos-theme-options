jQuery(document).ready(function($) {  
    /* Theme Option JS */
    $( window ).load(function() {
        $('.html-editor').find("textarea").ace({ theme: 'twilight', lang: 'html', height: 400 });
        $('.css-editor').ace({ theme: 'twilight', lang: 'css', height: 400 });
        $('.js-editor').find("textarea").ace({ theme: 'twilight', lang: 'javascript' });
    });
    $("span.theme_option_photo_remove_button").on("click", function(del){
      del.preventDefault();
      var button = $(this);
      var newSrc = button.siblings('div.theme_option_photo_container').find('img').data('src');
      $(this).closest('.photo-container').find('.photo').val('');
      button.siblings('div.theme_option_photo_container').find('img').attr('src', newSrc);
  });

  $(".theme_option_range").on('change', function(){
      $(this).closest('.range-wrapper').find('.theme_option_range_value').val($(this).val());
      //console.log($(this).val());
  });
  $(".theme_option_repeater_add_button").on('click', function(){
      var clonedData = $(this).closest('.repeater-wrapper').find('.repeater-data-wrapper > .repeater-unit').clone();
      $(this).siblings('.repeater-data').append(clonedData);
  });
  $('body').on('click', '.theme_option_repeater_remove_button', function (){
      $(this).parent().remove();
  });

  $('.mos-tabs').find('.mos-nav-tab').on('click', function (e){
      e.preventDefault();
      var tab = $(this).data('tab');
      setMtoCookie('mos_theme_options_active_tab',tab,1);
      $(this).closest('li').siblings().find('a').removeClass('nav-tab-active');
      $(this).closest('li').find('ul a').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');
      $('.mos-tab-item').hide();

      $('.mos-tab-item--' + tab).show();

  });
  //console.log($(".mos-tab-item:first-child").attr('class').split(/\s+/).length);

  if(getMtoCookie('mos_theme_options_active_tab')) {
      console.log(getMtoCookie('mos_theme_options_active_tab'));
      $('.'+getMtoCookie('mos_theme_options_active_tab')).closest('li').siblings().find('a').removeClass('nav-tab-active');
      $('.'+getMtoCookie('mos_theme_options_active_tab')).addClass('nav-tab-active');
      $('.mos-tab-item').hide();
      $('.mos-tab-item--' + getMtoCookie('mos_theme_options_active_tab')).show();
      
  } else {
      /*Need to code here*/
  }
  //setMtoCookie('mos_theme_options_active_tab','mos-tab-item--tab-2',0);
  /* Theme Option JS */
});
function setMtoCookie(cname,cvalue,exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays*24*60*60*1000));
    var expires = "expires=" + d.toGMTString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    //alert(cname);
}

function getMtoCookie(cname) {
    var name = cname + "=";
    var decodedCookie = decodeURIComponent(document.cookie);
    var ca = decodedCookie.split(';');
    for(var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}