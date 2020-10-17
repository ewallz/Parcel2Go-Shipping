(function($) {	
    console.log("Settings",window.parcel2go_settings);
    var rootUrl = window.parcel2go_settings.rootUrl + window.parcel2go_settings.namespace;
    var orderId = window.parcel2go_settings.orderId;
    var nonce = window.parcel2go_settings.nonce;
    var siteUrl = window.parcel2go_settings.siteUrl;
    var orderLineId = window.parcel2go_settings.orderLineId;
    var hash = window.parcel2go_settings.hash;

    function showLoading() {
        var img = $("<img/>").attr("src",siteUrl + "/wp-admin/images/spinner-2x.gif").css("height","15px");
        $("#p2gTracking").html('');
        $("#p2gTracking").append(img); 
    }

    function getTracking() {
        showLoading();
        $.ajax({
            url: rootUrl + "/tracking",
            type: "POST",
            data: {orderLineId : orderLineId},
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
                console.log("RAW Tracking", data);
                if(data.result.Results.length < 1) {
                    $("#p2gTracking").html('<span><a href="https://www.parcel2go.com/tracking/' + orderLineId +'">No Tracking Results Yet</a></span>');
                }
                else {
                    $("#p2gTracking").html('<span><a href="https://www.parcel2go.com/tracking/' + orderLineId +'">' + data.result.Results[0].Description + '</a></span>');
                }
            },
            error : function(data) {
                console.log("Error Getting Tracking",data);
                $("#p2gTracking").html('');
                if(data.status === 403 || data.status === 401) {
                    $("#p2gTracking").append($("<span />").text("Error Getting Tracking - Check Your API Key"));
                }
                else {
                    $("#p2gTracking").append($("<a />").text("Error Getting Tracking").attr("href","https://www.parcel2go.com/help-centre"));
                }
            }
         });
    }

    function getLabel() {
        $("#btnP2gLabel").html('');
        var img = $("<img/>").attr("src",siteUrl + "/wp-admin/images/spinner-2x.gif").css("height","15px");
        $("#btnP2gLabel").append(img);
        var labelSize = $('select#label_size').children("option:selected").val();

        $.ajax({
            url: rootUrl + "/label",
            type: "POST",
            data: {orderId : orderId, hash : hash, labelSize : labelSize},
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
                console.log("RAW Label", data);
                var label = data.result.Base64EncodedLabels[0];
                
                $("#btnP2gLabel")
                    .attr("href","data:application/pdf;base64," + label)
                    .attr("title","Parcel2Go_Label.pdf")
                    .attr("download","Parcel2Go_Label.pdf")
                    .unbind("click")
                    .html('Download');
            },
            error : function(data) {
                console.log("Error Getting Label",data);
                $("#btnP2gLabel").html('');
                if(data.status === 403 || data.status === 401) {
                    $("#btnP2gLabel").append($("<span />").text("Error Getting Label - Check Your API Key"));
                }
                else {
                    $("#btnP2gLabel").append($("<a />").text("Error Getting Label").attr("href","https://www.parcel2go.com/help-centre"));
                }
            }
         });
    }

    getTracking();
    $("#btnP2gLabel").click(function() {
        getLabel();
    });
    $('select#label_size').change(function() {
        var attr = $('a#btnP2gLabel').attr('title');
        if (typeof attr !== typeof undefined && attr !== false) {
           //label is ready to download - reset to generate
           console.log('Label ready to download');
           $('a#btnP2gLabel').removeAttr("href");
           $('a#btnP2gLabel').removeAttr("title");
           $('a#btnP2gLabel').removeAttr("download");
           $("#btnP2gLabel").click(function() {
               getLabel();
           });
           $('a#btnP2gLabel').html('Generate');
        }else{            

             // label is ready to generate
             console.log('Label ready to generate');
        }

    });


})( jQuery );