<?php

/**
 * Plugin Name: ViconBank Pagamentos
 * Plugin URI:  https://github.com/hiraygui/viconbank-woocommerce
 * Author: Gizo Digital
 * Author URI: 
 * Description: Este plugin permite que sua loja virtual possa aceitar pagamentos através de Pix e Boleto
 * Version: 0.1.1
 * License:     GPL-2.0+
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: viconbank-woocommerce
 * 
 * Class WC_Gateway_ViconBank file.
 *
 * @package WooCommerce\viconbank-woocommerce
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * ViconBank Pagamentos Gateway.
 *
 * Provides a ViconBank Pagamentos Payment Gateway.
 *
 * @class       WC_ViconBank_Gateway
 * @extends     WC_Payment_Gateway
 * @version     0.1.1
 * @package     WooCommerce\Classes\Payment
 */

//condição verifica se plugin woocommerce está ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

//função permite ativação de plugin
add_action('plugins_loaded', 'viconbank_init', 11);
add_filter('woocommerce_payment_gateways', 'add_to_woo_viconbank_payment_gateway');


function viconbank_init()
{
	if (class_exists('WC_Payment_Gateway')) {
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-viconbank-gateway.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/viconbank-order-status.php';
	}
}

function add_to_woo_viconbank_payment_gateway($gateways){
   $gateways[] = 'WC_ViconBank_Gateway';
   return $gateways;
}