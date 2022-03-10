Bitcoin Payments - Powered by Strike

Allows user to do bitcoin payments on your WooCommerce website using Strike API. 
[Strike Js](https://github.com/rahulbile/strike-js) is used for generating the QR Code. 

# Description

Plugin adds a payment gateway for WooCommerce enabled stores to accept payment in bitcoin, powered by strike (strike.me)
User is provided with a lightning invoice for payment via QR code.

# Installation

1. Activate the plugin
2. Under settings of the plugin set the strike account API key to which payment should be received.
4. Set the preferred currency.

## NOTE REGARDING API KEY :
For now its suggested to get a API key from Strike Account Manager with following limited scope :
* partner.invoice.quote.generate
* partner.invoice.read
* partner.invoice.create
* partner.account.profile.read
    
In next release user will be able to authenticate via strike oAuth, generate a key and auto-populate in settings.

Current version also supports API procy via wordpress internal end points so that API key is not passed via JS. For that purpose the API URl should be set to 
https://<YOURWEBSITE.com>/wp-json/strikeapi/v1, caching should be disabled for rest API.

## Screenshots

  - Settings Form

  ![woocommerceStrikeSettings.png](/assets/images/woocommerceStrikeSettings.png?raw=true "Payment Gateway Settings")


  - Checkout Payment Option

  ![woocommerceStrikeCheckoutOption.png](/assets/images/woocommerceStrikeCheckoutOption.png?raw=true "Payment Gateway Settings")


  - Payment Lightning QR Code

  ![woocommerceStrikeQRPayment.png](/assets/images/woocommerceStrikeQRPayment.png?raw=true "Payment Gateway Settings")
