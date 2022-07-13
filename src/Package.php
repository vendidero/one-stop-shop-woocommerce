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
	const VERSION = '1.2.5';

	/**
	 * Init the package
	 */
	public static function init() {
		if ( ! self::has_dependencies() ) {
			if ( ! self::is_integration() ) {
				add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
			}

			return;
		}

		\Vendidero\TaxHelper\Package::init();

		self::init_hooks();

		if ( is_admin() ) {
			Admin::init();
		}

		Tax::init();
	}

	protected static function init_hooks() {
		if ( ! self::is_integration() ) {
			add_action( 'init', array( __CLASS__, 'load_plugin_textdomain' ) );
		}

		add_filter( 'oss_woocommerce_enable_auto_observer', array( __CLASS__, 'enable_auto_observer' ) );
		add_filter( 'oss_woocommerce_oss_procedure_is_enabled', array( __CLASS__, 'oss_procedure_is_enabled' ) );
	}

	public static function dependency_notice() {
		?>
		<div class="error notice notice-error"><p><?php echo esc_html_x( 'To use the OSS for WooCommerce plugin please make sure that WooCommerce is installed and activated.', 'oss', 'oss-woocommerce' ); ?></p></div>
		<?php
	}

	public static function oss_procedure_is_enabled() {
		return 'yes' === get_option( 'oss_use_oss_procedure' );
	}

	public static function enable_auto_observer() {
		return 'yes' === get_option( 'oss_enable_auto_observation' );
	}

	public static function string_to_datetime( $time_string ) {
		if ( is_string( $time_string ) && ! is_numeric( $time_string ) ) {
			$time_string = strtotime( $time_string );
		}

		$date_time = $time_string;

		if ( is_numeric( $date_time ) ) {
			$date_time = new \WC_DateTime( "@{$date_time}", new \DateTimeZone( 'UTC' ) );
		}

		if ( ! is_a( $date_time, 'WC_DateTime' ) ) {
			return null;
		}

		return $date_time;
	}

	public static function load_plugin_textdomain() {
		if ( function_exists( 'determine_locale' ) ) {
			$locale = determine_locale();
		} else {
			// @todo Remove when start supporting WP 5.0 or later.
			$locale = is_admin() ? get_user_locale() : get_locale();
		}

		$locale = apply_filters( 'plugin_locale', $locale, 'oss-woocommerce' );

		unload_textdomain( 'oss-woocommerce' );
		load_textdomain( 'oss-woocommerce', trailingslashit( WP_LANG_DIR ) . 'oss-woocommerce/oss-woocommerce-' . $locale . '.mo' );
		load_plugin_textdomain( 'oss-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/i18n/languages/' );
	}

	public static function has_dependencies() {
		return ( class_exists( 'WooCommerce' ) );
	}

	public static function install() {
		self::init();
		Install::install();
	}

	public static function deactivate() {
		if ( self::has_dependencies() && Admin::supports_wc_admin() ) {
			foreach ( Admin::get_notes() as $oss_note ) {
				Admin::delete_wc_admin_note( $oss_note );
			}
		}
	}

	public static function install_integration() {
		self::install();
	}

	public static function is_integration() {
		$gzd_installed = class_exists( 'WooCommerce_Germanized' );
		$gzd_version   = get_option( 'woocommerce_gzd_version', '1.0' );

		return $gzd_installed && version_compare( $gzd_version, '3.5.0', '>=' ) ? true : false;
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
}
