$(document).ready(function(){
    $('#alertMore').css('display', 'none');
    
    $('#lnkMore').click(function(){
        $('#alertMore').css('display', 'block');
        $('#lnkMore').css('display', 'none');
        return false;
    });
    
    $('#lnkClose').click(function() {
        $('#alertMore').css('display', 'none');
        $('#lnkMore').css('display', 'inline');
    });
});