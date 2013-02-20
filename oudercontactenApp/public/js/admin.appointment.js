$(document).ready(function() { 
    // call the tablesorter plugin 
    $("table#titularTable").tablesorter({  
        sortList: [[1,0]] 
    });
    $("table#titularTable") 
    .tablesorterPager({container: $("#pagertitular")});
    
    $("table#teacherTable").tablesorter({  
        sortList: [[1,0]] 
    });
    $("table#teacherTable") 
    .tablesorterPager({container: $("#pagerteacher")});
});

