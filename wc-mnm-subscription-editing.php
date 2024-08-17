<?php
/**
 * Plugin URI: http://www.github.com/kathyisawesome/wc-mnm-subscription-editing
 * Plugin Name: WooCommerce Mix and Match - Subscription Editing
 * Version: 1.0.1
 * Description: Mix and Match subscription container contents editing in the my account area, no cart/checkout
 * Author: Kathy Darling
 * Author URI: http://kathyisawesome.com/
 * Developer: Kathy Darling
 * Developer URI: http://kathyisawesome.com/
 * Text Domain: wc-mnm-subscription-editing
 * Domain Path: /languages
 * 
 * WC requires at least: 8.0.0
 * WC tested up to: 8.9.0
 * Requires at least: 6.1.0
 * Requires PHP: 7.4
 * 
 * GitHub Plugin URI: https://github.com/kathyisawesome/wc-mnm-subscription-editing
 * Primary Branch: trunk
 * Release Asset: true
 *
 * Copyright: © 2022 Kathy Darling
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Declare Core WooCommerce features compatibility.
 */
add_action( 'before_woocommerce_init', function() {

	if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		return;
	}

	// HPOS (Custom Order tables.
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', plugin_basename( __FILE__ ), true );

	// Cart and Checkout Blocks.
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', plugin_basename( __FILE__ ), true );
} );


/**
 * The Main WC_MNM_Subscription_Editing class
 */
if ( ! class_exists( 'WC_MNM_Subscription_Editing' ) ) :

	class WC_MNM_Subscription_Editing {

		/**
		 * constants
		 */
		const VERSION = '1.0.1';
		const REQ_MNM_VERSION = '2.7.0';

		/**
		 * var string $notice
		 */
		private static $notice = '';

		/**
		 * var bool $is_enqueued
		 */
		private static $is_enqueued = false;

		/**
		 * WC_MNM_Subscription_Editing Constructor
		 *
		 * @access 	public
		 * @return 	WC_MNM_Subscription_Editing
		 */
		public static function init() {

			// MNM check.
			if ( ! function_exists( 'wc_mix_and_match' ) || version_compare( wc_mix_and_match()->version, self::REQ_MNM_VERSION ) < 0 ) {
				self::$notice = __( 'WooCommerce Mix and Match Subscription Editing requires at least WooCommerce Mix and Match Products version <strong>%1$s</strong>. %2$s', 'wc-mnm-subscription-editing' );
				if ( ! function_exists( 'wc_mix_and_match' ) ) {
					self::$notice = sprintf( self::$notice, self::REQ_MNM_VERSION, __( 'Please install and activate WooCommerce Mix and Match Products.', 'wc-mnm-subscription-editing' ) );
				} else {
					self::$notice = sprintf( self::$notice, self::REQ_MNM_VERSION, __( 'Please update WooCommerce Mix and Match Products.', 'wc-mnm-subscription-editing' ) );
				}

				add_action( 'admin_notices', [ __CLASS__, 'admin_notice' ] );
				return false;
			}

			// Sub check.
			if ( ! class_exists( 'WC_Subscriptions_Plugin' )  ) {
				self::$notice = __( 'WooCommerce Mix and Match Subscription Editing requires WooCommerce Subscriptions. Please install and activate WooCommerce Subscriptions', 'wc-mnm-subscription-editing' );
				add_action( 'admin_notices', [ __CLASS__, 'admin_notice' ] );
				return false;
			}	

			// APFS check.
			if ( ! defined( 'WCS_ATT_VERSION' )  ) {
				self::$notice = __( 'WooCommerce Mix and Match Subscription Editing requires WooCommerce All Products for Subscriptions. Please install and activate WooCommerce All Products for Subscriptions', 'wc-mnm-subscription-editing' );
				add_action( 'admin_notices', [ __CLASS__, 'admin_notice' ] );
				return false;
			}		

			// Load translation files.
			add_action( 'init', [ __CLASS__, 'load_plugin_textdomain' ] );

			// Register Scripts.
			add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_scripts' ], 20 );

			// Load template actions/functions later.
			add_action( 'after_setup_theme', [ __CLASS__, 'template_includes' ] );

			// Display Scripts.
			add_action( 'woocommerce_account_view-subscription_endpoint', [ __CLASS__, 'attach_listener_for_scripts' ], 1 );

			// Add note when customer makes changes.
			add_action( 'wc_mnm_editing_container_in_shop_subscription', [ __CLASS__, 'add_order_note' ], 10, 4 );

			// Add custom fragment.
			add_filter( 'wc_mnm_updated_container_in_shop_subscription_fragments', [ __CLASS__, 'updated_subscription_fragments' ], 10, 4 );

			// Frontend display.
			if ( version_compare( WC_Subscriptions::$version, '4.5.0', '>=' ) ) {
				add_filter( 'woocommerce_subscriptions_switch_link_classes', [ __CLASS__, 'switch_link_classes' ], 10, 4 );
			} else {
				add_filter( 'woocommerce_subscriptions_switch_link', [ __CLASS__, 'switch_link' ], 99, 4 );
			}
			
			// Modify the edit form.
			add_action( 'wc_mnm_edit_container_order_item_in_shop_subscription', array( __CLASS__, 'attach_hooks' ), 0, 4 );

			// Reapply variable product schemes from order item.
			add_filter( 'wc_mnm_get_product_from_edit_order_item', [ __CLASS__, 'reapply_schemes' ], 100, 4 );

			// Restore the subscription state of a product fetched via ajax, using an order item as reference.
			add_filter( 'wc_mnm_get_ajax_product_variation', array( __CLASS__, 'reapply_variation_schemes' ) );

			// Tell APFS that we have forced subscription.
			add_action( 'wc_ajax_mnm_get_edit_container_order_item_form', [ __CLASS__, 'set_forced_subscription' ], 0 );

		}

		/*-----------------------------------------------------------------------------------*/
		/* Notices */
		/*-----------------------------------------------------------------------------------*/


		/**
		 * Users must update Mix and Match
		 */
		public static function admin_notice() {
			echo '<div class="notice notice-error">';
				echo wpautop( self::$notice );
			echo '</div>';
		}

		/*-----------------------------------------------------------------------------------*/
		/* Localization */
		/*-----------------------------------------------------------------------------------*/


		/**
		 * Make the plugin translation ready
		 */
		public static function load_plugin_textdomain() {
			load_plugin_textdomain( 'wc-mnm-subscription-editing' , false , dirname( plugin_basename( __FILE__ ) ) .  '/languages/' );
		}


		/*-----------------------------------------------------------------------------------*/
		/* Scripts and Styles */
		/*-----------------------------------------------------------------------------------*/

		/**
		 * Register scripts
		 */
		public static function register_scripts() {

			// Inline styles.
			$custom_css = "
				.wc-mnm-edit-container-shop_subscription .mnm_form .woocommerce-loop-product__title {
					font-size: initial;
					margin: initial;
				}
				.wc-mnm-edit-container-shop_subscription .mnm_table .product-thumbnail {
					width: 15%;
				}
				.wc-mnm-edit-container-shop_subscription .mnm_form .product-thumbnail a {
					cursor: default;
				}
				.wc-mnm-edit-container-shop_subscription .mnm_form .product-thumbnail a:active,
				.wc-mnm-edit-container-shop_subscription .mnm_form .product-thumbnail a:focus {
					border: 0;
					text-decoration: none;
					outline: none;
				}
				.wc-mnm-edit-container-shop_subscription .mnm_form p {
					font-size: initial;
				}
				.wc-mnm-edit-container-shop_subscription .mnm_form .qty:not(.mnm-checkbox) {
					max-width: 5em;
				}
				.wc-mnm-edit-container-shop_subscription .wc-mnm-cancel-edit {
					margin-left: 1em;
					margin-right: 1em;
					cursor: pointer;
				}
				.wc-mnm-edit-container-shop_subscription .wc-mnm-edit-subscription-actions { display: inline-block; }

				.woocommerce-MyAccount-content.wc-mnm-subscription-editing .order_details tfoot {
					opacity: 30%;
				}
				.woocommerce-MyAccount-content.wc-mnm-subscription-editing .layout_grid .product-thumbnail { max-width: 100%; }
			";
			wp_add_inline_style( 'wc-mnm-frontend', $custom_css );

			// Frontend scripts.
			$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

			$script_path = '/assets/js/frontend/wc-mnm-subscription-editing' .  $suffix . '.js';

			wp_register_script( 'wc-mnm-subscription-editing', plugins_url( $script_path, __FILE__ ), array( 'jquery', 'wc-add-to-cart-mnm', 'jquery-blockui', 'wp-hooks' ), wc_mix_and_match()->get_file_version( self::plugin_path() . $script_path, self::VERSION ), true );

			$params = array(
				'wc_ajax_url'               => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'edit_container_nonce'      => wp_create_nonce( 'wc_mnm_edit_container' ),
				'i18n_edit_failure_message' => _x( 'Server error. Your subscription cannot be edited at this time.', '[Frontend]', 'wc-mnm-subscription-editing' ),
			);

			wp_localize_script( 'wc-mnm-subscription-editing', 'wc_mnm_subscription_editing_params', $params );

		}

		/**
		 * Load the scripts on the my subscription page.
		 */
		public static function attach_listener_for_scripts() {
			add_action( 'woocommerce_order_item_meta_end', [ __CLASS__, 'maybe_load_scripts' ], 10, 3 );
		}

		/**
		 * Load the scripts on the my subscription page, only when a mix and match product is present.
		 * 
		 * @param int $item_id The subscription line item ID.
		 * @param WC_Order_Item|array $item The subscription line item.
		 * @param WC_Subscription $subscription The subscription.
		 */
		public static function maybe_load_scripts( $item_id, $order_item, $subscription ) {

			if ( wc_mnm_is_container_order_item( $order_item ) ) {

				if ( ! self::$is_enqueued ) {

					WC_MNM_Ajax::load_edit_scripts();
		
					if ( class_exists( 'WC_MNM_Variable' ) ) {
						WC_MNM_Variable::get_instance()->load_scripts();
					}

					wp_enqueue_script( 'jquery-blockui' );
					wp_enqueue_script( 'wc-mnm-subscription-editing' );

					self::$is_enqueued = true;

					do_action( 'wc_mnm_subscription_editing_enqueue_scripts' );
				}

				// Stash any VMNM products for preloading Store API responses in footer.
				$preloads = WC_MNM_Helpers::cache_get( 'wcMNMVariablePreloads' );

				if ( $order_item->get_variation_id() ) { 
					if ( is_array( $preloads ) ) {
						$preloads[] = $order_item->get_product_id();
					} elseif ( null === $preloads ) {
						$preloads = [ $order_item->get_product_id() ];
					}
				}	

				WC_MNM_Helpers::cache_set( 'wcMNMVariablePreloads', $preloads );

			}	

		}


		/*-----------------------------------------------------------------------------------*/
		/* Ajax                                                                              */
		/*-----------------------------------------------------------------------------------*/

	
		/**
		 * Adds order note.
		 * 
		 * @param  WC_Order_Item_Product  $new_order_item
		 * @param  WC_Order_Item_Product  $order_item - the old order item.
		 * @param  $subscription      WC_Subscription
		 * @param  string $source The originating source loading this template
		 */
		public static function add_order_note( $container_item, $old_container_item, $subscription, $source ) {

			if ( $subscription instanceof WC_Subscription && 'myaccount' === $source ) {

				if ( $container_item->get_variation_id() !== $old_container_item->get_variation_id() ) {
					$subscription->add_order_note( sprintf( esc_html__( 'Customer switched variation subscription from "%1$s" to "%2$s" via the My Account page.', 'wc-mnm-subscription-editing' ), $old_container_item->get_name(), $container_item->get_name() ) );
				} else {
					$subscription->add_order_note( sprintf( esc_html__( 'Customer modified selections for "%1$s" subscription via the My Account page.', 'wc-mnm-subscription-editing' ), $container_item->get_name() ) );
				}
				
			}

		}


		/**
		 * Adds to fragments.
		 * 
		 * @param  array $fragments     
		 * @param  $container_item WC_Order_Item
		 * @param  $subscription      WC_Subscription
		 * @param  string $context The originating source loading this template
		 * @return array
		 */
		public static function updated_subscription_fragments( $fragments, $container_item, $subscription, $context ) {

			if ( $subscription instanceof WC_Subscription && 'myaccount' === $context ) {
				// Get new order items fragment.
				ob_start();

				$include_item_removal_links = wcs_can_items_be_removed( $subscription );
				$totals                     = $subscription->get_order_item_totals();

				WCS_Template_Loader::get_subscription_totals_table_template( $subscription, $include_item_removal_links, $totals );
				$fragments[ 'table.order_details' ] = ob_get_clean();
			}

			return $fragments;

		}


		/*-----------------------------------------------------------------------------------*/
		/* Frontend display                                                                  */
		/*-----------------------------------------------------------------------------------*/

		/**
		 * Template files.
		 */
		public static function template_includes() {
			include_once 'includes/wc-mnm-subscription-editing-template-functions.php';
			include_once 'includes/wc-mnm-subscription-editing-template-hooks.php';
		}

		/**
		 * Modify upgrade/downgrade button classes as of Subs 4.5.0.
		 * 
		 * @param array $classes The switch link classes.
		 * @param int $switch_id The order item ID of a subscription line item
		 * @param array $switch_item An order line item
		 * @param object $subscription A WC_Subscription object
		 * @return array
		 */
		public static function switch_link_classes( $classes, $switch_id, $switch_item, $subscription ) {

			$product = $switch_item->get_product();

			if ( wc_mnm_is_product_container_type( $product ) ) {
				$apportion_recurring_price = get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_recurring_price', 'no' );

				$prorate_virtual = in_array( $apportion_recurring_price, array( 'virtual', 'virtual-upgrade' ) );
		
				if ( 'no' === $apportion_recurring_price || ( $prorate_virtual && ! $switch_item->is_virtual_product() ) ) {
					$classes[] = 'ajax-edit';
				}
			}

			return $classes;
		}

		/**
		 * Modify upgrade/downgrade button.
		 * 
		 * @param string $switch_link The switch link html.
		 * @param int $switch_id The order item ID of a subscription line item
		 * @param array $switch_item An order line item
		 * @param object $subscription A WC_Subscription object
		 * @return string
		 */
		public static function switch_link( $switch_link, $switch_id, $switch_item, $subscription ) {

			$product = $switch_item->get_product();

			if ( wc_mnm_is_product_container_type( $product ) ) {
				$apportion_recurring_price = get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_recurring_price', 'no' );

				$prorate_virtual = in_array( $apportion_recurring_price, array( 'virtual', 'virtual-upgrade' ) );
		
				if ( 'no' === $apportion_recurring_price || ( $prorate_virtual && ! $switch_item->is_virtual_product() ) ) {
					$switch_link = str_replace( 'class="', 'class="ajax-edit ', $switch_link );
				}
			}

			return $switch_link;
		}


		/**
		 * Customize edit container form for subscription context
		 * 
		 * @param  $product  WC_Product_Mix_and_Match
		 * @param  $order_item WC_Order_Item
		 * @param  $order      WC_Order
		 * @param  string $source The originating source loading this template
		 */
		public static function attach_hooks( $product, $order_item, $order, $source ) {

			if ( 'myaccount' === $source ) {

				// Change button texts and validation context.
				add_filter( 'wc_mnm_edit_container_button_text', [ __CLASS__, 'update_container_text' ] );

			}
			
		}

		/**
		 * Modify edit button text- only applicable when ajax loading edit form in cart.
		 * 
		 * @param  string $text
		 * @return string
		 */
		public static function update_container_text( $text = '' ) {
			return esc_html__( 'Update subscription', 'wc-mnm-subscription-editing' );
		}

		/*-----------------------------------------------------------------------------------*/
		/* Variable Mix and Match                                                            */
		/*-----------------------------------------------------------------------------------*/

		/**
		 * Reapply schemes to parent product.
		 * 
		 * @param obj WC_Product $product
		 * @param obj WC_Order_Item
		 * @param obj WC_Order
		 * @param  string $source The originating source loading this template
		 * @return WC_Product
		 */
		public static function reapply_schemes( $product, $order_item, $order, $source ) {

			$scheme_key = $scheme_key = WCS_ATT_Order::get_subscription_scheme( $order_item, array(
				'product'   => $product,
				'order'     => $order,
			) );

			// Set scheme on product object for later reference.
			if ( null !== $scheme_key ) {
				WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $scheme_key );
			}

			return $product;

		}	


		/**
		 * Attempts to restore the subscription state of a variation fetched via ajax, using an order item as reference.
		 *
		 * @param  WC_Product  $product
		 * @return WC_Product
		 */
		public static function reapply_variation_schemes( $product ) {

			if ( isset( $_POST['extra_data'] ) ) {

				if ( isset( $_POST['extra_data'] ) && isset( $_POST['extra_data']['order_item_id' ] ) ) {
					
					$order_item = new WC_Order_Item_Product( intval( $_POST['extra_data']['order_item_id' ] ) );

					$scheme_key = WCS_ATT_Order::get_subscription_scheme( $order_item, array(
						'product'   => $product,
					) );

					if ( null !== $scheme_key ) {
						WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $scheme_key );
					}

				}

			}

			return $product;
		}

		/**
		 * Trick APFS into thinking the edited subscription only has forced-subscription, and one-time purchase is not available.
		 * This lets APFS handle display of subscription strings in the editing mode.
		 */
		public static function set_forced_subscription() {
			add_filter( 'wcsatt_force_subscription', '__return_true' );
		}
		

		/*-----------------------------------------------------------------------------------*/
		/* Helpers                                                                           */
		/*-----------------------------------------------------------------------------------*/

		/**
		 * Get the plugin path.
		 *
		 * @return string
		 */
		public static function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		}


	} //end class: do not remove or there will be no more guacamole for you

endif; // end class_exists check

// Launch the whole plugin.
add_action( 'plugins_loaded', [ 'WC_MNM_Subscription_Editing', 'init' ], 20 );
