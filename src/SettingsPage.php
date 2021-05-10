<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Settings_Emails.
 */
class SettingsPage extends \WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'oss';
		$this->label = _x( 'OSS', 'oss', 'oss-woocommerce' );

		parent::__construct();
	}

	public function output() {
		echo '<h2 class="oss-woocommerce-settings-title">' . _x( 'One Stop Shop', 'oss', 'oss-woocommerce' ) . ' <a class="page-title-action" href="' . admin_url( 'admin.php?page=oss-reports' ) . '">' . _x( 'Reports', 'oss', 'oss-woocommerce' ) . '</a> <a class="page-title-action" target="_blank" href="' .  Settings::get_help_url() . '">' . _x( 'Learn More', 'oss', 'oss-woocommerce' ) . '</a></h2>';

		parent::output();
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = Settings::get_sections();

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	public function save() {
		Settings::before_save();
		parent::save();
	}

	/**
	 * Get settings array.
	 *
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {
		$settings = Settings::get_settings( $current_section );

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
	}
}
