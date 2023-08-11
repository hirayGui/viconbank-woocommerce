<?php

/**
 * ViconBank Pagamentos Gateway.
 *
 * Provides a ViconBank Pagamentos Payment Gateway.
 *
 * @class       WC_ViconBank_Gateway
 * @extends     WC_Payment_Gateway
 * @version     0.1.1
 * @package     viconbank-woocommerce\Classes\Payment
 */

class WC_ViconBank_Gateway extends WC_Payment_Gateway
{

	/**
	 * Gateway instructions that will be added to the thank you page and emails.
	 *
	 * @var string
	 */
	public $instructions;

	/**
	 * Enable for shipping methods.
	 *
	 * @var array
	 */
	public $enable_for_methods;

	/**
	 * Enable for virtual products.
	 *
	 * @var bool
	 */
	public $enable_for_virtual;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->token 			  = $this->get_option('token');
		$this->title              = $this->get_option('title');
		$this->description        = $this->get_option('description');
		$this->instructions       = $this->get_option('instructions');
		$this->enable_for_methods = $this->get_option('enable_for_methods', array());
		$this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes';

		// Actions.
		add_action('viconbank-woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('viconbank-woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
		add_filter('viconbank-woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3);

		// Customer Emails.
		add_action('viconbank-woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties()
	{
		$this->id                 = 'viconbank';
		$this->icon 			  = apply_filters('viconbank-woocommerce_viconbank_icon', plugins_url('../assets/icon.png', __FILE__));
		$this->method_title       = __('ViconBank Pagamentos', 'viconbank-woocommerce');
		$this->method_description = __('Receba pagamentos em Pix ou Boleto!', 'viconbank-woocommerce');
		$this->has_fields         = false;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __('Ativar/Desativar', 'viconbank-woocommerce'),
				'label'       => __('Ativar ViconBank Pagamentos', 'viconbank-woocommerce'),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'token'              => array(
				'title'       => __('Token', 'viconbank-woocommerce'),
				'type'        => 'text',
				'description' => __('Informe o token recebido após fazer a conexão com o ViconBank', 'viconbank-woocommerce'),
				'desc_tip'    => true,
			),
			'title'              => array(
				'title'       => __('Título', 'viconbank-woocommerce'),
				'type'        => 'safe_text',
				'description' => __('Título que o cliente verá no checkout', 'viconbank-woocommerce'),
				'default'     => __('ViconBank Pagamentos', 'viconbank-woocommerce'),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __('Descrição', 'viconbank-woocommerce'),
				'type'        => 'textarea',
				'description' => __('Descrição sobre o método de pagamento', 'viconbank-woocommerce'),
				'default'     => __('Realize o pagamento através dop pix ou boleto', 'viconbank-woocommerce'),
				'desc_tip'    => true,
			),
			'instructions'       => array(
				'title'       => __('Instruções', 'viconbank-woocommerce'),
				'type'        => 'textarea',
				'description' => __('Instruções que são apresentadas na tela de agradecimento', 'viconbank-woocommerce'),
				'default'     => __('Você receberá seu comprovante por email', 'viconbank-woocommerce'),
				'desc_tip'    => true,
			),
			'enable_for_methods' => array(
				'title'             => __('Ativar para métodos de entrega', 'viconbank-woocommerce'),
				'type'              => 'multiselect',
				'class'             => 'wc-enhanced-select',
				'css'               => 'width: 400px;',
				'default'           => '',
				'description'       => __('If viconbank is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'viconbank-woocommerce'),
				'options'           => $this->load_shipping_method_options(),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-placeholder' => __('Select shipping methods', 'viconbank-woocommerce'),
				),
			),
			'enable_for_virtual' => array(
				'title'   => __('Aceitar pedidos virtuais', 'viconbank-woocommerce'),
				'label'   => __('Permitir', 'viconbank-woocommerce'),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
		);
	}

	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		$order          = null;
		$needs_shipping = false;

		// Test if shipping is needed first.
		if (WC()->cart && WC()->cart->needs_shipping()) {
			$needs_shipping = true;
		} elseif (is_page(wc_get_page_id('checkout')) && 0 < get_query_var('order-pay')) {
			$order_id = absint(get_query_var('order-pay'));
			$order    = wc_get_order($order_id);

			// Test if order needs shipping.
			if ($order && 0 < count($order->get_items())) {
				foreach ($order->get_items() as $item) {
					$_product = $item->get_product();
					if ($_product && $_product->needs_shipping()) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}

		$needs_shipping = apply_filters('viconbank-woocommerce_cart_needs_shipping', $needs_shipping);

		// Virtual order, with virtual disabled.
		if (!$this->enable_for_virtual && !$needs_shipping) {
			return false;
		}

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if (!empty($this->enable_for_methods) && $needs_shipping) {
			$order_shipping_items            = is_object($order) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get('chosen_shipping_methods');

			if ($order_shipping_items) {
				$canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids($order_shipping_items);
			} else {
				$canonical_rate_ids = $this->get_canonical_package_rate_ids($chosen_shipping_methods_session);
			}

			if (!count($this->get_matching_rates($canonical_rate_ids))) {
				return false;
			}
		}

		return parent::is_available();
	}

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function is_accessing_settings()
	{
		if (is_admin()) {
			// phpcs:disable WordPress.Security.NonceVerification
			if (!isset($_REQUEST['page']) || 'wc-settings' !== $_REQUEST['page']) {
				return false;
			}
			if (!isset($_REQUEST['tab']) || 'checkout' !== $_REQUEST['tab']) {
				return false;
			}
			if (!isset($_REQUEST['section']) || 'viconbank' !== $_REQUEST['section']) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function load_shipping_method_options()
	{
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if (!$this->is_accessing_settings()) {
			return array();
		}

		$data_store = WC_Data_Store::load('shipping-zone');
		$raw_zones  = $data_store->get_zones();

		foreach ($raw_zones as $raw_zone) {
			$zones[] = new WC_Shipping_Zone($raw_zone);
		}

		$zones[] = new WC_Shipping_Zone(0);

		$options = array();
		foreach (WC()->shipping()->load_shipping_methods() as $method) {

			$options[$method->get_method_title()] = array();

			// Translators: %1$s shipping method name.
			$options[$method->get_method_title()][$method->id] = sprintf(__('Any &quot;%1$s&quot; method', 'viconbank-woocommerce'), $method->get_method_title());

			foreach ($zones as $zone) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ($shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance) {

					if ($shipping_method_instance->id !== $method->id) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf(__('%1$s (#%2$s)', 'viconbank-woocommerce'), $shipping_method_instance->get_title(), $shipping_method_instance_id);

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf(__('%1$s &ndash; %2$s', 'viconbank-woocommerce'), $zone->get_id() ? $zone->get_zone_name() : __('Other locations', 'viconbank-woocommerce'), $option_instance_title);

					$options[$method->get_method_title()][$option_id] = $option_title;
				}
			}
		}

		return $options;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
	 * @return array $canonical_rate_ids    Rate IDs in a canonical format.
	 */
	private function get_canonical_order_shipping_item_rate_ids($order_shipping_items)
	{

		$canonical_rate_ids = array();

		foreach ($order_shipping_items as $order_shipping_item) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 */
	private function get_canonical_package_rate_ids($chosen_package_rate_ids)
	{

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if (!empty($chosen_package_rate_ids) && is_array($chosen_package_rate_ids)) {
			foreach ($chosen_package_rate_ids as $package_key => $chosen_package_rate_id) {
				if (!empty($shipping_packages[$package_key]['rates'][$chosen_package_rate_id])) {
					$chosen_rate          = $shipping_packages[$package_key]['rates'][$chosen_package_rate_id];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @since  3.4.0
	 *
	 * @param array $rate_ids Rate ids to check.
	 * @return boolean
	 */
	private function get_matching_rates($rate_ids)
	{
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique(array_merge(array_intersect($this->enable_for_methods, $rate_ids), array_intersect($this->enable_for_methods, array_unique(array_map('wc_get_string_before_colon', $rate_ids)))));
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);

		if ($order->get_total() > 0) {
			//$order->payment_complete();
			$this->viconbank_payment_processing($order);
		} else {
			$order->payment_complete();
		}

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url($order),
		);
	}

	private function viconbank_payment_processing($order)
	{

		$url('https://vicon.e-bancos.com.br/pix/pix/qrcode-externo');
		$token = $this->token;
		$total = intval($order->get_total());

		$ch = curl_init($url);
		# Setup request to send json via POST.
		$payload = json_encode(array("valor" => $total, 'mensagem' => $order->get_order_id(), 'expiracao' => 3600));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: bearer ' . $token));
		# Return response instead of printing.
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		# Send request.
		$response = curl_exec($ch);
		$result = json_decode($response);
		curl_close($ch);


		return $result;

		// $response = wp_remote_post($url, array('timeout' => 45));

		// if(is_wp_error($response)){
		// 	$error_message = $response->get_error_message();
		// 	return $error_message;
		// }

		// if(wp_remote_retrieve_response_code($response) !== 200){
		// 	$order->update_status(apply_filters('viconbank-woocommerce_viconbank_process_payment_order_status', $order->has_downloadable_item() ? 'wc-invoiced' : 'processing', $order), __('Pagamento pendente.', 'viconbank-woocommerce'));
		// }

		// if(wp_remote_retrieve_response_code($response) === 200){
		// 	$response_body = wp_remote_retrieve_body($response);
		// 	if($response_body['message'] == 'Thank you! Your payment was successful'){
		// 		$order->payment_complete();
		// 	}
		// }

		/*
		// Mark as processing or on-hold (payment won't be taken until delivery).
		$order->update_status(apply_filters('viconbank-woocommerce_viconbank_process_payment_order_status', $order->has_downloadable_item() ? 'wc-invoiced' : 'processing', $order), __('Pagamento pendente.', 'viconbank-woocommerce'));

		$order->payment_complete();
		*/
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page()
	{
		if ($this->instructions) {
			echo wp_kses_post(wpautop(wptexturize($this->instructions)));
		}
	}

	/**
	 * Change payment complete order status to completed for viconbank orders.
	 *
	 * @since  3.1.0
	 * @param  string         $status Current order status.
	 * @param  int            $order_id Order ID.
	 * @param  WC_Order|false $order Order object.
	 * @return string
	 */
	public function change_payment_complete_order_status($status, $order_id = 0, $order = false)
	{
		if ($order && 'viconbank' === $order->get_payment_method()) {
			$status = 'completed';
		}
		return $status;
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions($order, $sent_to_admin, $plain_text = false)
	{
		if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
			echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
		}
	}
}
