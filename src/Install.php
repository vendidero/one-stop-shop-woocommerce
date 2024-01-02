<?php

namespace Vendidero\OneStopShop;

use Vendidero\EUTaxHelper\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Main package class.
 */
class Install {

	public static function install() {
		$current_version = get_option( 'one_stop_shop_woocommerce', null );
		$is_update       = null !== $current_version && version_compare( $current_version, Package::get_version(), '<' );

		update_option( 'one_stop_shop_woocommerce', Package::get_version() );

		if ( ! Package::has_dependencies() ) {
			if ( is_admin() ) {
				ob_start();
				Package::dependency_notice();
				$notice = ob_get_clean();

				wp_die( wp_kses_post( $notice ) );
			} else {
				return;
			}
		}

		if ( $is_update ) {
			Helper::apply_tax_rate_changesets();
		}

		self::add_options();
	}

	private static function add_options() {
		foreach ( Settings::get_sections() as $section ) {
			foreach ( Settings::get_settings( $section ) as $setting ) {
				if ( isset( $setting['default'] ) && isset( $setting['id'] ) ) {
					wp_cache_delete( $setting['id'], 'options' );

					$autoload = isset( $setting['autoload'] ) ? (bool) $setting['autoload'] : true;
					add_option( $setting['id'], $setting['default'], '', ( $autoload ? 'yes' : 'no' ) );
				}
			}
		}
	}
}
