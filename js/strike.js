jQuery(function($){
	$(document).ready(function() {
		localStorage.removeItem('paymentinvoiceId');
	});

	$(document).on("click", '#paymentRequestRefresh', function() {
		 localStorage.removeItem('paymentinvoiceId');
		 pay();
	});

	$(document).on("click", 'li[data-slide="0"]', function() {
		$('#paymentRequestInvoiceCopy').html('Copy');
		invoice = document.getElementById('lnQrcode').title
		$("#paymentRequestInvoiceCopy").attr('data-clipboard-text',invoice);
	});
	$(document).on("click", 'li[data-slide="1"]', function() {
		$('#paymentRequestInvoiceCopy').html('Copy');
		invoice = document.getElementById('onChainQrcode').title
		$("#paymentRequestInvoiceCopy").attr('data-clipboard-text',invoice);
	});

	function pay() {

		// Remove any expired class
		$("#QrSlider").removeClass('qrCodeExpired')
		$("#QrCodeLoader, .unslider-nav").html('')
		var strikeApiKey = strike_params.strikeApiKey
		var strikeCurrency = strike_params.strikeCurrency

		var orderTotal = $("#QrSlider").data("order-total")
		var issueInvoiceEndpoint = "https://api.strike.me/v1/invoices"

		// hide the refresh button
		$('#paymentRequestRefresh').hide()
		$.ajax({
	    type: "post",
			contentType: "application/json; charset=utf-8",
			headers: {
				'Authorization': 'Bearer ' + strikeApiKey,
			},
	    url: issueInvoiceEndpoint,
			data: JSON.stringify({
				amount: {
					'amount': orderTotal,
					'currency': strikeCurrency,
				},
				description: "Wordpress Order Payment"
			}),
	    success: function (data) {
        var invoice = data;
				var issueQuoteEndpoint = "https://api.strike.me/v1/invoices/" + invoice.invoiceId + "/quote"
				var requestData = JSON.stringify({
					invoiceId: invoice.invoiceId,
					amount: {
						'amount': orderTotal,
						'currency': strikeCurrency,
					},
					description: "Wordpress Order Payment"
				});

				$.ajax({
						type: "post",
						contentType: "application/json; charset=utf-8",
						headers: {
				      'Authorization': 'Bearer ' + strikeApiKey,
				    },
						data: requestData,
						url: issueQuoteEndpoint,
						success:function(data)
						{
								var onchainAddress = data.onchainAddress ? data.onchainAddress : null;
								var lnInvoice = data.lnInvoice ? data.lnInvoice : null;
								var quoteExpiration = data.expirationInSec

								if (lnInvoice) {
									$("#paymentRequestInvoiceCopy").attr('data-clipboard-text',lnInvoice);
									// Also add link to QR codes
									$("#lnQrcodeLink").attr('href',"lightning:" + lnInvoice);
									var lnOptions = {
										width: 150,
										height: 150,
										colorDark : "#000000",
										colorLight : "#ffffff",
										text: lnInvoice,
										tooltip: true,
										drawer: "svg",
										correctLevel: QRCode.CorrectLevel.H
									};

									// Create QRCode Object
									$('#lnQrcode').empty();
									new QRCode(document.getElementById("lnQrcode"), lnOptions);
									$("#lnQrcodeAmount").text('$' + data.targetAmount['amount']);
								}

								if (onchainAddress) {
									$("#onChainQrcodeLink").attr('href',"bitcoin:" + onchainAddress);
									var btcOptions = {
										width: 150,
										height: 150,
										colorDark : "#000000",
										colorLight : "#ffffff",
										text: "bitcoin:" + onchainAddress + "?amount=" + parseFloat(data.sourceAmount['amount']),
										tooltip: true,
										drawer: "svg",
										correctLevel: QRCode.CorrectLevel.H
									};

									// Create QRCode Object
									$('#onChainQrcode').empty();
									new QRCode(document.getElementById("onChainQrcode"), btcOptions);
									$("#onChainQrcodeAmount").text(data.sourceAmount['amount'] + ' BTC');
								}

								//Send another request in 1 second.
								if (invoice.invoiceId) {
									localStorage.setItem('paymentinvoiceId',invoice.invoiceId);
									checkStatus(invoice.invoiceId, quoteExpiration)
								}
						},
						beforeSend: function () {
						  $("#qrCodeLoader").addClass("loading");
						},
					  complete: function () {
							$("#paymentRequestInvoiceCopy").show();
							$("#paymentInfo").show();

							$("#qrCodeLoader").removeClass("loading");
							var invoiceSlider = $('.QrCodesSlider').unslider({
								keys: true,
								dots: true,
								arrows: false,
							});

							invoiceSlider.unslider('calculateSlides')

							$(".li").click(function() {
						    slider.unslider('next');
						  });

							// bind the clipboard copy element
							var clipboard = new ClipboardJS('#paymentRequestInvoiceCopy');
							clipboard.on('success', function(e) {
								$('#paymentRequestInvoiceCopy').html('<svg height="15" viewBox="0 0 15 11" width="15"><path d="M14 1L7.178 9.354c-.584.715-1.69.858-2.47.32a1.498 1.498 0 01-.186-.148L1 6.292" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>');
								setTimeout(function(){
										$('#paymentRequestInvoiceCopy').html('Copy');
								}, 5000);

								e.clearSelection();
							});
					  }
				});
			}
		});
	}


	// Check for status of the quote.
	function checkStatus(invoiceId, quoteExpiration) {
		var paymentinvoiceId = localStorage.getItem("paymentinvoiceId");
		var strikeApiKey = strike_params.strikeApiKey
		if (!paymentinvoiceId) {
			return false;
		}
		var invoiceStatus = "https://api.strike.me/v1/invoices/" + invoiceId

		$.ajax({
				type: "get",
				url: invoiceStatus,
				headers: {
					'Authorization': 'Bearer ' + strikeApiKey,
				},
				success:function(data)
				{
						//Send another request in 1 second.
						if (quoteExpiration < 1) {
							// delete the stored invoiceId on expiry
							localStorage.removeItem('paymentinvoiceId');
							$("#paymentRequestRefresh").show();
							$("#paymentRequestInvoiceCopy").hide();
							$("#paymentInfo").hide();

							// Add class to QrSlider for bluring the request and freeze
							$("#QrSlider").addClass('qrCodeExpired')
							// Add overlay message of expiration
							$("#QrCodeLoader").html('Expired')

						}
						if (data.state === 'PAID') {
							// Set the paid invoice Id in order metadata
							$("#strikInvoiceId").val(invoiceId);
							localStorage.removeItem('paymentinvoiceId');
							$( 'form.checkout' ).submit();

							// Submit order with strike invoice
						}

						if (quoteExpiration >= 1) {
							$("#paymentInfo").show();
							$("#expirySecond").html(quoteExpiration);
							checkstatus = setTimeout(function() {
									checkStatus(invoiceId, --quoteExpiration);
							}, 1000);
						}
				}
		});
	}


	 $('body').on('checkout_error', function() {
		 // get the data between info
		 var paymentInfo = $('.woocommerce-NoticeGroup-checkout .woocommerce-info').html();
		 if (paymentInfo && paymentInfo.includes('QR code')) {
			 displayMode = strike_params.displayMode ? strike_params.displayMode : 'dark'
			 $("div.payment_method_strike").addClass('payment-method-strike-' + displayMode)
			 pay();
		 }
	 });
});
