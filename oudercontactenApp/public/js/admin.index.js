$(document).ready(function(){
    $('#confirmDelete').modal();
    $('#confirmDelete').modal('hide');
    $('#confirmChange').modal();
    $('#confirmChange').modal('hide');
    $('.date').datepicker();
    
    $('#btnDelete').click(function(){
        $('#confirmDelete').modal('show');
        return false;
    });
    
    $('#btnSave').click(function(){
        $('#confirmChange').modal('show');
        return false;
    });
    
    $('.btnCancel').click(function(){
        $('.modal').modal('hide');
        return false;
    })
});