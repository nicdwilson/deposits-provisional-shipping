<?php
/**
 * Order Details Shipping Template
 *
 * @package woocommerce-deposits-provisional-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wc-deposits-ps-order-shipping-info">
	<h3><?php esc_html_e( 'Provisional Shipping Information', 'woocommerce-deposits-provisional-shipping' ); ?></h3>
	
	<div class="provisional-shipping-details">
		<p>
			<strong><?php esc_html_e( 'Selected Method:', 'woocommerce-deposits-provisional-shipping' ); ?></strong> 
			<?php echo esc_html( $provisional_method ); ?>
		</p>
		
		<p>
			<strong><?php esc_html_e( 'Estimated Cost:', 'woocommerce-deposits-provisional-shipping' ); ?></strong> 
			<?php echo wc_price( $provisional_cost ); ?>
		</p>
		
		<div class="provisional-notice">
			<p>
				<em><?php esc_html_e( 'Note: This is a provisional selection. The final shipping cost will be calculated and charged separately upon completion of your deposit or payment plan.', 'woocommerce-deposits-provisional-shipping' ); ?></em>
			</p>
		</div>
	</div>
	

</div> 