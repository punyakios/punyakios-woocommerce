<?php
/**
 * Plugin Name: PunyaKios Payment Gateway for WooCommerce
 * Plugin URI: https://punyakios.web.id/docs
 * Description: Terima pembayaran QRIS PunyaKios di toko WooCommerce Anda.
 * Version: 1.0.0
 * Author: PunyaKios Team
 * Author URI: https://punyakios.web.id
 * License: MIT
 */

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'punyakios_wc_init');

function punyakios_wc_init() {
    if (!class_exists('WC_Payment_Gateway')) return;

    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-punyakios.php';

    add_filter('woocommerce_payment_gateways', 'punyakios_add_gateway');
    function punyakios_add_gateway($methods) {
        $methods[] = 'PunyaKios_WC_Gateway';
        return $methods;
    }
}
