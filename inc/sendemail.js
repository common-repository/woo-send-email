"use strict";
var $ = jQuery.noConflict();

function woosetc_send_completed(evt) {
	$("#woo_send_email_alert").append(" <strong>" + woosetc_ajax_object.msg_email_completed + "</strong>");
	$("#woo_send_email_notice").remove();
	$("#woo_send_email_panel_wrap").prepend('<div id="woo_send_email_notice" class="updated notice is-dismissible"><p> ' + woosetc_ajax_object.msg_email_completed + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');
	$("#woo_send_email_notice button").click(function() {$("#woo_send_email_notice").remove();});
}

function woosetc_sendmessage(evt) {
	var selectedorders = $("#the-list input[id^='cb-select-']:checked");
	var subject = $("#woo_send_email_subject").val();
	var test = $("#woo_send_email_test_option").prop("checked");
	var nonce = $("#woo_send_email_wpnonce").val();
	var custom_message = tinyMCE.get('woo_send_email_custom_content').getContent();
	if(test !== true && selectedorders.length === 0) {
		alert(woosetc_ajax_object.msg_error_no_selection);
		return(false);
	}
	if(subject === "") {
		alert(woosetc_ajax_object.msg_error_no_subject);
		return(false);
	}
	if(custom_message === "") {
		alert(woosetc_ajax_object.msg_error_no_message);
		return(false);
	}

	$("#woo_send_email_alert").html(woosetc_ajax_object.msg_email_sent_to + " ");
	if(test === true) {
		var str = {"action" : "woosetc_sendmessage", "nonce" : nonce, "subject" : subject, "custom_message" : custom_message, "test" : test};
		jQuery.ajax({
			type: "POST",
			url: woosetc_ajax_object.ajax_url,
			data: str,
			error: function(xreq) {
				alert(xreq.responseText);
			},
			success: function(res) {
				$("#woo_send_email_alert").append(res + ". ");
				woosetc_send_completed();
			}
		});
	} else {
		var cont = selectedorders.length;
		var k = 1;
		selectedorders.each(function() {
			var order_id = $(this).val();
			var str = {"action" : "woosetc_sendmessage", "nonce" : nonce, "subject" : subject, "custom_message" : custom_message, "test" : test, "order_id" : order_id};
			jQuery.ajax({
				type: "POST",
				url: woosetc_ajax_object.ajax_url,
				data: str,
				//error: function(xreq) {
					//alert(xreq.responseText);
				//},
				success: function(res) {
					$("#woo_send_email_alert").append(res + ". ");
					k++;
					if(k > cont) {
						woosetc_send_completed();
					}
					//console.log(res);
				}
			});
		});
	}
	return(false);
}

function woosetc_loadpanel() {
	$("#woo_send_email_panel_wrap").toggle();
}

jQuery(document).ready(function() {
	jQuery("#woo_send_email_openpanel").click(woosetc_loadpanel);
	jQuery("#woo_send_email_button").click(woosetc_sendmessage);
	jQuery('.alert-dismissible').on('close.bs.alert', function (e) {
		if(jQuery(this).hasClass("fade")) {
			jQuery(this).fadeOut();
		} else {
			jQuery(this).hide();
		}
		e.stopPropagation();
		return(false);
	});
	$("#woo_send_email_openpanel").insertAfter($("#post-query-submit"));
	$("#woo_send_email_panel_wrap").insertAfter($("#posts-filter .tablenav"));
});