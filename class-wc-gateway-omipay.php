<?php
/*
 * Plugin Name: Omipay
 * Plugin URI: 
 * Description: Add a payment method to WooCommerce using Omipay Checkout.
 * Author: Team Omipay
 * Author URI: 
 * Version: 1.0.3
 * Copyright (c) 2022
 */
include('core/config.php');
include('core/lang.php');
include('core/lib/OmipayConstance.php');
if (!defined('ABSPATH')) exit; // Exit if accessed directly
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
define( 'OMIPAY_PLUGIN_URL', esc_url( plugins_url( '', __FILE__ ) ) );

	//Create class after the plugins are loaded
	add_action('plugins_loaded', 'init_payment_gateway_class');
    add_action( 'callback', 'thankyou_custom_payment_redirect');

	//Init payment gateway class
	function init_payment_gateway_class()
	{
		class WC_Omipay_Checkout extends WC_Payment_Gateway
		{
			var $notify_url;

			/**
			 * Constructor for the gateway.
			 *
			 * @access public
			 * @return \WC_Omipay_Checkout
			 */
			public function __construct()
			{
				global $woocommerce;

				$this->id = 'omipay';
				$this->icon = sprintf("%s/logo.png",OMIPAY_PLUGIN_URL);
				$this->has_fields = false;
				$this->method_title = __('OMIPAY', 'woocommerce');
				$this->testurl = 'https://checkout-sandbox.omipay.vn/checkout.php';
				$this->liveurl = 'https://checkout.omipay.vn/checkout.php';

				//load the setting
				$this->init_form_fields();
				$this->init_settings();

				//Define user set variables
				$this->title = $this->get_option('title');
				$this->description = $this->get_option('description');
				$this->receiver_acc = $this->get_option('receiver_acc');
				$this->merchant_id = $this->get_option('merchant_id');
				$this->secure_pass = $this->get_option('secure_pass');
				$this->testmode = $this->get_option('testmode');
				$this->Curency_id=$this->get_option('Curency_id');
				$this->language=$this->get_option('language');
		
		
				$this->form_submission_method = false;

				//Action
				add_action('valid-omipay-standard-ipn-request', array($this, 'successful_request'));
				add_action('woocommerce_receipt_omipay', array($this, 'receipt_page'));
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
				add_action('woocommerce_api_wc_omipay_checkout', array($this, 'callback'));
				if (!$this->is_valid_for_use()) $this->enabled = false;
				

			}

			function is_valid_for_use()
			{
				if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_omipay_supported_currencies', array('VND', 'VNĐ', 'USD'))))
					return false;
				return true;
			}

			public function admin_options()
			{
				?>
				<h3><?php _e('Thanh toán Omipay', 'woocommerce'); ?></h3>
				<strong><?php _e('Omipay giá trị thanh toán đích thực.', 'woocommerce'); ?></strong>
				<?php if ($this->is_valid_for_use()) : ?>

				<table class="form-table">
					<?php
					// Generate the HTML For the settings form.
					$this->generate_settings_html();
					?>
				</table><!--/.form-table-->

				<?php else : ?>
					<div class="inline error"><p>
							<strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php _e('Phương thức thanh toán omipay không hỗ trợ loại tiền tệ trên gian hàng của bạn.', 'woocommerce'); ?>
						</p></div>
				<?php
				endif;
			}

			/**
			 * Initialise Gateway Settings Form Fields
			 *
			 * @access public
			 * @return void
			 */
			function init_form_fields()
			{

				$this->form_fields = array(
					'enabled' => array(
						'title' => __('Sử dụng phương thức', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('Đồng ý', 'woocommerce'),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __('Tiêu đề', 'woocommerce'),
						'type' => 'text',
						'description' => __('Tiêu đề của phương thức thanh toán bạn muốn hiển thị cho người dùng.', 'woocommerce'),
						'default' => __('Omipay', 'woocommerce'),
						'desc_tip' => true,
					),
					'description' => array(
						'title' => __('Mô tả phương thức thanh toán', 'woocommerce'),
						'type' => 'textarea',
						'description' => __('Mô tả của phương thức thanh toán bạn muốn hiển thị cho người dùng.', 'woocommerce'),
						'default' => __('Thanh toán với Omipay. Đảm bảo an toàn tuyệt đối cho mọi giao dịch', 'woocommerce')
					),
					'language' => array(
						'title' => __( 'Ngôn ngữ', 'woocommerce' ),
						'type' => 'select',
						'default' => 'vi',
						'description' => __( 'Ngôn ngữ được lựa chọn sẽ là ngôn ngữ trên cổng thanh toán', 'woocommerce' ),
						'options' => array(
							OmipayConstance::LANG_VI => 'Tiếng Việt',
							OmipayConstance::LANG_EN => 'Tiếng Anh',
						)
					),
					'account_config' => array(
						'title' => __('Cấu hình tài khoản', 'woocommerce'),
						'type' => 'title',
						'description' => '',
					),
					'receiver_acc' => array(
						'title' => __('Email đăng kí với Omipay', 'woocommerce'),
						'type' => 'text',
						'description' => __('Email đăng kí với Omipay', 'woocommerce'),
						'default' => '',
						'desc_tip' => true,
						
					),
					'merchant_id' => array(
						'title' => __('Merchant id', 'woocommerce'),
						'type' => 'text',
						'description' => __('“Mã merchant” được Omipay cấp khi bạn đăng ký tích hợp website.', 'woocommerce'),
						'default' => '',
						'desc_tip' => true,
						
					),
					'secure_pass' => array(
						'title' => __('Mã bảo mật', 'woocommerce'),
						'type' => 'text',
						'description' => __('Mã bảo mật khi bạn đăng ký tích hợp website.', 'woocommerce'),
						'default' => '',
						'desc_tip' => true,
						
					),
					'testmode' => array(
						'title' => __('Testmode', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('Sử dụng Omipay kiểm thử', 'woocommerce'),
						'default' => 'yes',
						'description' => 'Omipay kiểm thử được sử đụng kiểm tra phương thức thanh toán.',
					),
				);

			}

			/**
			 * Process the payment and return the result
			 *
			 * @access public
			 * @param int $order_id
			 * @return array
			 */
			function process_payment($order_id)
			{
                $order = new WC_Order($order_id);
				/*Check valid transaction*/
				$currency = $this->checkCurrency($order);
				$paymentConfig = $this->getSettingPayment();

				if($paymentConfig === false || !$currency) {
					// Mark as failed
					$order->update_status('on-hold', __( 'Đơn hàng tạm giữ', 'woocommerce' ));
		
					return array(
						'result' => 'error',
						'redirect' => $this->get_return_url()
					);
				}

				if (!$this->form_submission_method) {
					$omipay_args = $this->get_omipay_args($order);
					if ($this->testmode == 'yes'):
						$omipay_server = $this->testurl; else :
						$omipay_server = $this->liveurl;
					endif;
					$omipay_url = $this->createRequestUrl($omipay_args, $omipay_server);
					return array(
						'result' => 'success',
						'redirect' => $omipay_url
					);
				} else {
					return array(
						'result' => 'success',
						'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
					);
				}
			}


			function get_omipay_args($order)
			{
				global $woocommerce;
				$order_id = $order->get_id();
				$invoice_no = "OM_".time()."_".strval($order_id);
				$omipay_args = array(
					'invoice_no' => $invoice_no,
					'website_id' => strval($this->merchant_id),
					'reference_number' => strval($order_id),
					'receiver_account' => strval($this->receiver_acc),
					'url_return' => strtolower(get_bloginfo('wpurl') . "/wc-api/WC_Omipay_Checkout"),
					'cancel_url' => strtolower(get_bloginfo('wpurl') . "/wc-api/WC_Omipay_Checkout"),
					'bill_to_phone' => strval($order->billing_phone),
					'payment_type' => '',
					'language' => strval($this->language),
				);

				/*Invoice no*/
				$order->update_meta_data( 'invoice_no', $invoice_no);

				$currencyArr = [
					'VND'	=>	'vnd',
					'VNĐ'	=>	'vnd',
					'USD'	=>	'usd',
				];
				$omipay_args['amount'] = $order->order_total;
				$omipay_args['currency'] = isset($currencyArr[get_woocommerce_currency()]) ? $currencyArr[get_woocommerce_currency()] : 'vnd';

				return $omipay_args;
			}

			/**
			 * Điều hướng tác vụ xử lý cập nhật đơn hàng sau thanh toán hoặc nhận BPN từ Omipay
			 */
			function callback()
			{
				$url = get_bloginfo('wpurl');
				$order_code = sanitize_text_field(@$_GET['order_code']);
				if (isset($_GET['order_code']) && isset($_GET['secure_code'])) {
					$exp = explode( '_', $order_code);
					$order_id = $exp['2'];
					$order = new WC_Order($order_id);
					
					$merchant_id = strval($this->merchant_id);
					$secure_pass = strval($this->secure_pass);
	
					$secureCode = '';
					foreach ($this->verifySecureCode() as $key) {
						$secureCode .= ' ' . strval(sanitize_text_field(@$_GET[$key]));
					}
					$secureCode .= ' ' . trim($merchant_id) . ' ' . trim($secure_pass);
					$verifySecureCode = md5($secureCode);
	
					$secure_code = sanitize_text_field(@$_GET['secure_code']);
	
					if ($verifySecureCode !== $secure_code) {
						wp_redirect($url);
						exit();
						return;
					}else {
						$comment_status = ' </br>- Make successful payment with order: orderId' . $order_id . '</br>- Invoice code: '. $order_code.'</br>';
						$order_status='completed';
						$order->update_status($order_status, sprintf(__('Completed: %s', 'woocommerce'), $comment_status));
					}
					
					$link = $order->get_view_order_url(); 
					wp_redirect($link);
					exit();
					return;
				}
				wp_redirect($url);
				exit();
				return;
			}

			function verifySecureCode()
			{
				return [
					'transaction_info',
					'order_code',
					'price',
					'payment_id',
					'payment_type',
					'error_text',
				];
			}
            
			function verifyPaymentUrlLive($amount,$message,$payment_type,$order_code,$status,$trans_ref_no,$website_id,$sign)
			{
				
				// My plaintext
				$secret_key = $this->secure_pass;
				$plaintext = $amount."|".$message."|".$payment_type."|".$order_code."|".$status."|".$trans_ref_no."|".$website_id ."|". $secret_key;
				//print $plaintext;
				// Mã hóa sign
				$verify_secure_code = '';
				$verify_secure_code = strtoupper(hash('sha256', $plaintext));;
				// Xác thực chữ ký của ch? web v?i ch? ký tr? v? t? VTC Pay
				if ($verify_secure_code === $sign) 		return strval($status);
				
				return false;
			}

			private function createRequestUrl($data, $omipay_server)
			{
				$params = $data;

				$merchant_id = strval($this->merchant_id);
				$secure_pass = strval($this->secure_pass);
				$return_url = $params['url_return'];
				$cancel_url = $params['cancel_url'];
				$receiver = strval($this->receiver_acc);
				$order_code = $params['invoice_no'];
				$transaction_info = "tichhopthanhtoan_woocommerce";
				$price = $params['amount'];
				$currency = $params['currency'];
				$lang = $params['language'];
				$order_description = "Thanh toán đơn hàng: ". $order_code;
				if ($lang = 'en') {
					$order_description = "Order payment: ". $order_code;
				}

				$url = buildCheckoutUrlExpand(
					$omipay_server,
					$merchant_id,
					$secure_pass,
					$return_url,
					$cancel_url,
					$receiver,
					$order_code,
					$price,
					$currency,
					$order_description,
					$transaction_info,
					$lang
				);
				return $url;
			}

			/**
		 	* @return array|bool
			*/
			private function getSettingPayment()
			{  
				$payment = new WC_Payment_Gateways();
				$paymentConfig = $payment->get_available_payment_gateways()['omipay']->settings;

				if(empty($paymentConfig)) {
					return false;
				}

				if(empty($paymentConfig['merchant_id']) || empty($paymentConfig['secure_pass']) || empty($paymentConfig['receiver_acc'])) {
					return false;
				}

				return $paymentConfig;
			}

			/**
			 * @param $order
			 * @return bool
			 */
			private function checkCurrency($order)
			{
				$configFile = include('core/config.php');

				return in_array($order->get_data()['currency'], $configFile['CURRENCY']);
			}

		}

		class WC_OMIPAY extends WC_Omipay_Checkout
		{
			public function __construct()
			{
				_deprecated_function('WC_OMIPAY', '1.4', 'WC_Omipay_Checkout');
				parent::__construct();
			}
		}

		//Defining class gateway
		function add_omipay_gateway_class( $methods ) {
			$methods[] = 'WC_Omipay_Checkout';
			return $methods;
		}

		add_filter( 'woocommerce_payment_gateways', 'add_omipay_gateway_class');
	};

	$create_order_params = [];
	function buildCheckoutUrlExpand(
		$omipay_server,
		$merchant_id,
		$secure_pass,
		$return_url,
		$cancel_url = '',
		$receiver,
		$order_code,
		$price,
		$currency = 'vnd',
		$order_description = '',
		$transaction_info,
		$lang = 'vi',
		$notify_url = '',
		$quantity = 1,
		$tax = 0,
		$discount = 0,
		$fee_cal = 0,
		$fee_shipping = 0,
		$buyer_info = '',
		$affiliate_code = '',
		$token='',
		$token_type='',
		$blnInstallment = 0,
		$inpage = 0,
		$payment_method_id = 0,
		$method_group = ''
	)
	{
		// if ($affiliate_code == "") $affiliate_code = $this->affiliate_code;
		$create_order_params = array(
			'merchant_site_code'=>	strval($merchant_id),
			'return_url'		=>	strval(strtolower($return_url)),
			'receiver'			=>	strval($receiver),
			'transaction_info'	=>	strval($transaction_info),
			'order_code'		=>	strval($order_code),
			'price'				=>	strval($price),
			'currency'			=>	strval($currency),
			'quantity'			=>	strval($quantity),
			'tax'				=>	strval($tax),
			'discount'			=>	strval($discount),
			'fee_cal'			=>	strval($fee_cal),
			'fee_shipping'		=>	strval($fee_shipping),
			'order_description'	=>	strval($order_description),
			'buyer_info'		=>	strval(''), //"Họ tên người mua *|* Địa chỉ Email *|* Điện thoại *|* Địa chỉ"
			'affiliate_code'	=>	strval($affiliate_code),
			'installment'	=>	strval($blnInstallment),
			'inpage'	=>	strval($inpage),
			'payment_method_id'	=>	strval($payment_method_id)
		);

		$secure_code = implode(' ', $create_order_params) . ' ' . $secure_pass;
		$create_order_params['secure_code'] = md5($secure_code);
		$create_order_params['token'] = $token;
		$create_order_params['token_type'] = $token_type;
		$create_order_params['lang'] = $lang;
		$create_order_params['method_group'] = $method_group;

		/* */
		$redirect_url = $omipay_server;
		if (strpos($redirect_url, '?') === false) {
			$redirect_url .= '?';
		} else if (substr($redirect_url, strlen($redirect_url)-1, 1) != '?' && strpos($redirect_url, '&') === false) {
			$redirect_url .= '&';
		}

		$url = '';
		foreach ($create_order_params as $key=>$value) {
			$value = urlencode($value);
			if ($url == '') {
				$url .= $key . '=' . $value;
			} else {
				$url .= '&' . $key . '=' . $value;
			}
		}
		$url .= '&notify_url=' . $notify_url;
		$url .= '&cancel_url=' . urlencode($cancel_url);
		return $redirect_url.$url;
	}

	
}