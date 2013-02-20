$(document).ready(function(){
    var loc = window.location.href;
    var ind = (window.location.href).indexOf("Parent");
    var locationtoredirect = loc.substring(0, ind);
    
    /**
     * This function is triggered when user select a radio button. It sends a message to the server to allocate a temporary appointment
     */
    $("input[type='radio']").change(function() {
        var generator = $(this);
        var type = ($(this).attr("name").charAt(0) == 't' ? 1 : 2);
        var id = $(this).attr("name").split('p')[0].substr(1);
        var pupilId = $(this).attr("name").split('p')[1];
        var dayId = $(this).attr("value").split('d')[1];
        var slot = $(this).attr("value").split('d')[0];
        
        //disable select
        $("td." + id + " input[type='radio']").attr('disabled', true);
        
        $.post(locationtoredirect + "Parent/allocateslot", {type: type, id: id, pupilId: pupilId, dayId: dayId, slot: slot}, function(data) {
            if (data.indexOf("NOT OK") != -1) {
                (generator.parent()).attr("class", "occupied");
                (generator.parent()).text("");
            }

            //enable select
            $("td." + id + " input[type='radio']").attr('disabled', false);
            setDisabled();
        });
    });
    
    $("a[data-toggle='tab']").on("shown",function(e) {
        var temp = e.target.toString().split("#");
        $("input#activeDay").attr("value", temp[1]);
        setDisabled();
    });
    
    /**
     * This function is triggered by a timer and sends a message to the server to tell the user is still active so leastime of temorary appointments is extended 
     */
    setInterval(function() {
        $.post(locationtoredirect + "Parent/exceedleasetime", null, function(data) {
            if (data == "NOT OK") {
                
            }
        });
    }, 60000);
    
    /**
     * This function is triggered after a few minutes and makes an end to book action by redirecting
     */
    setTimeout(function() {
        window.location = locationtoredirect + "Parent/";
    }, 15 * 60000);
    
    setDisabled();
});


/**
 * Function checks which radio buttons has to be disabled in order to enforce requirements of users.
 */
function setDisabled() {
    var timeslot = parseInt($("#timeslot").attr("value"));
    var meantime = parseInt($("#meantime").attr("value"));
    var toDisable = Math.ceil(meantime / timeslot);
    
    $("input[type='radio']").attr("disabled", false);
    var appointments = $("div#" + $("input#activeDay").attr("value") + " input[type='radio']:checked");
    var size = appointments.size();
    $.each(appointments, function (index, appointment) {
        var row = parseInt(($(appointment).parent().parent().attr("class")).substr(1));
        var col = $(appointment).attr("name");
        
        for (i = row - toDisable; i <= row + toDisable; i++) {
            var entries = $("tr.r" + i).children();
            $.each(entries, function (index1, entry) {
                $.each($(entry).children("input[type='radio']"), function(index2, input) {
                    if ($(input).attr("name") != col) {
                        $(input).attr("disabled", true);
                    }
                });
            });
        }
    });
}