<?php
/**
 * Provisional Shipping Form Template
 *
 * @package woocommerce-deposits-provisional-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checkout_manager = WC_Deposits_PS_Checkout_Manager::get_instance();
$default_method = $checkout_manager->get_default_shipping_method();

?>

<div class="wc-deposits-ps-provisional-shipping">
	<h3><?php esc_html_e( 'Provisional Shipping Selection', 'woocommerce-deposits-provisional-shipping' ); ?></h3>
	
	<div class="provisional-notice">
		<p><?php esc_html_e( 'Since you have selected a deposit or payment plan, shipping costs will be calculated and charged separately upon completion of your payments. Please select your preferred shipping method below (this is provisional and may change based on final delivery requirements).', 'woocommerce-deposits-provisional-shipping' ); ?></p>
	</div>
	
	<div class="shipping-options">
		<?php foreach ( $available_methods as $method ) : ?>
			<label class="shipping-option">
				<input type="radio" 
					   name="provisional_shipping_method" 
					   value="<?php echo esc_attr( $method['id'] ); ?>"
					   data-cost="<?php echo esc_attr( floatval( $method['cost'] ) ); ?>"
					   <?php checked( $method['id'], $default_method ); ?>>
				<span class="method-title"><?php echo esc_html( $method['title'] ); ?></span>
				<span class="method-cost"><?php echo wc_price( $method['cost'] ); ?> (<?php esc_html_e( 'estimated', 'woocommerce-deposits-provisional-shipping' ); ?>)</span>
				<?php if ( ! empty( $method['zone_name'] ) ) : ?>
					<span class="method-zone"><?php echo esc_html( $method['zone_name'] ); ?></span>
				<?php endif; ?>
			</label>
		<?php endforeach; ?>
	</div>
	
	<div class="terms-acceptance">
		<label class="terms-checkbox">
			<input type="checkbox" name="shipping_terms_accepted" required>
			<span class="terms-text">
				<?php esc_html_e( 'I understand that shipping costs are provisional and will be calculated separately upon completion of my deposit or payment plan. The final shipping cost may differ from the estimated cost shown above.', 'woocommerce-deposits-provisional-shipping' ); ?>
			</span>
		</label>
	</div>
	
	<!-- Hidden field to store the selected shipping cost -->
	<input type="hidden" name="provisional_shipping_cost" value="<?php echo esc_attr( $available_methods[0]['cost'] ?? 0 ); ?>">
	
	<div class="provisional-shipping-display">
		<?php if ( ! empty( $available_methods ) ) : ?>
			<p class="selected-method">
				<strong><?php esc_html_e( 'Selected:', 'woocommerce-deposits-provisional-shipping' ); ?></strong> 
				<?php echo esc_html( $available_methods[0]['title'] ); ?>
				<br>
				<small><?php esc_html_e( 'Estimated cost:', 'woocommerce-deposits-provisional-shipping' ); ?> <?php echo wc_price( $available_methods[0]['cost'] ?? 0 ); ?></small>
			</p>
		<?php endif; ?>
	</div>
</div> 