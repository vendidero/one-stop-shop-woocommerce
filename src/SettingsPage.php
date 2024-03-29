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
		$this->label = _x( 'OSS', 'oss', 'one-stop-shop-woocommerce' );

		parent::__construct();
	}

	public function output() {
		echo '<h2 class="oss-woocommerce-settings-title">' . esc_html_x( 'One Stop Shop', 'oss', 'one-stop-shop-woocommerce' ) . ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=oss-reports' ) ) . '">' . esc_html_x( 'Reports', 'oss', 'one-stop-shop-woocommerce' ) . '</a> <a class="page-title-action" target="_blank" href="' . esc_url( Settings::get_help_url() ) . '">' . esc_html_x( 'Learn More', 'oss', 'one-stop-shop-woocommerce' ) . '</a></h2>';

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
		Settings::after_save();
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

	public function get_settings_for_section_core( $section_id ) {
		return Settings::get_settings( $section_id );
	}
}
