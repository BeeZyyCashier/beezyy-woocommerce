<?php
/*
Plugin Name: Beezyy
Plugin URI: https://beezyycashier.com/
Description: Beezyy payments
Author URI: https://beezyycashier.com/
Version: 1.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
*/


if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'woocommerce_beezyy', 0);

/**
 *
 */
function woocommerce_beezyy()
{
    load_plugin_textdomain('beezyy-woocommerce', false, plugin_basename(dirname(__FILE__)) . '/languages');

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    if (class_exists('WC_Beezyy')) {
        return;
    }

    class WC_Beezyy extends WC_Payment_Gateway
    {
        /**
         * @var int
         */
        public $payment_method;

        /**
         * @var string
         */
        public $apiUrl;
        /**
         * @var string
         */
        private $secret_key;
        /**
         * @var string
         */
        private $skip_confirm;
        /**
         * @var string
         */
        private $lifetime;
        /**
         * @var string
         */
        private $auto_complete;
        /**
         * @var string
         */
        private $use_iframe;
        /**
         * @var string
         */
        private $iframe_width;
        /**
         * @var string
         */
        private $iframe_height;

        public function __construct()
        {
            $plugin_dir = plugin_dir_url(__FILE__);

            $this->apiUrl = 'https://api.beezyycashier.com/v1/';
            $this->payment_method = null;
            $this->id = 'beezyy';
            $this->icon = apply_filters('woocommerce_beezyy_icon', '' . $plugin_dir . 'beezyy.svg');
            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->secret_key = $this->get_option('secret_key');
            $this->skip_confirm = $this->get_option('skip_confirm');
            $this->lifetime = $this->get_option('lifetime');
            $this->auto_complete = $this->get_option('auto_complete');
            $this->description = $this->get_option('description');
            $this->use_iframe = $this->get_option('use_iframe');
            $this->iframe_width = $this->get_option('iframe_width');
            $this->iframe_height = $this->get_option('iframe_height');

            add_action('beezyy-ipn-request', [$this, 'successful_request']);
            add_action('woocommerce_update_options_payment_gateways_beezyy', [$this, 'process_admin_options']);

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        public function add_actions()
        {
            add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
            add_action('woocommerce_api_wc_' . $this->id, [$this, 'check_ipn_response']);
        }

        public function is_valid_for_use(): bool
        {
            return true;
        }

        public function admin_options()
        {
            ?>
            <h3><?php
                _e('Beezyy', 'beezyy-woocommerce'); ?></h3>
            <p><?php
                _e('Take payments via Beezyy.', 'beezyy-woocommerce'); ?></p>

            <?php
            if ($this->is_valid_for_use()) : ?>

                <table class="form-table">
                    <?php
                    // Generate the HTML For the settings form.
                    $this->generate_settings_html();
                    ?>
                </table>

            <?php
            else : ?>
                <div class="inline error">
                    <p>
                        <strong><?php
                            _e('Gateway is disabled', 'beezyy-woocommerce'); ?></strong>:
                        <?php
                        _e('Beezyy does not support the currency of your store.', 'beezyy-woocommerce'); ?>
                    </p>
                </div>
            <?php
            endif;
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('Enable Beezyy payments', 'beezyy-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable/Disable', 'beezyy-woocommerce'),
                    'default' => 'yes',
                ],
                'title' => [
                    'title' => __('Name of payment gateway', 'beezyy-woocommerce'),
                    'type' => 'text',
                    'description' => __('The name of the payment gateway that the user see when placing the order',
                        'beezyy-woocommerce'),
                    'default' => __('Beezyy', 'beezyy-woocommerce'),
                ],
                'secret_key' => [
                    'title' => __('Secret key', 'beezyy-woocommerce'),
                    'type' => 'text',
                    'description' => __('Issued in the client panel https://beezyycashier.com', 'beezyy-woocommerce'),
                    'default' => '',
                ],
                'payment_method_id' => [
                    'title' => __('Payment method ID', 'beezyy-woocommerce'),
                    'type' => 'text',
                    'default' => '',
                ],
                'description' => [
                    'title' => __('Description', 'beezyy-woocommerce'),
                    'type' => 'textarea',
                    'description' => __(
                        'Description of the payment gateway that the client will see on your site.',
                        'beezyy-woocommerce'
                    ),
                    'default' => __('Accept online payments using beezyycashier.com', 'beezyy-woocommerce'),
                ],
                'auto_complete' => [
                    'title' => __('Order completion', 'beezyy-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __(
                        'Automatic transfer of the order to the status "Completed" after successful payment',
                        'beezyy-woocommerce'
                    ),
                    'default' => '1',
                ],
                'use_iframe' => [
                    'title' => __('Use iframe', 'beezyy-woocommerce'),
                    'type' => 'checkbox',
                    'default' => '1',
                ],
                'iframe_width' => [
                    'title' => __('iframe width', 'beezyy-woocommerce'),
                    'type' => 'text',
                    'default' => '800',
                ],
                'iframe_height' => [
                    'title' => __('iframe height', 'beezyy-woocommerce'),
                    'type' => 'text',
                    'default' => '600',
                ],
                'skip_confirm' => [
                    'title' => __('Skip confirmation', 'beezyy-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __(
                        'Skip page checkout confirmation',
                        'beezyy-woocommerce'
                    ),
                    'default' => 'yes',
                ],
                'payment_form_language' => [
                    'title' => __('Payment form language', 'beezyy-woocommerce'),
                    'type' => 'select',
                    'description' => __('Select the language of the payment form for your store', 'beezyy-woocommerce'),
                    'default' => 'en',
                    'options' => [
                        'en' => __('English', 'beezyy-woocommerce'),
                        'ru' => __('Russian', 'beezyy-woocommerce'),
                    ],
                ],
            ];
        }

        public function generate_form($order_id)
        {
            $order = new WC_Order($order_id);

            $requestData = [
                'reference' => strval($order_id),
                'amount' => number_format($order->get_total(), 2, '.', ''),
                'currency' => $order->get_currency(),
                'payment_method' => $this->payment_method,
                'customer' => [
                    'email' => $order->get_billing_email(),
                    'country' => $order->get_billing_country(),
                    'state' => $order->get_billing_state(),
                    'zipcode' => $order->get_billing_postcode(),
                    'city' => $order->get_billing_city(),
                    'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'ip' => $order->get_customer_ip_address(),
                ],
                'urls' => [
                    'success' => get_site_url() . "/?wc-api=wc_beezyy&beezyy=success&orderId={$order_id}",
                    'fail' => get_site_url() . "/?wc-api=wc_beezyy&beezyy=fail&orderId={$order_id}",
                    'notification' => get_site_url() . '?wc-api=wc_beezyy&beezyy=result',
                ],
            ];
            if ($order->get_billing_phone() && empty($response['data']['fields'])) {
                $requestData['customer']['phone'] = $order->get_billing_phone();
            }

            $response = $this->apiRequest('payment/create', $requestData);
            if (isset($response['message'])) {
                return '<p>' . __('Request to payment service was sent incorrectly', 'beezyy-woocommerce')
                    . '</p><br><p>' . $response['message'] . '</p>';
            }

            if ($this->use_iframe === 'yes') {
                $result = '<iframe src="' . $response['data']['url']
                    . '" width="' . $this->iframe_width . '" height="' . $this->iframe_height
                    . '" style="border: 0"></iframe>';
            } else {
                $result = '<form action="' . esc_url($response['data']['url'])
                    . '" method="' . strtoupper($response['data']['url']) . '" id="beezyy_payment_form">';

                if (!empty($response['data']['fields'])) {
                    foreach ($response['data']['fields'] as $k => $v) {
                        $result .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />';
                    }
                }

                $result .= '<input type="submit" class="button alt" id="submit_beezyy_payment_form" value="'
                    . __('Pay', 'beezyy-woocommerce') . '" />'
                    . '<a class="button cancel" href="'
                    . $order->get_cancel_order_url() . '"> ' . __('Refuse payment & return to cart',
                        'beezyy - woocommerce')
                    . '</a></form> ';
            }

            return $result;
        }

        public function process_payment($order_id): array
        {
            $order = new WC_Order($order_id);
            $order_key = $order->get_order_key();

            return [
                'result' => 'success',
                'redirect' => add_query_arg(
                    'order',
                    $order->get_id(),
                    add_query_arg('key', $order_key, $order->get_checkout_order_received_url())
                ),
            ];
        }

        public function receipt_page($order)
        {
            if ($this->use_iframe !== 'yes') {
                echo ' <p>' . __('Thank you for your order, please click the button below to pay',
                        'beezyy - woocommerce')
                    . ' </p> ';
            }

            echo $this->generate_form($order);
        }

        public function check_ipn_request_is_valid(array $posted)
        {
            $paymentId = $posted['payment_id'] ?? null;
            $orderId = $posted['reference'] ?? null;
            if (!$paymentId) {
                return 'empty payment id';
            }
            if (!$orderId) {
                return 'empty order id';
            }

            $state = $posted['status'] ?? null;

            if ($state !== 'success' && $state !== 'fail') {
                return 'State is not valid';
            }

            return true;
        }

        public function check_ipn_response()
        {
            $requestType = $_GET['beezyy'] ?? '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $postedData = json_decode(file_get_contents('php://input'), true);
                if (!is_array($postedData)) {
                    $postedData = [];
                }
            } else {
                $postedData = $_GET;
            }

            switch ($requestType) {
                case 'result':
                    @ob_clean();

                    $postedData = wp_unslash($postedData);
                    $valid = $this->check_ipn_request_is_valid($postedData);
                    if ($valid === true) {
                        $response = $this->apiRequest('payment/' . $postedData['payment_id']);

                        if ($postedData['reference'] !== $response['data']['reference']) {
                            wp_die('Order id is wrong', 'Order id is wrong', 200);
                        }
                        $orderId = $postedData['reference'];
                        $order = new WC_Order($orderId);
                        if ($order->get_currency() !== $response['data']['currency']) {
                            wp_die('Currency is wrong', 'Currency is wrong', 200);
                        }
                        if ($order->get_total() === $response['data']['amount']) {
                            wp_die('Amount is wrong', 'Amount is wrong', 200);
                        }
                        if ($response['data']['status'] === 'success') {
                            if ($this->auto_complete === 'yes') {
                                $order->update_status('completed',
                                    __('Payment successfully paid', 'beezyy-woocommerce'));
                            } else {
                                $order->update_status('processing',
                                    __('Payment successfully paid', 'beezyy-woocommerce'));
                            }
                            wp_die('Status success', 'Status success', 200);
                        } elseif ($response['data']['status'] === 'fail') {
                            $order->update_status('failed', __('Payment not paid', 'beezyy-woocommerce'));
                            wp_die('Status fail', 'Status fail', 200);
                        }
                        do_action('beezyy-ipn-request', $postedData);
                    } else {
                        wp_die($valid, $valid, 400);
                    }
                    break;
                case 'success':
                    $orderId = $postedData['transaction']['order']['id'] ?? $postedData['orderId'];

                    $order = new WC_Order($orderId);

                    WC()->cart->empty_cart();

                    wp_redirect($this->get_return_url($order));
                    break;
                case 'fail':
                    $orderId = $postedData['transaction']['order']['id'] ?? $postedData['orderId'];
                    $order = new WC_Order($orderId);
                    wp_redirect($order->get_cancel_order_url_raw());
                    break;
                default:
                    wp_die('Invalid request', 'Invalid request', 400);
            }
        }

        public function successful_request($posted)
        {
            $orderId = $posted['transaction']['order']['id'] ?? $posted['orderId'];

            $order = new WC_Order($orderId);

            if ($order->get_status() === 'completed') {
                exit;
            }

            $order->add_order_note(__('Payment completed successfully', 'beezyy-woocommerce'));

            $order->payment_complete();
            exit;
        }

        public function apiRequest(string $path, array $data = [])
        {
            $request_url = $this->apiUrl . $path;
            $args = [
                'sslverify' => false,
                'timeout' => 45,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->secret_key,
                ],
            ];
            if ($data) {
                $args['body'] = json_encode($data);
            }
            $response = $data
                ? wp_remote_post($request_url, $args)
                : wp_remote_get($request_url, $args);
            $response = wp_remote_retrieve_body($response);

            return json_decode($response, true);
        }

    }

    class WC_Beezyy_Card extends WC_Beezyy
    {
        public function __construct()
        {
            parent::__construct();
            $this->title = $this->get_option('title');
            $this->id = 'beezyy';
            $this->payment_method = $this->get_option('payment_method_id');
            self::add_actions();
        }
    }

    function add_beezyy_gateway($methods)
    {
        $methods[] = 'WC_Beezyy_Card';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_beezyy_gateway');

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_beezyy_plugin_page_settings_link');

    function add_beezyy_plugin_page_settings_link($links)
    {
        $links[] = '<a href="' .
            admin_url('admin.php?page=wc-settings&tab=checkout&section=beezyy') .
            '">' . __('Settings') . '</a>';

        return $links;
    }

    function do_iframe_redirect()
    {
        ?>
        <script>
            function inIframe() {
                try {
                    return window.self !== window.top;
                } catch (e) {
                    return true;
                }
            }

            if (inIframe()) {
                window.top.location = document.location.href;
            }

        </script>
        <?php
    }

    add_action('wp_head', 'do_iframe_redirect');
}
