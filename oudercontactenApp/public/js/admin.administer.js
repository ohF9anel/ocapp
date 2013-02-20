$(document).ready(function() { 
    // call the tablesorter plugin 
    $("table#rightsTable").tablesorter({  
        sortList: [[0,0]]
    });
    $("table#rightsTable") 
    .tablesorterPager({container: $("#pager"), removeRows: false});
    
    $("#btnSave").click(function() {
        $("table#rightsTable").tablesorterPager({size:1000});
        return true;
    });
});

