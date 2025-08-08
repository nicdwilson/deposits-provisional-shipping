<?php
/**
 * Plugin Name: WooCommerce Deposits Provisional Shipping
 * Requires Plugins: woocommerce, woocommerce-deposits
 * Plugin URI: https://example.com/woocommerce-deposits-provisional-shipping
 * Description: Handles provisional shipping for WooCommerce Deposits payment plans and regular deposits. Shipping costs are calculated and charged separately upon completion of deposits or payment plans.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: woocommerce-deposits-provisional-shipping
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8
 * WC tested up to: 9.9
 * WC requires at least: 9.7
 * Requires PHP: 7.4
 * PHP tested up to: 8.3
 *
 * Copyright: © 2024 Your Name
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package woocommerce-deposits-provisional-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'WC_DEPOSITS_PS_VERSION', '1.0.0' );
define( 'WC_DEPOSITS_PS_FILE', __FILE__ );
define( 'WC_DEPOSITS_PS_PLUGIN_URL', untrailingslashit( plugins_url( '', WC_DEPOSITS_PS_FILE ) ) );
define( 'WC_DEPOSITS_PS_PLUGIN_PATH', untrailingslashit( plugin_dir_path( WC_DEPOSITS_PS_FILE ) ) );

/**
 * Main plugin class
 */
class WC_Deposits_Provisional_Shipping {

	/**
	 * Plugin instance
	 *
	 * @var WC_Deposits_Provisional_Shipping
	 */
	private static $instance;

	/**
	 * Get plugin instance
	 *
	 * @return WC_Deposits_Provisional_Shipping
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// HPOS Compatibility
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_feature_compatibility' ) );

		// Initialize plugin after dependencies are loaded
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}

	/**
	 * Declare WooCommerce feature compatibility
	 */
	public function declare_woocommerce_feature_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}

	/**
	 * Initialize plugin
	 */
	public function init() {

		// Load plugin files
		$this->includes();

		// Initialize components
		$this->init_components();

		// Load text domain
		add_action( 'init', array( $this, 'load_textdomain' ) );

	}


	/**
	 * Include required files
	 */
	private function includes() {
		
		require_once WC_DEPOSITS_PS_PLUGIN_PATH . '/includes/class-wc-deposits-ps-cart-manager.php';
		require_once WC_DEPOSITS_PS_PLUGIN_PATH . '/includes/class-wc-deposits-ps-order-manager.php';
		require_once WC_DEPOSITS_PS_PLUGIN_PATH . '/includes/class-wc-deposits-ps-checkout-manager.php';
		require_once WC_DEPOSITS_PS_PLUGIN_PATH . '/includes/class-wc-deposits-ps-shipping-calculator.php';
		
	}

	/**
	 * Initialize plugin components
	 */
	private function init_components() {
		
		// Initialize cart manager
		WC_Deposits_PS_Cart_Manager::get_instance();
		
		// Initialize order manager
		WC_Deposits_PS_Order_Manager::get_instance();
	
		// Initialize checkout manager
		WC_Deposits_PS_Checkout_Manager::get_instance();
		
		// Initialize shipping calculator
		WC_Deposits_PS_Shipping_Calculator::get_instance();
		
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'woocommerce-deposits-provisional-shipping',
			false,
			dirname( plugin_basename( WC_DEPOSITS_PS_FILE ) ) . '/languages'
		);
	}
}

// Initialize plugin
WC_Deposits_Provisional_Shipping::get_instance(); 