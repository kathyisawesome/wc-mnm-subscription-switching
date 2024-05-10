/* global wc_mnm_subscription_editing_params */
;( function( $ ) {

	/**
	 * Main container object.
	 */
	function WC_MNM_Subscription_Editing() {
    
		/**
		 * Object initialization.
		 */
		this.initialize = function() {

			/**
			 * Bind event handlers.
			 */
			this.bind_event_handlers();

		};

		/**
		 * Events.
		 */
		this.bind_event_handlers = function() {
			$( '.woocommerce-MyAccount-content' ).on( 'click.wc-mnm-subscription-editing', '.mnm_table_container .wcs-switch-link.ajax-edit', this.loadForm );
			$( '.woocommerce-MyAccount-content' ).on( 'click.wc-mnm-subscription-editing', '.wc-mnm-cancel-edit', this.cancel );
			$( '.woocommerce-MyAccount-content' ).on( 'submit.wc-mnm-subscription-editing', '.mnm_form, .variable_mnm_form', this.updateSubscription );

			$( '.woocommerce-MyAccount-content' ).on( 'click.zowc-mnm-subscription-editing', '.mnm_child_product_image img', this.cancelClick );

			$( document.body ).on( 'wc_mnm_subscription_updated_fragments_refreshed', this.scroll );
			$( document.body ).on( 'wc_mnm_edit_container_in_shop_subscription_cancel', this.scroll );

			$( document.body).on( 'wc_mnm_scroll_to_variation', function() { return false; } );

			$( '.woocommerce-MyAccount-content' ).on( 'wc_mnm_variation_form_loaded.wc-mnm-subscription-editing', '.variable_mnm_form', this.onFoundVariation );
			
		};

		/**
		 * Load the selected MNM product.
		 */
		this.loadForm = function(e) {

			e.preventDefault();

			let target_url      = $(this).attr( 'href' );
			let url             = new URL( target_url );

			let subscription_id = url.searchParams.get( 'switch-subscription' );
			let item_id         = url.searchParams.get( 'item' );

			let $content        = $( '.woocommerce-MyAccount-content' );
			let $tbody          = $content.find( '.shop_table.order_details tbody' );
			let $containerRow   = $(this).closest( '.mnm_table_container' );
			let $all_rows       = $containerRow.nextAll( '.mnm_table_item' ).addBack();
			let columns         = $containerRow.find( 'td' ).length;

			// If currently processing... or clicking on same item, quit now.
			if ( $tbody.is( '.processing' ) ) {
				return false;
			} else if ( ! $tbody.is( '.processing' ) ) {
				$tbody.addClass( 'processing' );

				if ( typeof $.fn.block === 'function' ) {
					$tbody.block( {
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					} );
				}
				
			}

			$.ajax( {

				url: wc_mnm_subscription_editing_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'mnm_get_edit_container_order_item_form' ),
				type: 'POST',
				data: {
					item_id     : item_id,
					dataType    : 'json',
					order_id    : subscription_id,
					security    : wc_mnm_subscription_editing_params.edit_container_nonce,
					source      : 'myaccount'
				},
				success: function( response ) {

					if ( response.success && response.data ) {

						$content.addClass( 'wc-mnm-subscription-editing' );
					
						$all_rows.fadeOut();

						// Insert display row:
						let $editRow = $( `<tr class="wc-mnm-subscription-edit-row" data-subscription_id="${subscription_id}" data-item_id="${item_id}"><td colspan="${columns}" ><div class="wc-mnm-edit-container"></div></td></tr>` ).insertBefore( $containerRow );
							
						if ( 'object' === typeof response.data ) {
							$.each( response.data, function( key, value ) {
								$( key ).replaceWith( value );
							});

							// Initilize MNM scripts.
							if ( response.data[ 'div.wc-mnm-edit-container' ] ) {
								// Re-attach the replaced result div.
								let $result = $editRow.find( '.wc-mnm-edit-container' );

								$result.find( 'form' ).each( function() {
									let type = $(this).data( 'product_type' ) || 'mix-and-match';

									// Launch the Mix and Match validation scrtips. Share the current script source with mini-extensions.
									$(this).trigger( `wc-mnm-initialize.${type}` ).data( 'extra_data', { 'order_item_id': item_id } );
								} );

							}

						}

						$( document.body ).trigger( 'wc_mnm_edit_container_in_shop_subscription_fragments_refreshed', [ response.data ] );

					} else {
						location.href = target_url;
					}
					
				},
				complete: function() {
					$tbody.removeClass( 'processing' ).unblock();
				},
				fail: function() {
					location.href = target_url;
				}
			} );

		};

		/**
		 * Cancel edit.
		 */
		this.cancel = function(e) {
			e.preventDefault();
			let $content      = $( '.woocommerce-MyAccount-content' );
			let $editRow      = $(this).closest( '.wc-mnm-subscription-edit-row' );
			let $containerRow = $editRow.next( '.mnm_table_container' );
			let $all_rows     = $containerRow.nextAll( '.mnm_table_item' ).addBack();

			$content.removeClass( 'wc-mnm-subscription-editing' );

			$editRow.fadeOut().remove();
			$all_rows.fadeIn();

			$( document.body ).trigger( 'wc_mnm_edit_container_in_shop_subscription_cancel' );

		};


		/**
		 * Update the subscription
		 */
		this.updateSubscription = function(e) {

			e.preventDefault();

			let $content = $( '.woocommerce-MyAccount-content' );
			let $editRow = $(this).closest( '.wc-mnm-subscription-edit-row' );
			let Form     = $(this).wc_get_mnm_script();

			// If currently processing... or clicking on same item, quit now.
			if ( $editRow.is( '.processing' ) ) {
				return false;
			} else if ( ! $editRow.is( '.processing' ) ) {
				$editRow.addClass( 'processing' ).block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
			}

			$.ajax( {
				url: wc_mnm_subscription_editing_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'mnm_update_container_order_item' ),
				type: 'POST',
				data: {
					order_id       : $editRow.data( 'subscription_id' ),
					subscription_id: $editRow.data( 'subscription_id' ),
					variation_id   : 'undefined' !== typeof $editRow.data( 'variation_id' ) ? $editRow.data( 'variation_id' ) : 0,
					item_id        : $editRow.data( 'item_id' ),
					security       : wc_mnm_subscription_editing_params.edit_container_nonce,
					config         : Form.api.get_container_config(),
					source         : 'myaccount'
				},
				success: function( response ) {

					if ( response.success && response.data ) {
						if ( 'object' === typeof response.data ) {
							$.each( response.data, function( key, value ) {
								$( key ).replaceWith( value );
							});
						} else {
							$( '.woocommerce-MyAccount-content .wc-mnm-cancel-edit' ).trigger( 'click' );
						}

						// Remove the edit form.
						$editRow.remove();

						$( document.body ).trigger( 'wc_mnm_subscription_updated_fragments_refreshed', [ response.data ] );

					} else {
						window.alert( response.data );
					}

				},
				complete: function() {
					$editRow.removeClass( 'processing' ).unblock();
					$content.removeClass( 'wc-mnm-subscription-editing' );
				},
				fail: function() {
					window.alert( wc_mnm_subscription_editing_params.i18n_edit_failure_message );
				}
			} );

		};

		// When variation is found, update variation ID.
		this.onFoundVariation = function( event, variation ) {
			let $editRow = $(this).closest( '.wc-mnm-subscription-edit-row' );
			$editRow.data( 'variation_id', variation.variation_id );
		};



		/**
		 * Cancel thumbnail click.
		 */
		this.cancelClick = function(e) {
			e.preventDefault();
		};


		/**
		 * Scroll to totals
		 */
		this.scroll = function() {
			$( 'html, body' ).animate( {
				scrollTop: ( $( '.shop_table.order_details' ).offset().top - 100 )
			}, 1000 );
		};
		  
		// Launch.
		this.initialize();
  
	} // End WC_MNM_Subscription_Editing.
  
	/*-----------------------------------------------------------------*/
	/*  Initialization.                                                */
	/*-----------------------------------------------------------------*/
  
	new WC_MNM_Subscription_Editing( $(this) );
	  
} ) ( jQuery );
  