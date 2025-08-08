<?php
/**
 * Checkout Manager for WooCommerce Deposits Provisional Shipping
 *
 * @package woocommerce-deposits-provisional-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Deposits_PS_Checkout_Manager class
 */
class WC_Deposits_PS_Checkout_Manager {

	/**
	 * Class instance
	 *
	 * @var WC_Deposits_PS_Checkout_Manager
	 */
	private static $instance;

	/**
	 * Get class instance
	 *
	 * @return WC_Deposits_PS_Checkout_Manager
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


		// Add provisional shipping form to checkout
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'display_provisional_shipping_form' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_provisional_shipping_data' ) );

		// Add validation
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_provisional_shipping' ) );

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Block checkout compatibility
		add_filter( 'woocommerce_blocks_checkout_block_registry', array( $this, 'register_block_checkout_support' ) );
	}



	/**
	 * Check if cart has payment plans or deposits
	 *
	 * @return bool
	 */
	public function has_deposit_or_payment_plan_in_cart() {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			// Check for any deposit (regular deposit or payment plan)
			if ( ! empty( $cart_item['is_deposit'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if cart has payment plans (for backward compatibility)
	 *
	 * @return bool
	 */
	public function has_payment_plan_in_cart() {
		return $this->has_deposit_or_payment_plan_in_cart();
	}

	/**
	 * Display provisional shipping form
	 */
	public function display_provisional_shipping_form() {
		if ( ! $this->has_deposit_or_payment_plan_in_cart() ) {
			return;
		}

		// Get available shipping methods
		$available_methods = $this->get_available_shipping_methods();
		
		if ( empty( $available_methods ) ) {
			return;
		}

		// Load template
		
		wc_get_template(
			'provisional-shipping-form.php',
			array(
				'available_methods' => $available_methods,
			),
			'',
			WC_DEPOSITS_PS_PLUGIN_PATH . '/templates/'
		);
	}

	/**
	 * Save provisional shipping data to order
	 *
	 * @param int $order_id Order ID
	 */
	public function save_provisional_shipping_data( $order_id ) {
		if ( ! $this->has_deposit_or_payment_plan_in_cart() ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Check if provisional shipping data was submitted
		if ( isset( $_POST['provisional_shipping_method'] ) && ! empty( $_POST['provisional_shipping_method'] ) ) {
			$shipping_method = sanitize_text_field( $_POST['provisional_shipping_method'] );
			$shipping_cost = isset( $_POST['provisional_shipping_cost'] ) ? floatval( $_POST['provisional_shipping_cost'] ) : 0;
			$terms_accepted = isset( $_POST['shipping_terms_accepted'] ) ? 'yes' : 'no';



			// Save provisional shipping data
			$order->update_meta_data( '_wc_deposits_ps_provisional_shipping_method', $shipping_method );
			$order->update_meta_data( '_wc_deposits_ps_provisional_shipping_cost', $shipping_cost );
			$order->update_meta_data( '_wc_deposits_ps_shipping_terms_accepted', $terms_accepted );

			// Add order note
			$order_note = sprintf(
				/* translators: %1$s: shipping method, %2$s: cost */
				__( 'Provisional shipping selected: %1$s (estimated cost: %2$s). Full shipping cost will be calculated and charged upon completion of deposit or payment plan.', 'woocommerce-deposits-provisional-shipping' ),
				$shipping_method,
				wc_price( $shipping_cost )
			);
			
			$order->add_order_note( $order_note );

			$order->save();
		}
	}

	/**
	 * Validate provisional shipping
	 */
	public function validate_provisional_shipping() {
		if ( ! $this->has_deposit_or_payment_plan_in_cart() ) {
			return;
		}

		// Check if shipping method is selected
		if ( ! isset( $_POST['provisional_shipping_method'] ) || empty( $_POST['provisional_shipping_method'] ) ) {
			wc_add_notice(
				__( 'Please select a provisional shipping method.', 'woocommerce-deposits-provisional-shipping' ),
				'error'
			);
		}

		// Check if terms are accepted
		if ( ! isset( $_POST['shipping_terms_accepted'] ) ) {
			wc_add_notice(
				__( 'You must accept the provisional shipping terms to continue.', 'woocommerce-deposits-provisional-shipping' ),
				'error'
			);
		}
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( $this->has_deposit_or_payment_plan_in_cart() ) {
			
			// Get file modification times for cache busting
			$css_file = WC_DEPOSITS_PS_PLUGIN_PATH . '/assets/css/provisional-shipping.css';
			$js_file = WC_DEPOSITS_PS_PLUGIN_PATH . '/assets/js/provisional-shipping.js';
			
			$css_version = file_exists( $css_file ) ? filemtime( $css_file ) : WC_DEPOSITS_PS_VERSION;
			$js_version = file_exists( $js_file ) ? filemtime( $js_file ) : WC_DEPOSITS_PS_VERSION;
			
			wp_enqueue_style(
				'wc-deposits-ps-provisional-shipping',
				WC_DEPOSITS_PS_PLUGIN_URL . '/assets/css/provisional-shipping.css',
				array(),
				$css_version
			);

			wp_enqueue_script(
				'wc-deposits-ps-provisional-shipping',
				WC_DEPOSITS_PS_PLUGIN_URL . '/assets/js/provisional-shipping.js',
				array( 'jquery' ),
				$js_version,
				true
			);

			wp_localize_script(
				'wc-deposits-ps-provisional-shipping',
				'wc_deposits_ps',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'wc_deposits_ps_nonce' ),
					'i18n' => array(
						'select_shipping_method' => __( 'Please select a shipping method.', 'woocommerce-deposits-provisional-shipping' ),
						'accept_terms' => __( 'You must accept the provisional shipping terms.', 'woocommerce-deposits-provisional-shipping' ),
						'selected' => __( 'Selected:', 'woocommerce-deposits-provisional-shipping' ),
						'estimated_cost' => __( 'Estimated cost:', 'woocommerce-deposits-provisional-shipping' ),
					),
				)
			);
		}
	}

	/**
	 * Register block checkout support
	 *
	 * @param object $registry Block registry
	 * @return object
	 */
	public function register_block_checkout_support( $registry ) {
		// Add support for block checkout
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
		
		return $registry;
	}

	/**
	 * Get available shipping methods for provisional shipping
	 *
	 * @return array
	 */
	public function get_available_shipping_methods() {
		
		$available_methods = array();
		
		// Get shipping zones
		$zones = WC_Shipping_Zones::get_zones();
		
		// Add worldwide zone
		$worldwide_zone = new WC_Shipping_Zone( 0 );
		$zones[0] = array(
			'zone_id' => 0,
			'zone_name' => $worldwide_zone->get_zone_name(),
			'shipping_methods' => $worldwide_zone->get_shipping_methods(),
		);
		
		foreach ( $zones as $zone ) {
			$zone_name = $zone['zone_name'];
			$shipping_methods = $zone['shipping_methods'];
			
			foreach ( $shipping_methods as $method ) {
				if ( $method->is_enabled() ) {
					// Calculate cost for this method
					$cost = $method->get_option( 'cost' );
					if ( empty( $cost ) ) {
						$cost = 0;
					}
					
					$available_methods[] = array(
						'id' => $method->get_rate_id(),
						'title' => $method->get_title(),
						'cost' => $cost,
						'zone_name' => $zone_name,
					);
				}
			}
		}
		
		return $available_methods;
	}

	/**
	 * Get default shipping method
	 *
	 * @return string
	 */
	public function get_default_shipping_method() {
		$cart_manager = WC_Deposits_PS_Cart_Manager::get_instance();
		$available_methods = $cart_manager->get_available_shipping_methods();

		if ( ! empty( $available_methods ) ) {
			$default_method = $available_methods[0]['id'];
			return $default_method;
		}

		return '';
	}
} 