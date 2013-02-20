$(document).ready(function(){
    $('#mailAddress').modal();
    $('#mailAddress').modal('hide');
    $('#alertMore').css('display', 'none');
    
    $('#lnkMail').click(function(){
        $('#mailAddress').modal('show');
        return false;
    });
    
    $('#btnCancel').click(function(){
        $('#mailAddress').modal('hide');
        return false;
    });
    
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