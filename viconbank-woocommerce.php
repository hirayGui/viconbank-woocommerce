<?php 

/**
 * Plugin Name: ViconBank Pagamentos
 * Plugin URI: https://github.com/hiraygui/viconbank-woocommerce
 * Description: Este plugin permite que sua loja virtual possa aceitar pagamentos através de Pix e Boleto
 * Version: 0.1.0
 * Author: Gizo Digital
 * Author URI: 
 * Text Domain: viconbank-woocommerce
 */

 //condição verifica se plugin woocommerce está ativo
 if(!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

//função permite ativação de plugin
 add_action('plugins_loaded', 'viconbank_init', 11);

 function viconbank_init(){
    if(class_exists('WC_Payment_Gateway')){
        class WC_ViconBank_Gateway extends WC_Payment_Gateway{
            public function __construct(){
                $this->id = 'viconbank';
                $this->icon = apply_filters('woocommerce_viconbank_icon', plugins_url('/assets/icon.png', __FILE__));
                $this->has_fields = false;
                $this->method_title = __('ViconBank Pagamento', 'viconbank-woocommerce'); 
                $this->method_description = __('Este plugin permite que sua loja virtual possa aceitar pagamentos através de Pix e Boleto', 'viconbank-woocommerce');

                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->instructions = $this->get_option('instructions', $this->description);

                $this->init_form_fields();
                $this->init_settings();

                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            }

            //campos das preferências do plugin dentro do wordpress
            public function init_form_fields(){
                $this->form_fields = apply_filters('woocommerce_viconbank_fields', array(
                    'enabled' => array(
                        'title' => __('Ativar/Desativar', 'viconbank-woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Ativar ou desativar ViconBank Pagamentos', 'viconbank-woocommerce'),
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => __( 'Título', 'viconbank-woocommerce'),
                        'type' => 'text',
                        'default' => __( 'ViconBank Pagamentos', 'viconbank-woocommerce'),
                        'desc_tip' => true,
                        'description' => __( 'Altere o título do modo de pagamento', 'viconbank-woocommerce')
                    ),
                    'description' => array(
                        'title' => __( 'Descrição', 'viconbank-woocommerce'),
                        'type' => 'textarea',
                        'default' => __( 'Faça o pagamento através de uma chave Pix!', 'viconbank-woocommerce'),
                        'desc_tip' => true,
                        'description' => __( 'Altere a descrição do modo de pagamento!', 'viconbank-woocommerce')
                    ),
                    'instructions' => array(
                        'title' => __('Instruções', 'viconbank-woocommerce'),
                        'type' => 'textarea',
                        'desc_tip' => true,
                        'description' => __('Instruções que serão apresentadas ao usuário após o pagamento.', 'viconbank-woocommerce'),
                        'default' => __('Você receberá seu comprovante por email!', 'viconbank-woocommerce'),
                    ),
                ));
            }

            public function process_payment($order_id){
                $order_id = wc_get_order($order_id);
                
                $order->update_status('pending-payment', __('Esperando pagamento', 'viconbank-woocommerce'));

                $this->clear_payment_with_api();

                $order->reduce_order_stock();

                WC()->cart->empty_cart();

                return array(
                    'result' => 'success',
                    "redirect" => $this->get_return_url($order),
                );
            }

            public function clear_payment_with_api(){
                
            }
        }
    }
 }

 add_filter('woocommerce_payment_gateways', 'add_to_woo_viconbank_payment_gateway');

 function add_to_woo_viconbank_payment_gateway($gateways){
    $gateways[] = 'WC_ViconBank_Gateway';
    return $gateways;
 }