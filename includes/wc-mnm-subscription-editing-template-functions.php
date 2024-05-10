<?php
/**
 * Template functions
 *
 * Functions for the templating system.
 *
 * @package  WooCommerce Mix and Match Subscription Editing\Functions
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wc_mnm_template_edit_container_button' ) ) {
	/**
	 * Edit container template for Mix and Match products.
	 * 
	 * @param WC_Mix_and_Match_Product $product
	 * @param WC_Order_Item $order_item
	 * @param WC_Order $order
	 * @param  string $source The originating source loading this template
	 */
	function wc_mnm_template_edit_container_button( $product, $order_item, $order, $source ) {

		if ( $source === 'myaccount' ) {

			// Load the edit container template.
			wc_get_template(
				'edit-order-item/update-container-button.php',
				array(
					'order_item' => $order_item,
					'order'      => $order,
					'container'  => $order_item->get_product(),
					'button_text' => apply_filters( 'wc_mnm_edit_container_button_text', __( 'Update subscription', 'wc-mnm-subscription-editing' ), $order_item, $order ),
				),
				'',
				WC_MNM_Subscription_Editing::plugin_path() . '/templates/'
			);
		}
	}
}

if ( ! function_exists( 'wc_mnm_template_edit_subscription_heading' ) ) {
	/**
	 * Headline prompt text.
	 * 
	 * @param WC_Mix_and_Match_Product $product
	 * @param WC_Order_Item $order_item
	 * @param WC_Order $order
	 * @param  string $source The originating source loading this template
	 */
	function wc_mnm_template_edit_subscription_heading( $product, $order_item, $order, $source) {

		if ( $source === 'myaccount' ) {
			echo '<h3>' . sprintf( esc_html__( 'Edit selections for "%s"', 'wc-mnm-subscription-editing' ), $product->get_name() ) . '</h3>';
		}
		
	}
}

if ( ! function_exists( 'wc_mnm_template_edit_cancel_link' ) ) {
	/**
	 * Display "Cancel edit" link.
	 *
	 * @param WC_Mix_and_Match_Product $product
	 * @param WC_Order_Item $order_item
	 * @param WC_Order $order
	 * @param  string $source The originating source loading this template
	 */
	function wc_mnm_template_edit_cancel_link( $product, $order_item, $order, $source ) {

		if ( $source === 'myaccount' ) {
			// translators: %1$s Screen reader text opening <span> %2$s Product title %3$s Closing </span>
			$cancel_text = sprintf( esc_html_x( 'Cancel edit %1$soptions for %2$s%3$s', 'edit subscription cancel link text', 'wc-mnm-subscription-editing' ),
				'<span class="screen-reader-text">',
				$order_item->get_name(),
				'</span>'
			);
			echo '<div class="wc-mnm-edit-subscription-actions woocommerce-cart-form">
					<button class="button alt wc-mnm-cancel-edit' . esc_attr( WC_MNM_Core_Compatibility::wp_theme_get_element_class_name( 'button' ) ) .' wc-block-components-button outlined"> ' . wp_kses_post( $cancel_text ) . '</button>
				</div>';
		}
	}
}