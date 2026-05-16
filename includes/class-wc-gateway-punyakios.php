<?php
/**
 * PunyaKios Gateway Class for WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class PunyaKios_WC_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'punyakios';
        $this->icon = plugin_dir_url(dirname(__FILE__)) . 'assets/logo.png';
        $this->has_fields = false;
        $this->method_title = 'PunyaKios (QRIS)';
        $this->method_description = 'Accept automated QRIS payments via PunyaKios.';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_key = $this->get_option('api_key');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_gateway_punyakios', array($this, 'check_callback'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable PunyaKios Payment',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'default' => 'QRIS (PunyaKios)',
                'description' => 'Payment method name shown during checkout.'
            ),
            'api_key' => array(
                'title' => 'API Key Merchant',
                'type' => 'password',
                'description' => 'Get your API Key from the PunyaKios Merchant Dashboard.'
            ),
            'order_status' => array(
                'title' => 'Order Status After Paid',
                'type' => 'select',
                'options' => array(
                    'processing' => 'Processing',
                    'completed' => 'Completed'
                ),
                'default' => 'processing',
                'description' => 'Order status after payment is successfully received.'
            ),
            'debug' => array(
                'title' => 'Debug Log',
                'type' => 'checkbox',
                'label' => 'Enable Logging',
                'default' => 'no',
                'description' => 'Save activity logs to WooCommerce > Status > Logs.'
            )
        );
    }

    public function log($message) {
        if ($this->get_option('debug') === 'yes') {
            if (empty($this->logger)) {
                $this->logger = wc_get_logger();
            }
            $this->logger->info($message, array('source' => 'punyakios-gateway'));
        }
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        require_once plugin_dir_path(__FILE__) . 'PunyaKios.php';
        $sdk = new \PunyaKios\PunyaKios($this->api_key);

        try {
            $this->log('Creating payment for Order #' . $order_id);
            $response = $sdk->createPaymentRequest([
                'external_id' => (string)$order_id,
                'amount' => (int)$order->get_total(),
                'description' => 'Order #' . $order_id . ' at ' . get_bloginfo('name'),
                'callback_url' => WC()->api_request_url('WC_Gateway_PunyaKios')
            ]);

            if ($response['status_code'] === 200) {
                return array(
                    'result' => 'success',
                    'redirect' => $response['data']['data']['checkout_url']
                );
            } else {
                wc_add_notice('PunyaKios API Error: ' . esc_html($response['data']['message']), 'error');
                return;
            }
        } catch (Exception $e) {
            wc_add_notice('System Error: ' . esc_html($e->getMessage()), 'error');
            return;
        }
    }

    public function check_callback() {
        require_once plugin_dir_path(__FILE__) . 'PunyaKios.php';
        $data = \PunyaKios\PunyaKios::parseCallback();

        if ($data && isset($data['external_id']) && $data['status'] === 'PAID') {
            $order = wc_get_order($data['external_id']);
            if ($order) {
                $this->log('Callback received for Order #' . $data['external_id'] . ' - Status: PAID');
                $status = $this->get_option('order_status', 'processing');
                $order->update_status($status, 'Paid via PunyaKios QRIS.');
                $order->payment_complete();
            }
            status_header(200);
            exit('OK');
        }
        status_header(400);
        exit('Invalid Callback');
    }
}
