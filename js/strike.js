var $ = jQuery;

jQuery(function($) {
	$('body').on('checkout_error', function() {
		// get the data between info
		var paymentInfo = $('.woocommerce-NoticeGroup-checkout .woocommerce-info').html();
		if (paymentInfo && paymentInfo.includes('QR code')) {

			var strikeApiKey = strike_params.strikeApiKey
			var strikeApiUrl = strike_params.strikeApiUrl
			var strikeCurrency = strike_params.strikeCurrency
			var pluginVersion = strike_params.pluginVersion
			var orderTotal = $("#strikeInvoiceCard").data("order-total")


			strikeJS.generateInvoice({
				'debug': true,
				'element': '#strikeInvoiceCard',
				'amount': parseFloat(orderTotal),
				'currency': strikeCurrency,
				'correlationId': 'wordpress-' + pluginVersion,
				'redirectCallback': 'paymentSuccessCalback',
				'apiUrl': strikeApiUrl,
				'apiKey': strikeApiKey
			});
		}
	});
});
function paymentSuccessCalback(response) {
	$("#strikeInvoiceId").val(response.invoiceId);
	$( 'form.checkout' ).submit();
}
