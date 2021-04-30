<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

/**
 * Main package class.
 */
class Package {

	/**
	 * Version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Init the package - load the REST API Server class.
	 */
	public static function init() {
		if ( ! self::has_dependencies() ) {
			return;
		}

		self::includes();
		self::init_hooks();

		//add_action( 'admin_init', array( __CLASS__, 'test' ) );
	}

	public static function test() {
		Queue::start( 'quarterly' );

		/*$generator = new AsyncReportGenerator();
		$generator->next();
		$generator->complete();
		*/
		exit();
	}

	protected static function init_hooks() {
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( __CLASS__, 'query_taxable_country' ), 10, 2 );

		foreach( array_keys( Queue::get_available_types() ) as $type ) {
			add_action( 'oss_woocommerce_' . $type, function( $args ) use ( $type ) {
				Queue::next( $type, $args );
			}, 10, 1 );
		}
	}

	public static function query_taxable_country( $query, $query_vars ) {
		if ( ! empty( $query_vars['taxable_country'] ) ) {
			$taxable_country = is_array( $query_vars['taxable_country'] ) ? $query_vars['taxable_country'] : array( $query_vars['taxable_country'] );
			$taxable_country = wc_clean( $taxable_country );

			$query['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'relation' => 'AND',
					array(
						'key'     => '_shipping_country',
						'compare' => 'NOT_EXISTS',
					),
					array(
						'key'     => '_billing_country',
						'value'   => $taxable_country,
						'compare' => 'IN',
					),
				),
				array(
					'key'     => '_shipping_country',
					'value'   => $taxable_country,
					'compare' => 'IN',
				),
			);
		}

		return $query;
	}

	public static function has_dependencies() {
		return ( class_exists( 'WooCommerce' ) );
	}

	private static function includes() {
		// include_once self::get_path() . '/includes/wc-gzd-dhl-core-functions.php';
	}

	public static function get_non_base_eu_countries() {
		$countries    = WC()->countries->get_european_union_countries( 'eu_vat' );
		$base_country = wc_get_base_location()['country'];
		$countries    = array_diff( $countries, array( $base_country ) );

		return $countries;
	}

	public static function install() {
		self::init();
		Install::install();
	}

	public static function install_integration() {
		self::install();
	}

	public static function is_integration() {
		return class_exists( 'WooCommerce_Germanized' ) ? true : false;
	}

	/**
	 * Return the version of the package.
	 *
	 * @return string
	 */
	public static function get_version() {
		return self::VERSION;
	}

	/**
	 * Return the path to the package.
	 *
	 * @return string
	 */
	public static function get_path() {
		return dirname( __DIR__ );
	}

	/**
	 * Return the path to the package.
	 *
	 * @return string
	 */
	public static function get_url() {
		return plugins_url( '', __DIR__ );
	}

	public static function get_assets_url() {
		return self::get_url() . '/assets';
	}

	private static function define_constant( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	public static function log( $message, $type = 'info' ) {
		$logger = wc_get_logger();

		if ( ! $logger || ! apply_filters( 'one_stop_shop_woocommerce_enable_logging', true ) ) {
			return;
		}

		if ( ! is_callable( array( $logger, $type ) ) ) {
			$type = 'info';
		}

		$logger->{$type}( $message, array( 'source' => 'one-stop-shop-woocommerce' ) );
	}

	public static function extended_log( $message, $type = 'info' ) {
		if ( apply_filters( 'one_stop_shop_woocommerce_enable_extended_logging', true ) ) {
			self::log( $message, $type );
		}
	}
}