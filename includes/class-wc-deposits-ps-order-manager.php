<?php
/**
 * Order Manager for WooCommerce Deposits Provisional Shipping
 *
 * @package woocommerce-deposits-provisional-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Deposits_PS_Order_Manager class
 */
class WC_Deposits_PS_Order_Manager {

	/**
	 * Class instance
	 *
	 * @var WC_Deposits_PS_Order_Manager
	 */
	private static $instance;

	/**
	 * Get class instance
	 *
	 * @return WC_Deposits_PS_Order_Manager
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


		// Display provisional shipping on order details
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_provisional_shipping_info' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_provisional_shipping_info' ) );



		// Add order meta to order details
		add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'format_order_meta_key' ), 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_value', array( $this, 'format_order_meta_value' ), 10, 2 );

	}

	/**
	 * Check if order has payment plans or deposits
	 *
	 * @param WC_Order $order Order object
	 * @return bool
	 */
	public function has_deposit_or_payment_plan_in_order( $order ) {
		if ( ! $order ) {
			return false;
		}

		foreach ( $order->get_items() as $item ) {
			$is_deposit = $item->get_meta( '_is_deposit' );
			$payment_plan = $item->get_meta( '_payment_plan' );
			
			// Check for any deposit (regular deposit or payment plan)
			if ( 'yes' === $is_deposit ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if order has payment plans (for backward compatibility)
	 *
	 * @param WC_Order $order Order object
	 * @return bool
	 */
	public function has_payment_plan_in_order( $order ) {
		return $this->has_deposit_or_payment_plan_in_order( $order );
	}

	/**
	 * Check if payment plan or deposit is complete
	 *
	 * @param WC_Order $order Order object
	 * @return bool
	 */
	public function is_deposit_or_payment_plan_complete( $order ) {
		if ( ! $this->has_deposit_or_payment_plan_in_order( $order ) ) {
			return false;
		}

		// For regular deposits, check if the order is completed/processing
		if ( $this->has_regular_deposits_only( $order ) ) {
			$status = $order->get_status();
			
			if ( in_array( $status, array( 'completed', 'processing' ) ) ) {
				return true;
			}
			return false;
		}

		// For payment plans, check if all future payments are completed
		$future_payments = $this->get_future_payments_for_order( $order );
		
		foreach ( $future_payments as $payment ) {
			$status = $payment->get_status();
			
			if ( ! in_array( $status, array( 'completed', 'processing' ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if order has regular deposits only (no payment plans)
	 *
	 * @param WC_Order $order Order object
	 * @return bool
	 */
	public function has_regular_deposits_only( $order ) {
		if ( ! $order ) {
			return false;
		}

		$has_payment_plan = false;
		$has_regular_deposit = false;

		foreach ( $order->get_items() as $item ) {
			$is_deposit = $item->get_meta( '_is_deposit' );
			$payment_plan = $item->get_meta( '_payment_plan' );
			
			if ( 'yes' === $is_deposit ) {
				if ( ! empty( $payment_plan ) ) {
					$has_payment_plan = true;
				} else {
					$has_regular_deposit = true;
				}
			}
		}

		return $has_regular_deposit && ! $has_payment_plan;
	}

	/**
	 * Check if payment plan is complete (for backward compatibility)
	 *
	 * @param WC_Order $order Order object
	 * @return bool
	 */
	public function is_payment_plan_complete( $order ) {
		return $this->is_deposit_or_payment_plan_complete( $order );
	}

	/**
	 * Get future payments for order
	 *
	 * @param WC_Order $order Order object
	 * @return array
	 */
	public function get_future_payments_for_order( $order ) {
		$future_payments = array();

		// Get child orders (future payments)
		$child_orders = wc_get_orders( array(
			'parent' => $order->get_id(),
			'limit' => -1,
		) );

		foreach ( $child_orders as $child_order ) {
			$future_payments[] = $child_order;
		}

		return $future_payments;
	}

	/**
	 * Display provisional shipping info on order details
	 *
	 * @param WC_Order $order Order object
	 */
	public function display_provisional_shipping_info( $order ) {
		if ( ! $this->has_deposit_or_payment_plan_in_order( $order ) ) {
			return;
		}

		$provisional_method = $order->get_meta( '_wc_deposits_ps_provisional_shipping_method' );
		$provisional_cost = $order->get_meta( '_wc_deposits_ps_provisional_shipping_cost' );

		if ( $provisional_method ) {
			
			wc_get_template(
				'order-details-shipping.php',
				array(
					'order' => $order,
					'provisional_method' => $provisional_method,
					'provisional_cost' => $provisional_cost,
				),
				'',
				WC_DEPOSITS_PS_PLUGIN_PATH . '/templates/'
			);
		}
	}

	/**
	 * Display admin provisional shipping info
	 *
	 * @param WC_Order $order Order object
	 */
	public function display_admin_provisional_shipping_info( $order ) {
		if ( ! $this->has_deposit_or_payment_plan_in_order( $order ) ) {
			return;
		}

		$provisional_method = $order->get_meta( '_wc_deposits_ps_provisional_shipping_method' );
		$provisional_cost = $order->get_meta( '_wc_deposits_ps_provisional_shipping_cost' );

		if ( $provisional_method ) {
			echo '<div class="wc-deposits-ps-admin-shipping-info">';
			echo '<h3>' . esc_html__( 'Provisional Shipping Information', 'woocommerce-deposits-provisional-shipping' ) . '</h3>';
			echo '<p><strong>' . esc_html__( 'Selected Method:', 'woocommerce-deposits-provisional-shipping' ) . '</strong> ' . esc_html( $provisional_method ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Estimated Cost:', 'woocommerce-deposits-provisional-shipping' ) . '</strong> ' . wc_price( $provisional_cost ) . '</p>';
			echo '<p><em>' . esc_html__( 'Note: This is a provisional selection. The final shipping cost will be calculated and charged separately upon completion of the deposit or payment plan.', 'woocommerce-deposits-provisional-shipping' ) . '</em></p>';
			echo '</div>';
		}
	}

	/**
	 * Handle payment plan or deposit completion
	 *
	 * @param int $order_id Order ID
	 */








	/**
	 * Format order meta key for display
	 *
	 * @param string $key Meta key
	 * @param object $meta Meta object
	 * @return string
	 */
	public function format_order_meta_key( $key, $meta ) {
		$provisional_keys = array(
			'_wc_deposits_ps_provisional_shipping_method' => __( 'Provisional Shipping Method', 'woocommerce-deposits-provisional-shipping' ),
			'_wc_deposits_ps_provisional_shipping_cost' => __( 'Provisional Shipping Cost', 'woocommerce-deposits-provisional-shipping' ),
		);

		return isset( $provisional_keys[ $key ] ) ? $provisional_keys[ $key ] : $key;
	}

	/**
	 * Format order meta value for display
	 *
	 * @param string $value Meta value
	 * @param object $meta Meta object
	 * @return string
	 */
	public function format_order_meta_value( $value, $meta ) {
		if ( $meta->key === '_wc_deposits_ps_provisional_shipping_cost' ) {
			return wc_price( $value );
		}

		return $value;
	}
} 