$(document).ready(function() {
    $('.settingsDisplay').on('click', function() {
      if ($('.settings').css('display') == 'block') {
          $('.settings').css('display', 'none');
      } else {
          $('.settings').css('display', 'block');
      }
      
       return false;
    });
    
    $('#confirm').modal();
    $('#confirm').modal('hide');
    $('.sure').click(function(){
        var detail = $(this).parent().prev().prev().html() + ' - ' + $(this).parent().prev().html();
        $('#detail').text(detail);
        $('#confirmed').attr('href', $(this).attr('href'));
        $('#confirm').modal('show');
        return false;
    });
    
    $('#cancel').click(function() {
        $('#confirm').modal('hide');
    });
});