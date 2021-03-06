var CORE_JS_PREFIX = "";

function runParser(input_str,rel_prefix){
	CORE_JS_PREFIX = rel_prefix;
	$.ajax({
		url: CORE_JS_PREFIX+'ajax-callbacks/AjaxParser.php',
		type: 'GET',
		data: "input="+input_str,
		dataType: "json",
		cache: false,
		error: parserError,
		success: parserHandler
	});
}

function parserError()
{
}

function customerWindowHtml(selector, content)
{
    if (typeof customerWindow !== 'undefined' && $.isWindow(customerWindow)) {
        customerWindow.$(selector).html(content);
    }
}

function parserHandler(data)
{
	if (data.main_frame){
		window.location = data.main_frame;
		return;
	} else {
		if (data.output) {
			$(data.target).html(data.output);
            customerWindowHtml(data.target, data.output);
        }
	}

	if (data.redraw_footer){
		$('#footer').html(data.redraw_footer);
        customerWindowHtml('#footer', data.redraw_footer);
	}

	if (data.scale){
		$('#scaleBottom').html(data.scale);
        customerWindowHtml('#scaleBottom', data.scale);
	}

	if (data.term){
		$('#scaleIconBox').html(data.term);
        customerWindowHtml('#scaleIconBox', data.term);
	}

	if (data.receipt){
		$.ajax({
			url: CORE_JS_PREFIX+'ajax-callbacks/ajax-end.php',
			type: 'GET',
			data: 'receiptType='+data.receipt+'&ref='+data.trans_num,
			dataType: 'json',
			cache: false,
            error: function() {
                var icon = $('#receipticon').attr('src');
                var newicon = icon.replace(/(.*graphics)\/.*/, "$1/deadreceipt.gif");
                $('#receipticon').attr('src', newicon);
            },
			success: function(data){
				if (data.sync){
					ajaxTransactionSync(CORE_JS_PREFIX);
				}
                if (data.error) {
                    var icon = $('#receipticon').attr('src');
                    var newicon = icon.replace(/(.*graphics)\/.*/, "$1/deadreceipt.gif");
                    $('#receipticon').attr('src', newicon);
                }
			}
		});
	}

	if (data.retry){
		setTimeout("runParser('"+encodeURI(data.retry)+"','"+CORE_JS_PREFIX+"');",150);
	}
}

function ajaxTransactionSync(rel_prefix){
	$.ajax({
		url: rel_prefix+'ajax-callbacks/ajax-transaction-sync.php',
		type: 'GET',
		cache: false,
	});

}
