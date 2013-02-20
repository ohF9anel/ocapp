$(document).ready(function() {    
    $('a.btnPrint').click(function() {
        window.print();
        return false;
    });
    
    $('input.group').attr('checked', 'checked');
    
    $('.tooltip').tooltip();
});