<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

class Tax {

	public static function init() {
		add_action( 'woocommerce_product_options_tax', array( __CLASS__, 'tax_product_options' ), 10 );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_options' ), 10, 1 );
	}

	/**
	 * @param \WC_Product $product
	 */
	public static function save_product_options( $product ) {
		$tax_classes       = self::get_product_tax_classes( $product );
		$product_tax_class = $product->get_tax_class();

		$posted        = isset( $_POST['_tax_class_by_countries'] ) ? wc_clean( (array) $_POST['_tax_class_by_countries'] ) : array();
        $new_classes   = isset( $_POST['_tax_class_by_countries_new_tax_class'] ) ? wc_clean( (array) $_POST['_tax_class_by_countries_new_tax_class'] ) : array();
		$new_countries = isset( $_POST['_tax_class_by_countries_new_countries'] ) ? wc_clean( (array) $_POST['_tax_class_by_countries_new_countries'] ) : array();

		foreach( $tax_classes as $country => $tax_class ) {
		    // Delete missing tax classes (e.g. removed by the user)
		    if ( ! isset( $posted[ $country ] ) ) {
		        unset( $tax_classes[ $country ] );
            }
        }

		foreach( $new_countries as $key => $country ) {
		    if ( empty( $country ) ) {
		        continue;
            }

		    if ( ! array_key_exists( $country, $tax_classes ) && isset( $new_classes[ $key ] ) ) {
		        $tax_classes[ $country ] = $new_classes[ $key ];
            }
        }

		/**
		 * Remove tax classes which match the products main tax class or the base country
		 */
		foreach( $tax_classes as $country => $tax_class ) {
		    if ( $tax_class == $product_tax_class || $country === wc_get_base_location()['country'] ) {
		        unset( $tax_classes[ $country ] );
            }
        }

		$product->update_meta_data( '_tax_class_by_countries', $tax_classes );
    }

	public static function tax_product_options() {
		global $product_object;

		$tax_classes    = self::get_product_tax_classes( $product_object );
		$countries_left = Package::get_non_base_eu_countries();

		if ( ! empty( $tax_classes ) ) {
			foreach( $tax_classes as $country => $tax_class ) {
				$countries_left = array_diff( $countries_left, array( $country ) );

				woocommerce_wp_select(
					array(
						'id'          => '_tax_class_by_countries[' . $country . ']',
						'value'       => $tax_class,
						'label'       => sprintf( _x( 'Tax class (%s)', 'oss', 'oss-woocommerce' ), $country ),
						'options'     => wc_get_product_tax_class_options(),
						'description' => '<a href="#" class="oss-remove-tax-class-by-country remove delete red" data-country="' . esc_attr( $country ) . '">' . _x( 'remove', 'oss', 'oss-woocommerce' ) . '</a>',
					)
				);
			}
		}

		?>
		<p class="form-field oss-add-tax-class-by-country hide_if_grouped hide_if_external">
            <label>&nbsp;</label>
			<a href="#" class="oss-add-new-tax-class-by-country">+ <?php _ex( 'Add country specific tax class (OSS)', 'oss', 'oss-woocommerce' ); ?></a>
        </p>

        <div class="oss-add-tax-class-by-country-template">
            <p class="form-field">
                <label for="tax_class_countries">
                    <select class="enhanced select" name="_tax_class_by_countries_new_countries[]">
                        <option value="" selected="selected"><?php _ex( 'Select country', 'oss', 'oss-woocommerce' ); ?></option>
		                <?php
		                foreach ( $countries_left as $country_code ) {
			                echo '<option value="' . esc_attr( $country_code ) . '">' . esc_html( WC()->countries->get_countries()[ $country_code ] ) . '</option>';
		                }
		                ?>
                    </select>
                </label>
                <select class="enhanced select short" name="_tax_class_by_countries_new_tax_class[]">
		            <?php
		            foreach ( wc_get_product_tax_class_options() as $key => $value ) {
			            echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</option>';
		            }
		            ?>
                </select>
            </p>
        </div>
		<?php
	}

	/**
	 * @param \WC_Product $product
	 */
	public static function get_product_tax_class_by_country( $product, $country ) {
		$tax_classes = self::get_product_tax_classes( $product );
		$tax_class   = $product->get_tax_class();

		if ( array_key_exists( $country, $tax_classes ) ) {
			$tax_class = $tax_classes[ $country ];
		}

		return $tax_class;
	}

	/**
	 * @param \WC_Product $product
	 */
	public static function get_product_tax_classes( $product ) {
		$tax_classes = $product->get_meta( '_tax_class_by_countries', true );
		$tax_classes = ( ! is_array( $tax_classes ) || empty( $tax_classes ) ) ? array() : $tax_classes;

		return $tax_classes;
	}

	public static function adjust_tax_rates() {
		$tax_class_slugs = self::get_tax_class_slugs();

		foreach( $tax_class_slugs as $tax_class_type => $class ) {
			/**
			 * Maybe create missing tax classes
			 */
			if ( false === $class ) {
				switch( $tax_class_type ) {
					case "reduced":
						\WC_Tax::create_tax_class( __( 'Reduced rate', 'woocommerce' ) );
						break;
					case "greater-reduced":
						\WC_Tax::create_tax_class( _x( 'Greater reduced rate', 'oss', 'oss-woocommerce' ) );
						break;
					case "super-reduced":
						\WC_Tax::create_tax_class( _x( 'Super reduced rate', 'oss', 'oss-woocommerce' ) );
						break;
				}
			}

			$new_rates = array();

			foreach( self::get_eu_tax_rates() as $country => $rates ) {
				switch( $tax_class_type ) {
					case "greater-reduced":
						if ( sizeof( $rates['reduced'] ) > 1 ) {
							$new_rates[ $country ] = $rates['reduced'][1];
						}
						break;
					case "reduced":
						if ( ! empty( $rates['reduced'] ) ) {
							$new_rates[ $country ] = $rates['reduced'][0];
						}
						break;
					default:
						if ( isset( $rates[ $tax_class_type ] ) ) {
							$new_rates[ $country ] = $rates[ $tax_class_type ];
						}
						break;
				}
			}

			self::import_rates( $new_rates, $class );
		}
	}

	public static function get_tax_class_slugs() {
		$tax_classes               = \WC_Tax::get_tax_class_slugs();
		$reduced_tax_class         = false;
		$greater_reduced_tax_class = false;
		$super_reduced_tax_class   = false;

		/**
		 * Try to determine the reduced tax rate class
		 */
		foreach( $tax_classes as $slug ) {
			if ( strstr( $slug, 'virtual' ) ) {
				continue;
			}

			if ( strstr( $slug, sanitize_title( _x( 'Greater reduced rate', 'oss', 'oss-woocommerce' ) ) ) ) {
				$greater_reduced_tax_class = $slug;
			} elseif ( strstr( $slug, sanitize_title( _x( 'Super reduced rate', 'oss', 'oss-woocommerce' ) ) ) ) {
				$super_reduced_tax_class = $slug;
			} elseif ( strstr( $slug, sanitize_title( __( 'Reduced rate', 'woocommerce' ) ) ) ) {
				$reduced_tax_class = $slug;
			} elseif ( strstr( $slug, 'reduced' ) && ! $reduced_tax_class ) {
				$reduced_tax_class = $slug;
			}
		}

		return apply_filters( 'oss_woocommerce_tax_rate_class_slugs', array(
			'reduced'         => $reduced_tax_class,
			'greater-reduced' => $greater_reduced_tax_class,
			'super-reduced'   => $super_reduced_tax_class,
			'standard'        => '',
		) );
	}

	public static function get_eu_tax_rates() {
		/**
		 * @see https://europa.eu/youreurope/business/taxation/vat/vat-rules-rates/index_en.htm
		 */
		$rates = array(
			'AT' => array(
				'standard' => 20,
				'reduced'  => array( 10, 13 )
			),
			'BE' => array(
				'standard' => 21,
				'reduced'  => array( 6, 12 )
			),
			'BG' => array(
				'standard' => 20,
				'reduced'  => array( 9 )
			),
			'CY' => array(
				'standard' => 19,
				'reduced'  => array( 5, 9 )
			),
			'CZ' => array(
				'standard' => 21,
				'reduced'  => array( 10, 15 )
			),
			'DE' => array(
				'standard' => 19,
				'reduced'  => array( 7 )
			),
			'DK' => array(
				'standard' => 25,
				'reduced'  => array()
			),
			'EE' => array(
				'standard' => 20,
				'reduced'  => array( 9 )
			),
			'GR' => array(
				'standard' => 24,
				'reduced'  => array( 6, 13 )
			),
			'ES' => array(
				'standard'      => 21,
				'reduced'       => array( 10 ),
				'super-reduced' => 4
			),
			'FI' => array(
				'standard' => 24,
				'reduced'  => array( 10, 14 )
			),
			'FR' => array(
				'standard'      => 20,
				'reduced'       => array( 5.5, 10 ),
				'super-reduced' => 2.1
			),
			'HR' => array(
				'standard' => 25,
				'reduced'  => array( 5, 13 )
			),
			'HU' => array(
				'standard' => 27,
				'reduced'  => array( 5, 18 )
			),
			'IE' => array(
				'standard'      => 23,
				'reduced'       => array( 9, 13.5 ),
				'super-reduced' => 4.8
			),
			'IT' => array(
				'standard'      => 22,
				'reduced'       => array( 5, 10 ),
				'super-reduced' => 4
			),
			'LT' => array(
				'standard' => 21,
				'reduced'  => array( 5, 9 )
			),
			'LU' => array(
				'standard'      => 17,
				'reduced'       => array( 8 ),
				'super-reduced' => 3
			),
			'LV' => array(
				'standard' => 21,
				'reduced'  => array( 12, 5 )
			),
			'MC' => array(
				'standard'      => 20,
				'reduced'       => array( 5.5, 10 ),
				'super-reduced' => 2.1
			),
			'MT' => array(
				'standard' => 18,
				'reduced'  => array( 5, 7 )
			),
			'NL' => array(
				'standard' => 21,
				'reduced'  => array( 9 )
			),
			'PL' => array(
				'standard' => 23,
				'reduced'  => array( 5, 8 )
			),
			'PT' => array(
				'standard' => 23,
				'reduced'  => array( 6, 13 )
			),
			'RO' => array(
				'standard' => 19,
				'reduced'  => array( 5, 9 )
			),
			'SE' => array(
				'standard' => 25,
				'reduced'  => array( 6, 12 )
			),
			'SI' => array(
				'standard' => 22,
				'reduced'  => array( 9.5 )
			),
			'SK' => array(
				'standard' => 20,
				'reduced'  => array( 10 )
			),
		);

		return $rates;
	}

	public static function import_rates( $rates, $tax_class = '' ) {
		global $wpdb;

		// Delete rates
		$wpdb->delete( $wpdb->prefix . 'woocommerce_tax_rates', array( 'tax_rate_class' => $tax_class ), array( '%s' ) );
		$count = 0;

		foreach ( $rates as $iso => $rate ) {
			$_tax_rate = array(
				'tax_rate_country'  => $iso,
				'tax_rate_state'    => '',
				'tax_rate'          => (string) number_format( (double) wc_clean( $rate ), 4, '.', '' ),
				'tax_rate_name'     => sprintf( _x( 'VAT %s', 'oss-tax-rate-import', 'oss-woocommerce' ), ( $iso . ( ! empty( $tax_class ) ? ' ' . $tax_class : '' ) ) ),
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => ( strstr( $tax_class, 'virtual' ) ? 0 : 1 ),
				'tax_rate_order'    => $count++,
				'tax_rate_class'    => $tax_class
			);

			\WC_Tax::_insert_tax_rate( $_tax_rate );
		}
	}
}