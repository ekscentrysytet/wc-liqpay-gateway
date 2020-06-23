<?php

if ( !defined( 'ABSPATH' ) ) exit;

function woocommerce_init_liqpay() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_Gateway_Liqpay extends WC_Payment_Gateway {

        private $_checkout_url          = 'https://www.liqpay.ua/api/3/checkout';
        protected $_supportedCurrencies = array( 'EUR', 'UAH', 'USD', 'RUB', 'RUR' );
        private $_public_key;
        private $_private_key;
        private $_serverUrl;
        private $_resultUrl;

        public function __construct() {

            $this->id                 = 'liqpay';
            $this->has_fields         = false;
            $this->icon               = apply_filters( 'woocommerce_liqpay_icon', plugin_dir_url(__FILE__) . '/assets/img/liqpay.png' );
            $this->method_title       = __( 'Liqpay', 'wc-gateway-liqpay' );
            $this->method_description = __( 'Liqpay', 'wc-gateway-liqpay' );
            $this->init_form_fields();
            $this->init_settings();
            $this->title              =  $this->get_option( 'title' );
            $this->description        =  $this->get_option( 'description' );
            $this->_public_key        =  $this->get_option( 'public_key' );
            $this->_private_key       =  $this->get_option( 'private_key' );
            $this->fee                =  $this->get_option( 'fee' );
            $this->skip_submit        =  $this->get_option( 'skip_submit' );
            $this->sandbox            =  $this->get_option( 'sandbox' );
            $this->_serverUrl         =  add_query_arg( 'wc-api', 'WC_Gateway_Liqpay', home_url( '/' ) );

            // Actions
            add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
            }

            // Payment listener/API hook
            add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'check_ipn_response' ) );
        }

        public function admin_options() {
          ?>

          <h3><?php _e( 'Liqpay', 'wc-gateway-liqpay' ); ?></h3>

          <table class="form-table">
              <?php $this->generate_settings_html(); ?>
          </table>

        <?php }

        public function init_form_fields() {

            $this->form_fields = array(
                'enabled'     => array(
                    'title'       => __( 'Включить/Выключить', 'wc-gateway-liqpay' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Включить', 'wc-gateway-liqpay' ),
                    'default'     => 'yes'
                ),
                'title'       => array(
                    'title'       => __( 'Заголовок', 'wc-gateway-liqpay' ),
                    'type'        => 'text',
                    'description' => __( 'Заголовок, который отображается на странице оформления заказа.', 'wc-gateway-liqpay' ),
                    'default'     => __( 'Liqpay', 'wc-gateway-liqpay' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Описание', 'wc-gateway-liqpay' ),
                    'type'        => 'textarea',
                    'description' => __( 'Описание, которое отображается в процессе выбора формы оплаты.', 'wc-gateway-liqpay' ),
                    'default'     => __( 'Оплатить через электронную платежную систему Liqpay', 'wc-gateway-liqpay' ),
                ),
                'public_key'  => array(
                    'title'       => __( 'Public key', 'wc-gateway-liqpay' ),
                    'type'        => 'text',
                    'description' => __( 'Публичный ключ - идентификатор магазина. Получить ключ можно в личном кабинете Liqpay.', 'wc-gateway-liqpay' ),
                ),
                'private_key' => array(
                    'title'       => __( 'Private key', 'wc-gateway-liqpay' ),
                    'type'        => 'text',
                    'description' => __( 'Приватный ключ. Получить ключ можно в личном кабинете Liqpay.', 'wc-gateway-liqpay' ),
                ),
                'fee' => array(
                    'title'       => __( 'Удержать комиссию', 'wc-gateway-liqpay' ),
                    'type'        => 'number',
					'default'     => '2.75',
                    'description' => __( 'Размер в процентах удерживаемой с покупателя комиссии за пользование платёжной системы. Укажите отличное от 0 значение, если хотите чтобы после снятия указанного процента вы получили полную сумму заказа.', 'wc-gateway-liqpay' ),
                ),
                'sandbox'     => array(
                    'title'       => __( 'Демо оплата', 'wc-gateway-liqpay' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Включить', 'wc-gateway-liqpay' ),
                    'default'     => 'no',
                    'description' => __( 'Включить демо оплату в магазине. Деньги на карту не зачисляются.', 'wc-gateway-liqpay' ),
                ),
                'skip_submit'     => array(
                    'title'       => __( 'Авторедирект на страницу оплаты', 'wc-gateway-liqpay' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Включить', 'wc-gateway-liqpay' ),
                    'default'     => 'no',
                    'description' => __( 'Автоматически направлять пользователя на страницу оплаты системы Liqpay', 'wc-gateway-liqpay' ),
                )
            );
        }

        public function process_payment( $order_id ) {
            $order = new WC_Order( $order_id );
            return array(
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url( true )
            );
        }

        public function receipt_page( $order ) {

            $order          = new WC_Order( $order );
            $order_id       = $order->id;
            $amount         = $order->get_total();
			if (is_numeric($this->fee) && $this->fee > 0) {
				$amount     = $amount / (1 - $this->fee / 100.0);
			}
            $products_names = array();

            foreach ( $order->get_items() as $order_item ) {
                $products_names[] = $order_item['name'];
            }

            $products = implode( ', ', $products_names );

            $this->_resultUrl = $order->get_checkout_order_received_url();

            $params = array(
                'version'     => 3,
                'public_key'  => $this->_public_key,
                'action'      => 'pay',
                'amount'      => $amount,
                'currency'    => get_option( 'woocommerce_currency' ),
                'description' => 'Покупка ' . $products,
                'order_id'    => $order_id,
                'result_url'  => $this->_resultUrl,
                'server_url'  => $this->_serverUrl
            );

            if ( $this->get_option( 'sandbox' ) == 'yes' ) {
                $params['sandbox'] = '1';
            }

            echo '<p>'.__( 'Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'wc-gateway-liqpay' ).'</p>';
            echo $this->generate_form( $params );
        }

        private function check_params( $params ) {

            if ( !isset( $params['version'] ) ) {
                throw new InvalidArgumentException('version is null');
            }

            if ( !isset( $params['amount'] ) ) {
                throw new InvalidArgumentException('amount is null');
            }

            if ( !isset( $params['currency'] ) ) {
                throw new InvalidArgumentException('currency is null');
            }

            if ( !in_array( $params['currency'], $this->_supportedCurrencies ) ) {
                throw new InvalidArgumentException('currency is not supported');
            }

            if ( $params['currency'] == 'RUR' ) {
                $params['currency'] = 'RUB';
            }

            if ( !isset( $params['description'] ) ) {
                throw new InvalidArgumentException('description is null');
            }

            return $params;
        }

        public function make_signature( $params ) {

            $params      = $this->check_params( $params );
            $private_key = $this->_private_key;
            $json        = base64_encode( json_encode( $params ) );
            $signature   = $this->str_to_sign( $private_key . $json . $private_key );

            return $signature;
        }

        public function generate_form( $params ) {

            global $woocommerce;

            $language = 'ru';
            if ( isset($params['language'] ) && $params['language'] == 'en') {
                $language = 'en';
            }

            $params    = $this->check_params( $params );
            $data      = base64_encode( json_encode( $params ) );
            $signature = $this->make_signature( $params );

            $template = sprintf('
                <form method="POST" id="%s_payment_form" action="%s" accept-charset="utf-8">
                  %s
                  %s
                  <input type="image" class="liqpay-btn" src="//static.liqpay.ua/buttons/p1%s.radius.png" name="btn_text" />
                </form>',
                $this->id,
                $this->_checkout_url,
                sprintf( '<input type="hidden" name="%s" value="%s" />', 'data', $data ),
                sprintf( '<input type="hidden" name="%s" value="%s" />', 'signature', $signature ),
                $language
            );

            if ( $this->skip_submit == 'yes' ) {
                $skip_script = sprintf(
                    '<script type="text/javascript">
                      jQuery(function() {
                        jQuery.blockUI({ message: "<h4>%s</h4><p>%s</p>" })
                        jQuery("#' . $this->id . '_payment_form").submit(); 
                      })
                    </script>',
                    __( 'Спасибо за заказ', 'wc-gateway-liqpay' ),
                    __( 'Сейчас Вы будете перенаправлены на страницу оплаты.', 'wc-gateway-liqpay' )
                );

                $template .= $skip_script;
            }

            $woocommerce->cart->empty_cart();
            return $template;
        }

        public function str_to_sign( $str ) {

            $signature = base64_encode( sha1( $str, 1 ) );
            return $signature;
        }

        public function check_ipn_response() {

            if ( isset( $_POST['data'] ) && isset( $_POST['signature'] ) ) {

                /* parsing data from API */
                $data                = $_POST['data'];
                $received_signature  = $_POST['signature'];
                $parsed_data         = json_decode( base64_decode( $data ) );

                $received_public_key = $parsed_data->public_key;
                $order_id            = $parsed_data->order_id;
                $status              = $parsed_data->status;

                $generated_signature = base64_encode( sha1( $this->_private_key . $data . $this->_private_key, 1 ) );

                $order = new WC_Order( $order_id );

                if ( $received_signature !== $generated_signature || $received_public_key !== $this->_public_key ) {
                    wp_die( __( 'IPN Response Error' ), 'Liqpay IPN', array( 'response' => 500 ) );
                }

                // Handle order status and add order notes
                switch ($status) {
                    case 'success':
                        $order->payment_complete();
                        $order_note = __( 'Платёж успешно выполнен', 'wc-gateway-liqpay' );
                        break;
                    case 'sandbox':
                        $order->update_status( 'completed', __( 'Тестовый платёж завершен', 'wc-gateway-liqpay' ) );
                        $order_note = __( 'Тестовый платёж успешно выполнен', 'wc-gateway-liqpay' );
                        break;
                    case 'error':
                        $order->update_status( 'failed', __( 'Платёж не выполнен', 'wc-gateway-liqpay' ) );
                        $order_note = __( 'Платёж не удался', 'wc-gateway-liqpay' );
                        break;
                    case 'failure':
                        $order->update_status( 'failed', __( 'Платёж не выполнен', 'wc-gateway-liqpay' ) );
                        $order_note = __( 'Неуспешный платеж. Некорректно заполнены данные', 'wc-gateway-liqpay' );
                        break;
                }

                $order->add_order_note( $order_note );

            } else {
                wp_die( __( 'IPN Request Failure.', 'wc_lp' ), 'Liqpay IPN', array( 'response' => 500 ) );
            }
        }
    }
}