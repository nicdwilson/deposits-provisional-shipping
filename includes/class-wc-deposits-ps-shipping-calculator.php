<?php
/**
 * Shipping Calculator for WooCommerce Deposits Provisional Shipping
 *
 * @package woocommerce-deposits-provisional-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Deposits_PS_Shipping_Calculator class
 */
class WC_Deposits_PS_Shipping_Calculator {

	/**
	 * Class instance
	 *
	 * @var WC_Deposits_PS_Shipping_Calculator
	 */
	private static $instance;

	/**
	 * Get class instance
	 *
	 * @return WC_Deposits_PS_Shipping_Calculator
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
		// Add AJAX handlers for shipping calculations
		add_action( 'wp_ajax_wc_deposits_ps_calculate_shipping', array( $this, 'ajax_calculate_shipping' ) );
		add_action( 'wp_ajax_nopriv_wc_deposits_ps_calculate_shipping', array( $this, 'ajax_calculate_shipping' ) );
	}

	/**
	 * Calculate final shipping cost for an order
	 *
	 * @param WC_Order $order Order object
	 * @return float
	 */
	public function calculate_final_shipping_cost( $order ) {
		// Get order data
		$shipping_address = array(
			'country' => $order->get_shipping_country(),
			'state' => $order->get_shipping_state(),
			'postcode' => $order->get_shipping_postcode(),
			'city' => $order->get_shipping_city(),
			'address_1' => $order->get_shipping_address_1(),
			'address_2' => $order->get_shipping_address_2(),
		);

		// Calculate package weight and dimensions
		$package = $this->calculate_package_details( $order );

		// Get available shipping methods for the final destination
		$available_methods = $this->get_available_shipping_methods_for_address( $shipping_address, $package );

		// Select the best shipping method (could be based on cost, speed, etc.)
		$selected_method = $this->select_best_shipping_method( $available_methods );

		return $selected_method ? $selected_method['cost'] : 0;
	}

	/**
	 * Calculate package details from order items
	 *
	 * @param WC_Order $order Order object
	 * @return array
	 */
	public function calculate_package_details( $order ) {
		$package = array(
			'contents' => array(),
			'weight' => 0,
			'length' => 0,
			'width' => 0,
			'height' => 0,
		);

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			
			if ( ! $product ) {
				continue;
			}

			$package['contents'][] = array(
				'product_id' => $product->get_id(),
				'quantity' => $item->get_quantity(),
				'data' => $product,
			);

			// Add weight
			$weight = $product->get_weight();
			if ( $weight ) {
				$package['weight'] += $weight * $item->get_quantity();
			}

			// Add dimensions (use the largest item's dimensions for simplicity)
			$length = $product->get_length();
			$width = $product->get_width();
			$height = $product->get_height();

			if ( $length && $width && $height ) {
				$package['length'] = max( $package['length'], $length );
				$package['width'] = max( $package['width'], $width );
				$package['height'] = max( $package['height'], $height );
			}
		}

		return $package;
	}

	/**
	 * Get available shipping methods for an address
	 *
	 * @param array $address Shipping address
	 * @param array $package Package details
	 * @return array
	 */
	public function get_available_shipping_methods_for_address( $address, $package ) {
		$methods = array();

		// Get shipping zones
		$shipping_zones = WC_Shipping_Zones::get_zones();

		foreach ( $shipping_zones as $zone ) {
			$zone_obj = WC_Shipping_Zones::get_zone( $zone['zone_id'] );
			
			// Check if address matches zone
			if ( $this->address_matches_zone( $address, $zone_obj ) ) {
				$shipping_methods = $zone_obj->get_shipping_methods( true );
				
				foreach ( $shipping_methods as $method ) {
					if ( $method->is_enabled() ) {
						// Calculate cost for this method
						$cost = $this->calculate_method_cost( $method, $package );
						
						if ( $cost !== false ) {
							$methods[] = array(
								'id' => $method->id,
								'title' => $method->get_title(),
								'cost' => $cost,
								'zone_name' => $zone['zone_name'],
							);
						}
					}
				}
			}
		}

		// Add default zone methods
		$default_zone = WC_Shipping_Zones::get_zone( 0 );
		$default_methods = $default_zone->get_shipping_methods( true );
		
		foreach ( $default_methods as $method ) {
			if ( $method->is_enabled() ) {
				$cost = $this->calculate_method_cost( $method, $package );
				
				if ( $cost !== false ) {
					$methods[] = array(
						'id' => $method->id,
						'title' => $method->get_title(),
						'cost' => $cost,
						'zone_name' => __( 'Default Zone', 'woocommerce-deposits-provisional-shipping' ),
					);
				}
			}
		}

		return $methods;
	}

	/**
	 * Check if address matches shipping zone
	 *
	 * @param array $address Shipping address
	 * @param WC_Shipping_Zone $zone Shipping zone
	 * @return bool
	 */
	public function address_matches_zone( $address, $zone ) {
		$zone_locations = $zone->get_zone_locations();
		
		foreach ( $zone_locations as $location ) {
			if ( $location->type === 'country' && $location->code === $address['country'] ) {
				return true;
			}
			
			if ( $location->type === 'state' && $location->code === $address['country'] . ':' . $address['state'] ) {
				return true;
			}
			
			if ( $location->type === 'postcode' ) {
				// Simple postcode matching - could be enhanced
				if ( strpos( $address['postcode'], $location->code ) === 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Calculate cost for a shipping method
	 *
	 * @param WC_Shipping_Method $method Shipping method
	 * @param array $package Package details
	 * @return float|false
	 */
	public function calculate_method_cost( $method, $package ) {
		// Get base cost
		$cost = $method->get_option( 'cost' );
		
		if ( ! $cost ) {
			$cost = 0;
		}

		// Add weight-based costs
		if ( $package['weight'] > 0 ) {
			$weight_costs = $method->get_option( 'weight_costs' );
			if ( $weight_costs ) {
				$cost += $this->calculate_weight_cost( $package['weight'], $weight_costs );
			}
		}

		// Add item-based costs
		$item_costs = $method->get_option( 'item_costs' );
		if ( $item_costs ) {
			$cost += $this->calculate_item_cost( count( $package['contents'] ), $item_costs );
		}

		return $cost;
	}

	/**
	 * Calculate weight-based cost
	 *
	 * @param float $weight Package weight
	 * @param string $weight_costs Weight costs configuration
	 * @return float
	 */
	public function calculate_weight_cost( $weight, $weight_costs ) {
		// Parse weight costs (format: "weight:cost,weight:cost")
		$costs = array();
		$weight_cost_pairs = explode( ',', $weight_costs );
		
		foreach ( $weight_cost_pairs as $pair ) {
			$parts = explode( ':', $pair );
			if ( count( $parts ) === 2 ) {
				$costs[ floatval( $parts[0] ) ] = floatval( $parts[1] );
			}
		}

		// Find applicable cost
		$applicable_cost = 0;
		foreach ( $costs as $weight_threshold => $cost ) {
			if ( $weight <= $weight_threshold ) {
				$applicable_cost = $cost;
				break;
			}
		}

		return $applicable_cost;
	}

	/**
	 * Calculate item-based cost
	 *
	 * @param int $item_count Number of items
	 * @param string $item_costs Item costs configuration
	 * @return float
	 */
	public function calculate_item_cost( $item_count, $item_costs ) {
		// Parse item costs (format: "count:cost,count:cost")
		$costs = array();
		$item_cost_pairs = explode( ',', $item_costs );
		
		foreach ( $item_cost_pairs as $pair ) {
			$parts = explode( ':', $pair );
			if ( count( $parts ) === 2 ) {
				$costs[ intval( $parts[0] ) ] = floatval( $parts[1] );
			}
		}

		// Find applicable cost
		$applicable_cost = 0;
		foreach ( $costs as $item_threshold => $cost ) {
			if ( $item_count <= $item_threshold ) {
				$applicable_cost = $cost;
				break;
			}
		}

		return $applicable_cost;
	}

	/**
	 * Select the best shipping method from available options
	 *
	 * @param array $available_methods Available shipping methods
	 * @return array|false
	 */
	public function select_best_shipping_method( $available_methods ) {
		if ( empty( $available_methods ) ) {
			return false;
		}

		// Sort by cost (lowest first)
		usort( $available_methods, function( $a, $b ) {
			return $a['cost'] <=> $b['cost'];
		} );

		// Return the cheapest option
		return $available_methods[0];
	}

	/**
	 * AJAX handler for shipping calculations
	 */
	public function ajax_calculate_shipping() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wc_deposits_ps_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'woocommerce-deposits-provisional-shipping' ) );
		}

		$order_id = intval( $_POST['order_id'] );
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( __( 'Order not found.', 'woocommerce-deposits-provisional-shipping' ) );
		}

		$final_cost = $this->calculate_final_shipping_cost( $order );

		wp_send_json_success( array(
			'cost' => $final_cost,
			'formatted_cost' => wc_price( $final_cost ),
		) );
	}
} 