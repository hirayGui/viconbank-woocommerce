<?php

/**
 * ViconBank Pagamentos Gateway
 *
 * Providencia um Gateway de pagamento próprio do ViconBank
 *
 * @class       WC_ViconBank_Gateway
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce\Classes\Payment
 */
class WC_ViconBank_Gateway extends WC_Payment_Gateway
{

	/**
	 * Gateway instructions that will be added to the thank you page and emails.
	 *
	 * @var string
	 */
	public $instructions;

	public $status_when_waiting;

	public $data;

	public $auth_url = 'https://vicon.e-bancos.com.br/index.php?r=utilitarios/conexao-api';

	/**
	 * Enable for shipping methods.
	 *
	 * @var array
	 */
	public $enable_for_methods;

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
		$this->title              = $this->get_option('title');
		$this->token			  = $this->get_option('token');
		$this->description        = $this->get_option('description');
		$this->instructions       = $this->get_option('instructions');
		$this->enable_for_methods = $this->get_option('enable_for_methods', array());

		// Actions.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
		
		// add_action	('woocommerce_payment_complete', 'vicon_complete');

		//função verifica se pagamentos forma efetuados
		add_action('rest_api_init', array($this, 'viconbank_check_payment_status'), 10);
		

		// Customer Emails.
		add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
	}

	public function viconbank_check_payment_status(){
		//buscando todos os pedidos que estão com o status 'pagamento pendente'
		$orders = wc_get_orders(array(
			'status' => 'wc-pending'
		));

		$url = 'https://vicon.e-bancos.com.br/index.php?r=pix/pix/consulta-qrcode-externo';
		//checando se o array não está vazio
		if($orders != null){

			//fazendo verificação de token
			$auth_response = wp_remote_get(
				$this->auth_url,
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $this->token,
					],
				]
			); 

			if($auth_response['response']['code'] != 200) {
				return [
					'result' => 'fail',
				];
			}//if ($auth_response['response']['code'] != 200)

			//recuperando token retornado da requisição
			if(!is_wp_error($auth_response)){
				$auth_body = wp_remote_retrieve_body($auth_response);
				$auth_data = json_decode($auth_body, true);

				foreach($orders as $single_order){
					$id_transaction = $single_order->get_meta('id_transacao');

					if($id_transaction > 0){
						$request = json_encode(['transaction_id' => $id_transaction]);
						//fazendo a requisição e já atribuindo o seu retorno a uma variável
						$response = wp_remote_post(
							$url,
							[
								'headers' => [
									'Authorization' => 'Bearer ' . $auth_data['msg'],
								],
								'body' => $request,
							]
						);

						//verificando código recebido da requisição
						if ($response['response']['code'] === 401) {
							return [
								'result' => 'fail',
							];
						}//$response['response']['code'] === 401
				
						if ($response['response']['code'] != 200) {
							return [
								'result' => 'fail',
							];
						}//$response['response']['code'] != 200

						if(!is_wp_error($response)){

							//$body recebe o corpo da resposta da requisição
							$body = wp_remote_retrieve_body($response);

							//$data recebe a resposta da requisição traduzida para array PHP
							$data_request = json_decode($body, true);

							//atualizando status do pedido de acordo com status de pagamento
							if ($data_request['pago'] == true) {
								$single_order->update_meta_data('pago', true);
								$single_order->update_status('completed');
							}else{
								$single_order->update_status('pending');
							}
						}//!is_wp_error($response)
					}//$id_transaction > 0
				}//foreach($orders as $single_order)
			}
		}else{
			return array(
				'result' => 'fail'
			);
		}

	}//public function viconbank_check_payment_status()

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties()
	{
		$this->id                 = 'viconbank';
		$this->token			  = __('Adicionar Token', 'viconbank-woocommerce');
		$this->icon               = apply_filters('viconbank-woocommerce_viconbank_icon', plugins_url('../assets/icon.png', __FILE__));
		$this->method_title       = __('Pix', 'viconbank-woocommerce');
		$this->method_description = __('Receba pagamentos em Pix utilizando sua conta ViconBank', 'viconbank-woocommerce');
		$this->has_fields         = false;
		$this->instructions 	  = __('Escaneie o código QR para realizar o pagamento!', 'viconbank-woocommerce');
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __('Ativar/Desativar', 'viconbank-woocommerce'),
				'label'       => __('Ativar Pagamento em Pix - ViconBank', 'viconbank-woocommerce'),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'token'              => array(
				'title'       => __('Token', 'viconbank-woocommerce'),
				'type'        => 'text',
				'description' => '<a href="https://relacionamento.viconbank.com.br/" target="_blank">Clique aqui para receber seu token</a>',
			),
			'title'              => array(
				'title'       => __('Título', 'viconbank-woocommerce'),
				'type'        => 'safe_text',
				'description' => __('Título que o cliente verá na tela de pagamento', 'viconbank-woocommerce'),
				'default'     => __('ViconBank Pagamentos', 'viconbank-woocommerce'),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __('Descrição', 'viconbank-woocommerce'),
				'type'        => 'textarea',
				'description' => __('Descrição do método de pagamento', 'viconbank-woocommerce'),
				'default'     => __('Realize o pagamento através de Pix!', 'viconbank-woocommerce'),
				'desc_tip'    => true,
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

		$needs_shipping = apply_filters('woocommerce_cart_needs_shipping', $needs_shipping);

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
		//recuperando informações osbre o pedido
		$order = wc_get_order($order_id);

		//endpoint para emitir pagamento em pix
		$url = 'https://vicon.e-bancos.com.br/index.php?r=pix/pix/gerar-qrcode-pix-acesso-externo-token';
		$cart_total = $this->get_order_total();

		//fazendo verificação de token
		$auth_response = wp_remote_get(
			$this->auth_url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->token,
				],
			]
		); 

		if($auth_response['response']['code'] != 200) {
			return [
				'result' => 'fail',
			];
		}//if ($auth_response['response']['code'] != 200)

		if(!is_wp_error($auth_response)){
			$auth_body = wp_remote_retrieve_body($auth_response);
			$auth_data = json_decode($auth_body, true);

			//fazendo a requisição e já atribuindo o seu retorno a uma variável
			$response = wp_remote_post(
				$url,
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $auth_data['msg'],
					],
					'body' => json_encode([
						'valor' => $cart_total,
						'mensagem' => $order_id,
						'expiracao' => 3600,
					],)
				]
			);

			if ($response['response']['code'] === 401) {
				wc_add_notice(
					__('Token inválido!', 'viconbank-woocommerce'),
					'error'
				);

				return [
					'result' => 'fail',
				];
			}

			if ($response['response']['code'] != 200) {
				wc_add_notice(
					__('Erro ao criar chave pix, tente de novo.', 'viconbank-woocommerce'),
					'error'
				);

				return [
					'result' => 'fail',
				];
			}

			//caso requisição seja um sucesso, aplicação já continua com o pagamento
			if (!is_wp_error($response)) {
				//$body recebe o corpo da resposta da requisição
				$body = wp_remote_retrieve_body($response);

				//$data recebe a resposta da requisição traduzida para array PHP
				$this->data = json_decode($body, true);

				//dados da requisição bem-sucedida são adicionados às informações do pedido
				$meta_data = [
					'id_transacao' => $this->data['transaction_id'],
					'qr_code' => $this->data['qrcode'],
					'pix_cod' => $this->data['copia_cola'],
					'data_expiracao' => $this->data['expiracao'],
					'data_criacao' => $this->data['data_criacao'],
				];

				foreach ($meta_data as $key => $value) {
					$order->update_meta_data($key, $value);
				}

				$order->save();

				//informando que o pagamento do pedido está pendente
				$order->update_status(
					$this->status_when_waiting,
					__('ViconBank: O pix foi emitido, mas o pagamento ainda não foi realizado.', 'viconbank-woocomerce')
				);

				//adicionando a chave pix como anotação do pedido
				$order->add_order_note(
					__("ViconBank Chave pix:" . $this->data['copia_cola'], 'viconbank-woocommerce')
				);

				// Remove cart.
				WC()->cart->empty_cart();

				// Return thankyou redirect.
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url($order),
				);
			} else{
				return ['result' => 'fail'];
			}
		}else{
			return ['result' => 'fail'];
		}
	}
	

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page($order_id)
	{
		//buscando informações do pedido
		$order = wc_get_order($order_id);
		$order_data = $order->get_meta('qr_code');
		$copia_cola = $order->get_meta('pix_cod');

		//apresentando qr_code
		$finalImage = '<img src="data:image/png;base64,' .$order_data .'" id="imageQRCode" alt="QR Code" class="qrcode" style="display: block;margin-left: auto;margin-right: auto;"/>';
		echo $finalImage;
		
	
		echo '<div style="font-size: 20px;color: #303030;text-align: center;">';
		echo wp_kses_post(wpautop(wptexturize($this->instructions)));

		echo 'Ou copie e cole a seguinte chave pix em seu aplicativo de banco para realizar o pagamento:';
		echo '<blockquote>' . $copia_cola . '</blockquote>';
		echo '</div>';
	}

	
	public function vicon_complete($order_id){
		$order = wc_get_order($order_id);
		
		$url = 'https://vicon.e-bancos.com.br/index.php?r=pix/pix/consulta-qrcode-externo';

		$id_transaction = intval($order->get_meta('id_transacao'));

		//fazendo a requisição e já atribuindo o seu retorno a uma variável
		$response = wp_remote_post(
			$url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->token,
				],
				'body' => json_encode([
					'transaction_id' => $id_transaction,
				],)
			]
		);

		if(!is_wp_error($response)){
			//$body recebe o corpo da resposta da requisição
			$body = wp_remote_retrieve_body($response);

			//$data recebe a resposta da requisição traduzida para array PHP
			$data = json_decode($body, true);

			if ($data['pago'] === true) {
				$order->update_meta_data('pago', true);
				$order->payment_complete();
			}else{
				$order->update_status('pending');
			}
		}	
	
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