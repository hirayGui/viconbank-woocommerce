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

 add_action('plugins_loaded', 'viconbank_init', 11);

 function viconbank_init(){
    if(class_exists('WC_Payment_Gateway')){
        class WC_ViconBank_Gateaway extends WC_Payment_Gateway{
            public function __construct(){
                $this->id = 'viconbank';
                $this->icon = apply_filters('woocommerce_viconbank_icon', plugins_url . '/assets/icon.png');
                $this->has_fields = false;
                $this->method_title = __('ViconBank Pagamento', 'viconbank-woocommerce'); 
                $this->method_description = __('Este plugin permite que sua loja virtual possa aceitar pagamentos através de Pix e Boleto', 'viconbank-woocommerce');

                $this->init_form_fields();
                $this->init_settings();
            }

            public function init_form_fields(){
                
            }
        }
    }
 }