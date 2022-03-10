// jQuery(function($) {
// 	// Populate user available currencies
// 	$('input[name ="woocommerce_strike_username"]').focusout(function() {
// 		$('#woocommerce_strike_currency').empty();
// 		var username = document.getElementsByName("woocommerce_strike_username")[0].value;
// 		var apiKey = document.getElementsByName("woocommerce_strike_apikey")[0].value;
//
// 		var getAccountInfo = "https://api.strike.me/v1/accounts/handle/" + username + "/profile"
// 		var select = $("#woocommerce_strike_currency")[0];
// 		$.ajax({
// 			type: "get",
// 			contentType: "application/json; charset=utf-8",
// 			headers: {
// 				'Authorization': 'Bearer ' + apiKey,
// 			},
// 			url: getAccountInfo,
// 			success: function (data) {
// 				var accountDetails = data;
// 				if (accountDetails.canReceive && (accountDetails.currencies.length !== 0)) {
// 					// get all available currencies and set the options
// 					var currencies = []
// 					accountDetails.currencies.forEach(function (item, index) {
// 					  if(item.isAvailable) {
// 							select.add(new Option(item.currency, item.currency));
// 						}
// 					});
// 				}
// 			}
// 		});
// 	});
// });
