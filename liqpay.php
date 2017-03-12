<?php
/*
Plugin Name: WooCommerce Liqpay Gateway
Plugin URI: https://github.com/ekscentrysytet/wc-liqpay-gateway
Description: Extends WooCommerce gateways with a Liqpay gateway.
Version: 1.0
Author: Nazar Ilkiv
Author URI: https://github.com/ekscentrysytet
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

include_once 'class-wc-gateway-liqpay.php';

add_action( 'plugins_loaded', 'woocommerce_init_liqpay', 0 );

function woocommerce_add_liqpay_gateway( $methods ) {
    $methods[] = 'WC_Gateway_Liqpay';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'woocommerce_add_liqpay_gateway' );