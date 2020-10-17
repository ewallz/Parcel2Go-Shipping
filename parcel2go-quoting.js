(function($) {	
    console.log("Settings",window.parcel2go_settings);
    var rootUrl = window.parcel2go_settings.rootUrl + window.parcel2go_settings.namespace;
    var orderId = window.parcel2go_settings.orderId;
    var nonce = window.parcel2go_settings.nonce;
    var siteUrl = window.parcel2go_settings.siteUrl;
    var defaults = {};
    var customs = { VatStatus : null, ReasonsForExport : null, ManufacturedIn : null, VatNumber : null, Required : false }
    var countries = [];

    function showLoading() {
        var img = $("<img/>").attr("src",siteUrl + "/wp-admin/images/spinner-2x.gif");
        var lbl = $("<div />").text("Loading");
        $("#p2g .results").html('');
        $("#p2g .results").append(img).append(lbl); 
    }

    function showOverviewLoading() {
        var img = $("<img/>").attr("src",siteUrl + "/wp-admin/images/spinner-2x.gif");
        var lbl = $("<div />").text("Loading");
        $("#p2g .overview").html('');
        $("#p2g .overview").append(img).append(lbl); 
    }

    function getDefaults() {
        showOverviewLoading();
        $.ajax({
            url: rootUrl + "/defaults",
            type: "GET",
            data: {orderId : orderId},
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
                console.log("RAW Defaults", data);
                defaults = data;
                customs.VatNumber = data.VatNumber;
                getCountries();
            },
            error : function(data) {
                $("#p2g .overview").html('');
                showError(data.responseJSON.message);
            }
         });
    }

    function getCountries() {
        $.ajax({
            url: rootUrl + "/countries",
            type: "GET",
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
                console.log("RAW Countries", data);
                countries = data.result;
                displayDefaults();
            },
            error : function(data) {
                $("#p2g .overview").html('');
                showError(data.responseJSON.message);
            }
         });
    }

    function displayDefaults() {

        var collection = $("<div>").css({"width":"33%", "float":"left"});
        collection.append($("<h3>").text("Collection/Return Address"));
        collection.append(createField("Contact Name",defaults.CollectionAddress,"ContactName"));
        collection.append(createField("Property",defaults.CollectionAddress,"Property"));
        collection.append(createField("Street",defaults.CollectionAddress,"Street"));
        collection.append(createField("Town",defaults.CollectionAddress,"Town"));
        collection.append(createField("Postcode",defaults.CollectionAddress,"Postcode"));
        collection.append(createField("County",defaults.CollectionAddress,"County"));
        collection.append(createField("Email",defaults.CollectionAddress,"Email"));
        collection.append(createField("Phone",defaults.CollectionAddress,"Phone"));
        collection.append(createCountryDropDown("Country",defaults.CollectionAddress,"Country"));


        var delivery = $("<div>").css({"width":"33%", "float":"left"});
        delivery.append($("<h3>").text("Delivery Address"));
        delivery.append(createField("Contact Name",defaults.DeliveryAddress,"ContactName"));
        delivery.append(createField("Property",defaults.DeliveryAddress,"Property"));
        delivery.append(createField("Street",defaults.DeliveryAddress,"Street"));
        delivery.append(createField("Town",defaults.DeliveryAddress,"Town"));
        delivery.append(createField("Postcode",defaults.DeliveryAddress,"Postcode"));
        delivery.append(createField("County",defaults.DeliveryAddress,"County"));
        delivery.append(createField("Email",defaults.DeliveryAddress,"Email"));
        delivery.append(createField("Phone",defaults.DeliveryAddress,"Phone"));
        delivery.append(createCountryDropDown("Country",defaults.DeliveryAddress,"Country"));
        delivery.append(createField("Special Instructions",defaults.DeliveryAddress,"SpecialInstructions"));

        var box = $("<div>").css({"width":"33%", "float":"left"});
        box.append($("<h3>").text("Box Details"));
        box.append(createField("Weight (kg)",defaults.Box,"Weight"));
        box.append(createField("Height (cm)",defaults.Box,"Height"));
        box.append(createField("Length (cm)",defaults.Box,"Length"));
        box.append(createField("Width (cm)",defaults.Box,"Width"));
        box.append(createField("Estimated Value (£)",defaults.Box,"EstimatedValue"));
        box.append(createField("Contents Summary",defaults.Box,"ContentsSummary"));
        box.append(createCheckbox("Include Protection",defaults,"IncludeCover"));

        var btn = $("<button />").text("Get Quote").addClass("button button-primary");
        btn.click(function() {
            $("#p2g .overview input[type='text']").each(function() {
                $(this).replaceWith($("<label />").text($(this).val()).css("margin-left","10px"))
            });
            $("#p2g .overview input[type='checkbox']").each(function() {
                $(this).replaceWith($("<label />").text($(this).is(':checked')).css("margin-left","10px"))
            });
            $("#p2g .overview select").each(function() {
                $(this).replaceWith($("<label />").text($(this).val()).css("margin-left","10px"))
            });

            var btnReset = $("<button />").text("Restart").addClass("button button-primary");
            btnReset.click(function() {
                window.location.reload();
            });
            btn.replaceWith(btnReset);

            getQuotes();
        });
        box.append(btn);

        $("#p2g .overview").html('');
        $("#p2g .overview").append(collection);
        $("#p2g .overview").append(delivery);
        $("#p2g .overview").append(box);
        $("#p2g .overview").append($("<div>").addClass("clear"));
    }

    function createField(name, value, key) {
        var container = $("<p>").addClass("form-field form-field-wide");
        var lbl = $("<label>").text(name + " : ").css("font-weight","bold");
        var txt = $("<input>").attr("type","text").attr("required","required").val(value[key]);
        txt.keyup(function() {
            value[key] = $(this).val();
        });
        container.append(lbl);
        container.append(txt);
        return container;
    }

    function createCheckbox(name, value, key) {
        var container = $("<p>").addClass("form-field form-field-wide");
        var lbl = $("<label>").text(name + " : ").css("font-weight","bold")
        var chk = $("<input>").attr("type","checkbox");
        if(value) {
            chk.attr("checked","checked");
        }
        chk.change(function() {
            value[key] = $(this).is(':checked');
        });
        container.append(lbl);
        container.append(chk);
        return container;
    }

    function createCountryDropDown(name, value, key) {
       
        var container = $("<p>").addClass("form-field form-field-wide");
        var lbl = $("<label>").text(name + " : ").css("font-weight","bold")
        var select = $("<select />").css("width","95%");
        var selected = false;
        for(var i = 0; i < countries.length; i++) {
            var option = $("<option />").text(countries[i].Name).val(countries[i].Iso3Code);
            if(!selected && (value[key] === countries[i].Iso3Code || value[key] === countries[i].Iso2Code)) {
                option.attr("selected","selected");
                selected = true;
                value[key] = countries[i].Iso3Code;
            }
            select.append(option)
        }
        select.change(function() {
            value[key] = $(this).val();
        });
        container.append(lbl);
        container.append(select);
        return container;
    }

    function getQuotes() {
        showLoading();
        $.ajax({
            url: rootUrl + "/quote",
            type: "POST",
            data: {
                value : defaults.Box.EstimatedValue, 
                weight : defaults.Box.Weight,
                length : defaults.Box.Length,
                height : defaults.Box.Height,
                width : defaults.Box.Width,
                collectionCountry : defaults.CollectionAddress.Country,
                collectionPostcode : defaults.CollectionAddress.Postcode,
                deliveryPostcode : defaults.DeliveryAddress.Postcode,
                deliveryCountry : defaults.DeliveryAddress.Country,
                includeCover : defaults.IncludeCover
            },
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
                console.log("RAW Quotes", data);
                var quotes = processQuotes(data.result);
                if(!data.result.Quotes || data.result.Quotes.length === 0) {
                    console.log("No Quotes Found");
                    showError({Message : "No Services Found For This Order"});
                }
                else {
                    console.log("Processed Quotes", quotes);
                    displayQuotes(quotes);
                }
            },
            error : function(data) {
                showError(data.responseJSON.message);
            }
         });
    }

    function showError(message) {
        $("#p2g .results").html('');
        if(message.Message) {
            var lbl = $("<h1 />").text(message.Message);
            $("#p2g .results").append(lbl); 
        }
        else if(message.Errors && message.Errors.length > 0) {
            var lblHeader = $("<h1 />").text("Error - Please fix the issues below");
            $("#p2g .results").append(lblHeader); 
            for(var i = 0; i < message.Errors.length; i ++) {
                var lbl = $("<h2 />").text(message.Errors[i].Error);
                $("#p2g .results").append(lbl); 
            }
        }
        else if(message === "Authorization Failed") {
            $("#p2g .overview").html('');
            var header = $("<h1 />").text(message);
            $("#p2g .results").append(header); 
            var lbl = $("<p />").text("Please check your API credentials in the settings");
            $("#p2g .results").append(lbl); 
        }
        else {
            var lblHeader = $("<h1 />").text("Error");
            $("#p2g .results").append(lblHeader); 
            var lbl = $("<pre />").text(JSON.stringify(message));
            $("#p2g .results").append(lbl); 
        }

        var btnReset = $("<button />").text("Edit and Retry").addClass("button button-primary").css("margin-top","20px");
        btnReset.click(function() {
            window.location.reload();
        });
        $("#p2g .results").append(btnReset);
    }
    
    function processQuotes(data) {
        var results = {
            collection : {
                slow : [],
                medium : [],
                fast :[]
            },
            shop : {
                slow : [],
                medium : [],
                fast :[]
            }
        };
        for(var i = 0; i < data.Quotes.length; i++) {
            var q = data.Quotes[i];

            if(q.Service.DeliveryType !== "Door") {
                continue;
            }
            
            var deliveryTime = moment(q.EstimatedDeliveryDate);
            var deliveryDays = getBusinessDays(deliveryTime,moment());

            var result = {
                CourierName : q.Service.CourierName, 
                Service : q.Service.Name,
                Price : q.TotalPrice,
                EstimatedDeliveryDate : deliveryTime.format("YYYY-MM-DD"),
                CollectionType : q.Service.CollectionType,
                DeliveryDays : deliveryDays,
                CutOff : moment(q.CutOff).format("YYYY-MM-DD HH:mm:ss"),
                Slug : q.Service.Slug,
                ProviderCode : q.Service.DropOffProviderCode,
                SvgImage: q.Service.Links.ImageSvg
            };

            if(q.Service.CollectionType === "Collection") {
                if(q.Service.Classification === "Fast") {
                    results.collection.fast.push(result);
                }
                else if(q.Service.Classification === "Medium") {
                    results.collection.medium.push(result);
                }
                else {
                    results.collection.slow.push(result);
                }
            }
            else {
                if(q.Service.Classification === "Fast") {
                    results.shop.fast.push(result);
                }
                else if(q.Service.Classification === "Medium") {
                    results.shop.medium.push(result);
                }
                else {
                    results.shop.slow.push(result);
                }
            }
        }

        results.collection.fast.sort(sortByPrice);
        results.collection.medium.sort(sortByPrice);
        results.collection.slow.sort(sortByPrice);

        results.shop.fast.sort(sortByPrice);
        results.shop.medium.sort(sortByPrice);
        results.shop.slow.sort(sortByPrice);

        return results;
    }

    function displayQuotes(quotes) {
        var tbl = $("<table/>").addClass("wp-list-table widefat fixed striped");
        
        var tblHeader = $("<thead>");
        var tblHeaderRow = $("<tr>");        
        tblHeaderRow.append($("<td>").text("Courier"));
        tblHeaderRow.append($("<td>").text("Service"));
        tblHeaderRow.append($("<td>").text("Type"));
        tblHeaderRow.append($("<td>").text("Earliest Delivery Date"));
        tblHeaderRow.append($("<td>").text("Service Speed"));
        tblHeaderRow.append($("<td>").text("Price"));
        tblHeaderRow.append($("<td>").text("Book"));
        tblHeader.append(tblHeaderRow);
        tbl.append(tblHeader);

        var tblBody = $("<tbody>");

        if(quotes.collection.fast.length) {
            for (let i = 0; i < quotes.collection.fast.length; i++) {
                const quote = quotes.collection.fast[i];
                tblBody.append(generateQuoteRow(quote,"Fast"));
            }
            
        }
        if(quotes.collection.medium.length) {
            for (let i = 0; i < quotes.collection.medium.length; i++) {
                const quote = quotes.collection.medium[i];
                tblBody.append(generateQuoteRow(quote,"Medium"));
            }            
        }
        if(quotes.collection.slow.length) {
            for (let i = 0; i < quotes.collection.slow.length; i++) {
                const quote = quotes.collection.slow[i];
                tblBody.append(generateQuoteRow(quote,"Slow"));
            }            
        }

        if(quotes.shop.fast.length) {
            for (let i = 0; i < quotes.shop.fast.length; i++) {
                const quote = quotes.shop.fast[i];
                tblBody.append(generateQuoteRow(quote,"Fast"));
            }            
        }
        if(quotes.shop.medium.length) {
            for (let i = 0; i < quotes.shop.medium.length; i++) {
                const quote = quotes.shop.medium[i];
                tblBody.append(generateQuoteRow(quote,"Medium"));
            }
            
        }
        if(quotes.shop.slow.length) {
            for (let i = 0; i < quotes.shop.slow.length; i++) {
                const quote = quotes.shop.slow[i];
                tblBody.append(generateQuoteRow(quote,"Slow"));
            }
            
        }

        tbl.append(tblBody);

        $("#p2g .results").html('');
        $("#p2g .results").append($("<h2>").text("Quote Results"));
        $("#p2g .results").append($("<p>").text("Select your quote from the list below"));
        $("#p2g .results").append($("<p>").text("The earliest delivery date will vary depending on your chosen collection/dropoff date"));
        $("#p2g .results").append(tbl); 
    }

    function generateQuoteRow(quote,speed) {
        var tblRow = $("<tr>");
        var imgHtml = "<img src='" +quote.SvgImage + "' alt='"+ quote.CourierName +"'  class='quote-row-img' />";
        tblRow.append($("<td>").html(imgHtml));
        tblRow.append($("<td>").text(quote.Service));
        tblRow.append($("<td>").text(quote.CollectionType === "Shop" ? "Drop Off" : quote.CollectionType));
        tblRow.append($("<td>").text(quote.EstimatedDeliveryDate));
        tblRow.append($("<td>").text(speed));
        tblRow.append($("<td>").text("£" + quote.Price.toFixed(2)));
        
        var btnBook = $("<button>")
            .addClass("button button-primary")
            .text("Book")
            .data("quote",quote);
        
        btnBook.click(function() {
            var btn = $(this);
            
            var quote = btn.data("quote");
            console.log("Clicked",quote);
            if(quote.CollectionType === 'Collection') {
                var slug = btn.attr("data-slug");
                getCollectionDates(quote);
            }
            else {
                getDropOffShops(quote);
            }
        });
        tblRow.append($("<td>").append(btnBook));
        return tblRow;
    }

    function getCollectionDates(quote) {
        showLoading();
        $.ajax({
            url: rootUrl + "/collection-dates",
            type: "POST",
            data: { country : defaults.CollectionAddress.Country, postcode : defaults.CollectionAddress.Postcode, slug : quote.Slug },
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
                var collectionDates = data.result;
                console.log("Collection Dates",collectionDates);
                displayCollectionDates(collectionDates, quote);
            },
            error : function(data) {
                showError(data.responseJSON.message);
            }
         });
    }

    function displayCollectionDates(collectionDates, quote) {
        var tbl = $("<table/>").addClass("wp-list-table widefat fixed striped");
        
        var tblHeader = $("<thead>");
        var tblHeaderRow = $("<tr>");
        tblHeaderRow.append($("<td>").text("Collection Date"));
        tblHeaderRow.append($("<td>").text("Book"));
        tblHeader.append(tblHeaderRow);
        tbl.append(tblHeader);

        var tblBody = $("<tbody>");

        for(var i=0; i < collectionDates.CollectionDates.length; i++) {
            var date = moment(collectionDates.CollectionDates[i].CollectionDate).format("YYYY-MM-DD");
            var tblRow = $("<tr>");
            tblRow.append($("<td>").text(date));
            
            var btnBook = $("<button>")
                .addClass("button button-primary")
                .text("Book")
                .data("quote",quote)
                .data("date",date);
            
            btnBook.click(function() {
                var btn = $(this);
                var quote = btn.data("quote");
                var date = btn.data("date");
                quote.CollectionDate = date;
                quote.ShopId = null;
                console.log("Clicked",quote);
                checkCustoms(quote);
            });
            tblRow.append($("<td>").append(btnBook));
            tblBody.append(tblRow);
        }

        tbl.append(tblBody);

        $("#p2g .results").html('');
        $("#p2g .results").append($("<h2>").text("Collection Dates"));
        $("#p2g .results").append($("<p>").text("Select the date that you would like the courier to pickup your parcel"));
        $("#p2g .results").append(tbl); 
    }

    function getDropOffShops(quote) {
        showLoading();
        $.ajax({
            url: rootUrl + "/drop-shops",
            type: "POST",
            data: { country : defaults.CollectionAddress.Country, postcode : defaults.CollectionAddress.Postcode, providerCode : quote.ProviderCode },
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
                var dropShops = data.result;
                console.log("Drop Shops",dropShops);
                displayDropshops(dropShops,quote);
            },
            error : function(data) {
                showError(data.responseJSON.message);
            }
         });
    }

    function displayDropshops(dropShops, quote) {
        var tbl = $("<table/>").addClass("wp-list-table widefat fixed striped");
        
        var tblHeader = $("<thead>");
        var tblHeaderRow = $("<tr>");
        tblHeaderRow.append($("<td>").text("Name"));
        tblHeaderRow.append($("<td>").text("Postcode"));
        tblHeaderRow.append($("<td>").text("Distance"));
        tblHeaderRow.append($("<td>").text("Directions"));
        tblHeaderRow.append($("<td>").text("Book"));
        tblHeader.append(tblHeaderRow);
        tbl.append(tblHeader);

        var tblBody = $("<tbody>");

        for(var i=0; i < dropShops.Results.length; i++) {
            var d = dropShops.Results[i]
            var tblRow = $("<tr>");
            tblRow.append($("<td>").text(d.Name));
            tblRow.append($("<td>").text(d.Postcode));
            tblRow.append($("<td>").text((d.Distance / 1000).toFixed(2) + " (km)"));
            tblRow.append($("<td>").append($("<a />").attr("href","https://www.google.com/maps/search/?api=1&query=" + d.Latitude + "," + d.Longitude).text("Directions")));
            
            var btnBook = $("<button>")
                .addClass("button button-primary")
                .text("Book")
                .data("quote",quote)
                .data("shop",d);
            
            btnBook.click(function() {
                var btn = $(this);
                var quote = btn.data("quote");
                quote.CollectionDate = moment().format("YYYY-MM-DD");
                quote.ShopId = d.Id;
                console.log("Clicked",quote);
                checkCustoms(quote);
            });
            tblRow.append($("<td>").append(btnBook));
            tblBody.append(tblRow);
        }

        tbl.append(tblBody);

        $("#p2g .results").html('');
        $("#p2g .results").append($("<h2>").text("Dropoff Shops"));
        $("#p2g .results").append($("<p>").text("Select the drop off shop that you are planning on using (it dosn't matter if you change your mind later)"));
        $("#p2g .results").append(tbl); 
    }

    function sortByPrice(a,b) {
        return a.Price-b.Price;
    }

    function checkCustoms(quote) {
        showLoading();
        $.ajax({
            url: rootUrl + "/customs",
            type: "POST",
            data: {
                slug : quote.Slug, 
                collectionDate : quote.CollectionDate, 
                shopId : quote.ShopId,
                
                value : defaults.Box.EstimatedValue, 
                weight : defaults.Box.Weight,
                length : defaults.Box.Length,
                height : defaults.Box.Height,
                width : defaults.Box.Width,
                contents : defaults.Box.ContentsSummary,
                
                collectionProperty : defaults.CollectionAddress.Property,
                collectionStreet : defaults.CollectionAddress.Street,
                collectionPostcode : defaults.CollectionAddress.Postcode,
                collectionTown : defaults.CollectionAddress.Town,
                collectionEmail : defaults.CollectionAddress.Email,
                collectionPhone : defaults.CollectionAddress.Phone,
                collectionName : defaults.CollectionAddress.ContactName,
                collectionCountry : defaults.CollectionAddress.Country,
                collectionCounty : defaults.CollectionAddress.County,


                deliveryProperty : defaults.DeliveryAddress.Property,
                deliveryStreet : defaults.DeliveryAddress.Street,
                deliveryPostcode : defaults.DeliveryAddress.Postcode,
                deliveryTown : defaults.DeliveryAddress.Town,
                deliveryEmail : defaults.DeliveryAddress.Email,
                deliveryPhone : defaults.DeliveryAddress.Phone,
                deliveryName : defaults.DeliveryAddress.ContactName,
                deliveryCountry : defaults.DeliveryAddress.Country,
                deliveryCounty : defaults.DeliveryAddress.County,
                specialInstructions  : defaults.DeliveryAddress.SpecialInstructions,

                includeCover : defaults.IncludeCover
            },
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
               console.log("Customs", data);
               if(data.result["00000000-0000-0000-0000-000000000000"].RequiresCustomsDetails) {
                displayCustoms(data.result["00000000-0000-0000-0000-000000000000"],quote);
               }
               else {
                placeOrder(quote);
               }
            },
            error : function(data) {
                showError(data.responseJSON.message);
            }
         });
    }

    function displayCustoms(customsOptions, quote) {

        customs.Required = true;

        var vatNumbercontainer = $("<p>")
            .addClass("form-field form-field-wide")
            .append($("<label>").text("VAT Number (if applicable)"));
        var txtVat = $("<input>").attr("type","text").css("width","100%").val(customs.VatNumber);
        txtVat.keyup(function() {
            customs.VatNumber = $(this).val();
        });
        vatNumbercontainer.append(txtVat);


        var vatContainer = $("<p>")
            .addClass("form-field form-field-wide")
            .append($("<label>").text("VAT Status"));

        var ddlVat = $("<select />").css("width","100%");
        var hasSelectedVatStatus = false;
        for (var key in customsOptions.VatStatus) {
            var option = $("<option />").text(customsOptions.VatStatus[key]).val(key);
            if(!hasSelectedVatStatus) {
                hasSelectedVatStatus = true;
                option.attr("selected","selected");
                customs.VatStatus = customsOptions.VatStatus[key];
            }
            ddlVat.append(option)
        }
        ddlVat.change(function() {
            customs.VatStatus = $(this).val();
        });
        vatContainer.append(ddlVat);



        var exportContainer = $("<p>")
        .addClass("form-field form-field-wide")
        .append($("<label>").text("Reasons For Export"));

        var ddlExport = $("<select />").css("width","100%");
        var hasSelectedExportReason = false;
        for (var key in customsOptions.ReasonsForExport) {
            var option = $("<option />").text(customsOptions.ReasonsForExport[key]).val(key);
            if(!hasSelectedExportReason) {
                hasSelectedExportReason = true;
                option.attr("selected","selected");
                customs.ReasonsForExport = customsOptions.ReasonsForExport[key];
            }
            ddlExport.append(option)
        }
        ddlExport.change(function() {
            customs.ReasonsForExport = $(this).val();
        });
        exportContainer.append(ddlExport);



        var countryContainer = $("<p>")
            .addClass("form-field form-field-wide")
            .append($("<label>").text("Manufactured In"))

        var ddlCountry = $("<select />").css("width","100%");
        var hasSelectedCountry = false;
        for(var i = 0; i < countries.length; i++) {
            var option = $("<option />").text(countries[i].Name).val(countries[i].Iso3Code);
            if(!hasSelectedCountry && (defaults.CollectionAddress.Country === countries[i].Iso3Code || defaults.CollectionAddress.Country === countries[i].Iso2Code)) {
                option.attr("selected","selected");
                hasSelectedCountry = true;
                customs.ManufacturedIn = countries[i].Iso3Code;
            }
            ddlCountry.append(option)
        }
        ddlCountry.change(function() {
            customs.ManufacturedIn = $(this).val();
        });
        countryContainer.append(ddlCountry);

        var btn = $("<button />").text("Submit").addClass("button button-primary");
        btn.click(function() {
            placeOrder(quote);
        });


        $("#p2g .results").html('');

        $("#p2g .results").append($("<h2>").text("Customs"));
        $("#p2g .results").append($("<p>").text("Your parcel is being sent to a country that requires additional customs information."));

        $("#p2g .results").append(vatNumbercontainer);
        $("#p2g .results").append(vatContainer);
        $("#p2g .results").append(exportContainer);
        $("#p2g .results").append(countryContainer);
        $("#p2g .results").append(btn);
    }

    function placeOrder(quote) {
        showLoading();
        $.ajax({
            url: rootUrl + "/order",
            type: "POST",
            data: {
                slug : quote.Slug, 
                collectionDate : quote.CollectionDate, 
                shopId : quote.ShopId,
                
                value : defaults.Box.EstimatedValue, 
                weight : defaults.Box.Weight,
                length : defaults.Box.Length,
                height : defaults.Box.Height,
                width : defaults.Box.Width,
                contents : defaults.Box.ContentsSummary,
                
                collectionProperty : defaults.CollectionAddress.Property,
                collectionStreet : defaults.CollectionAddress.Street,
                collectionPostcode : defaults.CollectionAddress.Postcode,
                collectionTown : defaults.CollectionAddress.Town,
                collectionEmail : defaults.CollectionAddress.Email,
                collectionPhone : defaults.CollectionAddress.Phone,
                collectionName : defaults.CollectionAddress.ContactName,
                collectionCountry : defaults.CollectionAddress.Country,
                collectionCounty : defaults.CollectionAddress.County,


                deliveryProperty : defaults.DeliveryAddress.Property,
                deliveryStreet : defaults.DeliveryAddress.Street,
                deliveryPostcode : defaults.DeliveryAddress.Postcode,
                deliveryTown : defaults.DeliveryAddress.Town,
                deliveryEmail : defaults.DeliveryAddress.Email,
                deliveryPhone : defaults.DeliveryAddress.Phone,
                deliveryName : defaults.DeliveryAddress.ContactName,
                deliveryCountry : defaults.DeliveryAddress.Country,
                deliveryCounty : defaults.DeliveryAddress.County,
                specialInstructions  : defaults.DeliveryAddress.SpecialInstructions,

                includeCover : defaults.IncludeCover,

                vatNumber : customs.Required ? customs.VatNumber : null,
                exportReason : customs.Required ? customs.ReasonsForExport : null,
                originCountry : customs.Required ? customs.ManufacturedIn : null,
                vatStatus  : customs.Required ? customs.VatStatus : null,
                summary : customs.Required ? defaults.OrderItems : null
            },
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
               console.log("Ordered", data);
               getPrePalBalance(data.result, quote);
            },
            error : function(data) {
                showError(data.responseJSON.message);
            }
         });
    }

    function getPrePalBalance(order, quote) {
        $.ajax({
            url: rootUrl + "/prepaybalance",
            type: "GET",
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
               console.log("Pre Pay Balance", data.result);
               displayPaymentOptions(order, data.result, quote);
            },
            error : function(data) {
                showError(data.responseJSON.message);
            }
         });
    }

    function displayPaymentOptions(order, balance, quote) {

        var hasPaymentOption = false;

        var tbl = $("<table/>").addClass("wp-list-table widefat fixed striped");
        
        var tblHeader = $("<thead>");
        var tblHeaderRow = $("<tr>");
        tblHeaderRow.append($("<td>").text("Payment Option"));
        tblHeaderRow.append($("<td>").text("Pay"));
        tblHeader.append(tblHeaderRow);
        tbl.append(tblHeader);

        var tblBody = $("<tbody>");

        //PrePay
        if(balance >= order.TotalPrice) {
            hasPaymentOption = true;
            var tblRow = $("<tr>");
            tblRow.append($("<td>").text("PrePay (Balance : £" + balance.toFixed(2) + ")"));
           
            var btnPay = $("<button>")
                .addClass("button button-primary")
                .text("Pay")
                .data("order",order)
                .data("quote",quote);
            
            btnPay.click(function() {
                var btn = $(this);
                var order = btn.data("order");
                var quote = btn.data("quote");
                console.log("Pay With PrePay");
                payWithPrePay(order, quote);
            });
            tblRow.append($("<td>").append(btnPay));
            tblBody.append(tblRow);
        }

        tbl.append(tblBody);

        $("#p2g .results").html('');
        
        $("#p2g .results").append($("<h2>").text("Payment Options"));
        $("#p2g .results").append($("<h3>").text("Total Cost : £" + order.TotalPrice.toFixed(2)));
        $("#p2g .results").append($("<p>").text("Select your payment option to finish and pay for your order"));

        if(balance < order.TotalPrice) {
            $("#p2g .results").append($("<h2>").text("Your PrePay balance (£"+ balance.toFixed(2) +") is less than the order cost"));
        }

        $("#p2g .results").append(tbl); 
    }

    function payWithPrePay(order, quote) {
        showLoading();
        $.ajax({
            url: rootUrl + "/paywithprepay",
            type: "POST",
            contentType: "application/json",
            data: JSON.stringify({order : order, quote : quote, internalOrderId : orderId}),
            traditional: true,
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
               console.log("Payment", data.result);
               window.location.href = siteUrl + "/wp-admin/post.php?post=" + orderId + "&action=edit"
            },
            error : function(data) {
                showError(data.responseJSON.message);
            }
         });
    }

    function payWithStoredCard(order, storedCard, quote) {
        showLoading();
        $.ajax({
            url: rootUrl + "/paywithstoredcard",
            type: "POST",
            contentType: "application/json",
            data: JSON.stringify({order : order, quote : quote, internalOrderId : orderId, storedCardId : storedCard.Id}),
            traditional: true,
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
               console.log("Payment", data.result);
               window.location.href = siteUrl + "/wp-admin/post.php?post=" + orderId + "&action=edit"
            },
            error : function(data) {
                showError(data.responseJSON.message);
            }
         });
    }

    function getBusinessDays(startDate, endDate) {
        var days = Math.round(startDate.diff(endDate, 'days') - startDate .diff(endDate, 'days') / 7 * 2);
        if (endDate.day() === 6) {
          days--;
        }
        if (startDate.day() === 7) {
          days--;
        }
        return days;
    }

    function landingPage() {
        showOverviewLoading();
        $.ajax({
            url: rootUrl + "/prepaybalance",
            type: "GET",
            beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', nonce);},
            success: function( data ) {
               console.log("Pre Pay Balance", data.result);
               displayLandingPage(data.result);
            },
            error : function(data) {
                showError(data.responseJSON.message);
            }
         });
    }

    function displayLandingPage(balance) {
        $("#p2g .overview").html('');
        $("#p2g .overview").append($('<h2 />').text("PrePay Balance"));
        if(balance === null) {
            showError({Message : "There was an error getting your PrePay Balance - Check Your API Keys"});
        }
        else {
            $("#p2g .overview").append($('<h3 />').text("You have £" + balance.toFixed(2) + " left on your account"));
            $("#p2g .overview").append($('<a/>').attr("href","https://www.parcel2go.com/myaccount/prepaid").text("You can top your account up here"));
        }
    }

    if(orderId != null) {
        getDefaults();
    }
    else {
        landingPage();
    }

})( jQuery );