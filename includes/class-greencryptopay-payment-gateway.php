<?php

declare(strict_types=1);

use GcpSdk\Api;
use GcpSdk\Exceptions\GcpSdkApiException;

defined('ABSPATH') || exit;

if (!class_exists('WC_Payment_Gateway')) {
    return;
}

/**
 * The functionality of the GreenCryptoPay payment gateway.
 *
 * @since      1.0.0
 * @package    GreenCryptoPay
 */
class GreenCryptoPay_Payment_Gateway extends WC_Payment_Gateway
{
    const PAY_PAGE_URL = '/greencryptopay/payment';

    const TO_CURRENCIES = [
        'btc'
    ];

    const FROM_CURRENCIES = [
        'usd'
    ];

    public $title;
    public $description;
    public $testnet;

    private $merchant_id;
    private $secret_key;
    private $number_of_confirmations;
    private $request_signature;

    /**
     * GreenCryptoPay_Payment_Gateway constructor.
     */
    public function __construct()
    {
        $this->id = 'greencryptopay';

        $this->method_title = 'Green Crypto Processing';
        $this->icon = apply_filters('woocommerce_greencryptopay_icon', GREENCRYPTOPAY_PLUGIN_URL . 'assets/bitcoin.png');

        $this->init_form_fields();

        $this->testnet = ('yes' === $this->get_option('testnet', 'no'));

        $this->merchant_id = $this->get_option('merchant_id');
        $this->secret_key = $this->get_option('secret_key');
        $this->number_of_confirmations = $this->get_option('number_of_confirmations');
        $this->request_signature = $this->get_option('request_signature');

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_api_wc_gateway_greencryptopay', array( $this, 'payment_callback'));
        add_action('woocommerce_update_options_payment_gateways_greencryptopay', array($this, 'process_admin_options' ));
    }

    /**
     * Initialise settings form fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __( 'Enable Green Crypto Processing', 'greencryptopay' ),
                'label'       => __( 'Enable Cryptocurrency payments via Green Crypto Processing', 'greencryptopay' ),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'testnet' => array(
                'title' => __('Testnet', 'greencryptopay'),
                'label' => __('Enable testnet', 'greencryptopay'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
            'merchant_id' => array(
                'title' => __('Merchant id', 'greencryptopay'),
                'type' => 'text',
                'description' => __('Set merchant id <a href="https://greencryptopay.com/ru/standard" target="_blank">see more</a>', 'greencryptopay'),
                'default' => $this->get_option('merchant_id'),
            ),
            'secret_key' => array(
                'title' => __('Secret Key', 'greencryptopay'),
                'type' => 'text',
                'description' => __('Set secret key <a href="https://greencryptopay.com/ru/standard" target="_blank">see more</a>', 'greencryptopay'),
                'default' => $this->get_option('secret_key'),
            ),
            'number_of_confirmations' => array(
                'title' => __('Number of confirmations', 'greencryptopay'),
                'type' => 'text',
                'description' => __('Specify the number of confirmations for to confirm the payment', 'greencryptopay'),
                'default' => $this->get_option('number_of_confirmations', 3),
            ),
            'title' => array(
                'title' => __('Title', 'greencryptopay'),
                'type' => 'text',
                'description' => __('The payment method title which a customer sees at the checkout of your store.', 'greencryptopay'),
                'default' => __('Cryptocurrencies via Green Crypto Processing', 'greencryptopay'),
            ),
            'description' => array(
                'title' => __('Description', 'greencryptopay'),
                'type' => 'textarea',
                'description' => __('The payment method description which a user sees at the checkout of your store.', 'greencryptopay'),
                'default' => __('Pay with BTC and other cryptocurrencies. Powered by Green Crypto Processing.', 'greencryptopay'),
            ),
            'wallet_link' => array(
                'title' => __('Wallet link', 'greencryptopay'),
                'type' => 'text',
                'description' => __('Link to open a wallet.', 'greencryptopay'),
            ),
            'time_to_pay' => array(
                'title' => __('Time to pay', 'greencryptopay'),
                'type' => 'text',
                'description' => __('Time for payment in minutes.', 'greencryptopay'),
                'default' => __(10, 'greencryptopay'),
            ),
            'request_signature' => array(
                'type' => 'hidden',
                'default' => $this->get_option('request_signature', md5(time() . random_bytes(10))),
            ),
        );
    }

    /**
     * Output the gateway settings screen.
     */
    public function admin_options()
    {
        ?>
        <h3>
            <?php
            esc_html_e('Green Crypto Processing', 'greencryptopay');
            ?>
        </h3>
        <p>
            <?php
            esc_html_e(
                'Accept Bitcoin through the Green Crypto Processing',
                'greencryptopay'
            );
            ?>
            <br>
            <a href="https://greencryptopay.com/ru/faq" target="_blank">
                <?php
                esc_html_e('Not working? Common issues');
                ?>
            </a>
            <a href="mailto:support@greencryptopay.com">support@greencryptopay.com</a>
        </p>
        <table class="form-table">
            <?php
            $this->generate_settings_html();
            ?>
        </table>
        <?php
    }

    /**
     * Payment process.
     *
     * @param int $order_id The order ID.
     * @return string[]
     *
     * @throws Exception Unknown exception type.
     */
    public function process_payment($order_id)
    {
        $result = array(
            'result' => 'fail'
        );

        $order = wc_get_order(sanitize_text_field($order_id));
        $client = $this->make_client();

        $to_currency = self::TO_CURRENCIES[0];
        $from_currency = self::FROM_CURRENCIES[0];
        $total = $order->get_total();

        try {

            $response = $client->paymentAddress(
                $to_currency,
                trailingslashit(get_bloginfo('wpurl')) . '?wc-api=wc_gateway_greencryptopay',
                (string) $order_id,
                $from_currency,
                (float) $total
            );

            update_post_meta($order_id, '_callback_secret', sanitize_text_field($response['callback_secret']));
            update_post_meta($order_id, 'payment_currency', sanitize_text_field($to_currency));
            update_post_meta($order_id, 'payment_amount', sanitize_text_field($response['amount']));
            update_post_meta($order_id, 'payment_address', sanitize_text_field($response['payment_address']));

            $query_string = [
                'order_id' => $order_id
            ];

            $query_string['signature'] = $this->makeSignature($query_string);

            $result['redirect'] = self::PAY_PAGE_URL . '?' . http_build_query($query_string);
            $result['result'] = 'success';

        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        return $result;
    }

    /**
     * @return mixed
     * @throws GcpSdkApiException
     */
    private function make_client()
    {
        if (empty($this->merchant_id)) {
            throw new Exception('The "Merchant id" parameter must be filled in the plugin settings.');
        }

        if (empty($this->secret_key)) {
            throw new Exception('The "Secret Key" parameter must be filled in the plugin settings.');
        }

        $client = Api::make('standard', $this->testnet);

        $client->setMerchantId($this->merchant_id);
        $client->setSecretKey($this->secret_key);

        return $client;
    }

    /**
     * Payment callback.
     *
     * @throws Exception Unknown exception type.
     */
    public function payment_callback()
    {
        $result = [];

        $data = json_decode(file_get_contents('php://input'), true);
        $order = wc_get_order(sanitize_text_field((int) $data['order_id']));

        if (!$order || !$order->get_id()) {
            throw new Exception('Order #' . $order->get_id() . ' does not exists');
        }

        if ($order->get_payment_method() !== $this->id) {
            throw new Exception('Order #' . $order->get_id() . ' payment method is not ' . $this->method_title);
        }

        if ($data['callback_secret'] !== $order->get_meta('_callback_secret')) {
            throw new Exception('Order #' . $order->get_id() . ' unknown error');
        }

        if ($data['currency'] !== $order->get_meta('payment_currency')) {
            throw new Exception('Order #' . $order->get_id() . ' currency does not match');
        }

        if ($order->get_status() === 'pending') {
            if ($data['amount_received'] >= $order->get_meta('payment_amount') && $data['confirmations'] >= $this->number_of_confirmations) {
                $order->payment_complete();
                $result['stop'] = true;
            } else {
                $order->add_order_note(__('A payment in the amount of ' . $data['amount_received'] . ' ' . $data['currency'] . ' was received to the payment address. Number of confirmations ' . $data['confirmations'] . '.', 'greencryptopay'));
            }
        }

        wp_send_json($result);
    }

    /**
     * @param array $requestParams
     * @return string
     */
    public function makeSignature(array $requestParams)
    {
        unset($requestParams['signature']);
        return sha1(http_build_query($requestParams) . $this->request_signature);
    }

    /**
     * @param array $requestParams
     * @throws Exception
     */
    public function checkSignature(array $requestParams)
    {
        if ($requestParams['signature'] !== $this->makeSignature($requestParams)) {
            throw new \Exception('Bad Request', 400);
        }
    }
}
