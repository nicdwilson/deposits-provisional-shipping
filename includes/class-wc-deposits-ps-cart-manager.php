<?php
/**
 * Cart Manager for WooCommerce Deposits Provisional Shipping
 *
 * @package woocommerce-deposits-provisional-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Deposits_PS_Cart_Manager class
 */
class WC_Deposits_PS_Cart_Manager {

	/**
	 * Class instance
	 *
	 * @var WC_Deposits_PS_Cart_Manager
	 */
	private static $instance;

	/**
	 * Get class instance
	 *
	 * @return WC_Deposits_PS_Cart_Manager
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
		
		// Disable standard shipping for payment plans
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'disable_shipping_for_payment_plans' ), 5 );
		add_filter( 'woocommerce_cart_needs_shipping', array( $this, 'disable_shipping_needs' ), 9999 );
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'disable_shipping_packages' ), 9999 );
		add_filter( 'woocommerce_cart_get_shipping_total', array( $this, 'disable_shipping_total' ), 9999 );
		add_filter( 'woocommerce_cart_get_shipping_taxes', array( $this, 'disable_shipping_taxes' ), 9999 );

		// Remove shipping from cart totals
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'remove_shipping_from_totals' ), 10 );
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
	 * Disable shipping for payment plans or deposits
	 *
	 * @param WC_Cart $cart Cart object
	 */
	public function disable_shipping_for_payment_plans( $cart ) {
		if ( $this->has_deposit_or_payment_plan_in_cart() ) {
			// Remove shipping from cart
			$cart->set_shipping_total( 0 );
			$cart->set_shipping_taxes( array() );
		}
	}

	/**
	 * Disable shipping needs check
	 *
	 * @param bool $needs_shipping Whether cart needs shipping
	 * @return bool
	 */
	public function disable_shipping_needs( $needs_shipping ) {
		if ( $this->has_deposit_or_payment_plan_in_cart() ) {
			return false;
		}
		
		return $needs_shipping;
	}

	/**
	 * Disable shipping packages
	 *
	 * @param array $packages Shipping packages
	 * @return array
	 */
	public function disable_shipping_packages( $packages ) {
		if ( $this->has_deposit_or_payment_plan_in_cart() ) {
			return array();
		}
		
		return $packages;
	}

	/**
	 * Disable shipping total
	 *
	 * @param float $shipping_total Shipping total
	 * @return float
	 */
	public function disable_shipping_total( $shipping_total ) {
		if ( $this->has_deposit_or_payment_plan_in_cart() ) {
			return 0;
		}
		
		return $shipping_total;
	}

	/**
	 * Disable shipping taxes
	 *
	 * @param array $shipping_taxes Shipping taxes
	 * @return array
	 */
	public function disable_shipping_taxes( $shipping_taxes ) {
		if ( $this->has_deposit_or_payment_plan_in_cart() ) {
			return array();
		}
		
		return $shipping_taxes;
	}

	/**
	 * Remove shipping from totals
	 *
	 * @param WC_Cart $cart Cart object
	 */
	public function remove_shipping_from_totals( $cart ) {
		if ( $this->has_deposit_or_payment_plan_in_cart() ) {
			// Remove shipping from cart totals
			$cart->set_shipping_total( 0 );
			$cart->set_shipping_taxes( array() );
		}
	}

	/**
	 * Get available shipping methods for provisional selection
	 *
	 * @return array
	 */
	public function get_available_shipping_methods() {
		$methods = array();

		// Get all available shipping zones and methods
		$shipping_zones = WC_Shipping_Zones::get_zones();
		
		foreach ( $shipping_zones as $zone ) {
			$zone_obj = WC_Shipping_Zones::get_zone( $zone['zone_id'] );
			$shipping_methods = $zone_obj->get_shipping_methods( true );
			
			foreach ( $shipping_methods as $method ) {
				if ( $method->is_enabled() ) {
					$method_data = array(
						'id' => $method->id,
						'title' => $method->get_title(),
						'cost' => $method->get_option( 'cost' ) ?: 0,
						'zone_name' => $zone['zone_name']
					);
					$methods[] = $method_data;
				}
			}
		}

		// Add default zone methods
		$default_zone = WC_Shipping_Zones::get_zone( 0 );
		$default_methods = $default_zone->get_shipping_methods( true );
		
		foreach ( $default_methods as $method ) {
			if ( $method->is_enabled() ) {
				$method_data = array(
					'id' => $method->id,
					'title' => $method->get_title(),
					'cost' => $method->get_option( 'cost' ) ?: 0,
					'zone_name' => __( 'Default Zone', 'woocommerce-deposits-provisional-shipping' )
				);
				$methods[] = $method_data;
			}
		}

		return $methods;
	}
} 