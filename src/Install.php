<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

/**
 * Main package class.
 */
class Install {

	public static function install() {
		$current_version = get_option( 'one_stop_shop_woocommerce', null );

		update_option( 'one_stop_shop_woocommerce', Package::get_version() );
	}
}