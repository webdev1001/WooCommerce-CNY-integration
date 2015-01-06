<?php
/**
 * Plugin Name: WooCommerce CNY integration
 * Description: Allow compatibility between WooCommerce, Paypal Gateway and Chinese Yuan (CNY) - USD by default
 * Version: 1.0
 * Author: Alexandre Froger
 * Author URI: http://www.crystal-asia.com
 */


defined('PAYPAL_CURRENCY') or define('PAYPAL_CURRENCY', 'USD');

add_filter('woocommerce_paypal_supported_currencies', 'WCNY_add_paypal_CNY');
/**
 * Add CNY to WooCommerce Paypal Gateway
 * @param array currencies
 * @return array currencies
 */
function WCNY_add_paypal_CNY($currencies) {

	if (!in_array('CNY', $currencies)) {
		array_push($currencies, 'CNY');
	}

	return $currencies;

}

add_filter('woocommerce_paypal_args', 'WCNY_paypal_convert_to_currency');
/**
 * Convert all the prices sent to Paypal
 * @param array paypalArgs
 * @return array paypalArgs
 */
function convert_rmb_to_usd($paypal_args){
 
    if ($paypal_args['currency_code'] == 'CNY'){
        foreach ($paypal_args as $key => $paypal_arg) {
        	if (false !== strpos($key, 'amount')) {
        		$paypal_args[$key] = WCNY_convert_price($paypal_args[$key]);
        	}
        }
    }
    $paypal_args['currency_code'] = 'USD';

    return $paypal_args;
}
add_filter('woocommerce_paypal_args', 'convert_rmb_to_usd');

add_filter('woocommerce_cart_total', 'WCNY_show_currency_on_cart');
/**
 * Show the converted price on Cart and Checkout pages of WooCommerce
 * @param string total
 * @return string total
 */
function WCNY_show_currency_on_cart($total) {

	$convertedPrice = WCNY_convert_price(WC()->cart->total);

	if ($convertedPrice != WC()->cart->total) {
		$total = $total . sprintf( __(' <span class="indicative-converted-amount">($ %s)</span>', 'woocommerce-cny'), $convertedPrice);
	}

	return $total;

}

/**
 * Convert a CNY price to the defined currency, and optionally display the currency.
 * @param float price
 * @param string currency
 * @return mixed convertedPrice
 */
function WCNY_convert_price($price, $currency = null) {

	$conversionRate = WCNY_get_CNY_to_currency_exchange_rate();
	$convertedPrice = round( $price * $conversionRate, 2);

	if ($currency !== null) {
		$convertedPrice = $currency.' '.number_format($convertedPrice, 2);
	}

	return $convertedPrice;

}

/**
 * Get the exchange rate from the European Central Bank
 * @return float exchangeRate
 */
function WCNY_get_CNY_to_currency_exchange_rate() {

	$XML = get_transient('conversions_from_ECB');

    if ($XML === false) {
        $XML = simplexml_load_file("http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml");
        $XML = $XML->asXML();
        // The file is updated daily between 2.15 p.m. and 3.00 p.m. CET, so we'll cache the value in DB for half day (can be improved depending on timezone).
        set_transient('conversions_from_ECB', $XML, 3600 * 12);
    }

    $XML = simplexml_load_string($XML);

    if ($XML !== false) {
    	
    	$rateCNYtoEURO = $rateEUROtoCurrency = 0;

	    foreach ($XML->Cube->Cube->Cube as $rate) { 
	        if ($rate['currency'] == 'CNY') {
	            $rateCNYtoEURO = 1 / floatval($rate["rate"]);
	        }
	        if ($rate['currency'] == PAYPAL_CURRENCY) {
	            $rateEUROtoCurrency = floatval($rate["rate"]);
	        }
	        if ($rateCNYtoEURO != 0 && $rateEUROtoCurrency != 0) {
	            break;
	        }
	    } 
	    $exchangeRate = $rateCNYtoEURO * $rateEUROtoCurrency;

    } else {
    	$exchangeRate = 1;
    }

	return $exchangeRate;

}