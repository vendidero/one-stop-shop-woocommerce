<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Settings_Emails.
 */
class Settings extends \WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'oss';
		$this->label = _x( 'OSS', 'oss', 'oss-woocommerce' );

		parent::__construct();
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = array(
			'' => _x( 'Reports', 'oss', 'oss-woocommerce' ),
		);

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Get settings array.
	 *
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {
		$settings = array();

		if ( '' === $current_section ) {

		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
	}

	/**
	 * Output the settings.
	 */
	public function output() {
		global $current_section;

		if ( '' === $current_section ) {

		} else {
			$settings = $this->get_settings( $current_section );
			\WC_Admin_Settings::output_fields( $settings );
		}
	}

	/**
	 * Save settings.
	 */
	public function save() {
		global $current_section;

		if ( ! $current_section ) {
			WC_Admin_Settings::save_fields( $this->get_settings() );

		} else {
			$wc_emails = WC_Emails::instance();

			if ( in_array( $current_section, array_map( 'sanitize_title', array_keys( $wc_emails->get_emails() ) ), true ) ) {
				foreach ( $wc_emails->get_emails() as $email_id => $email ) {
					if ( sanitize_title( $email_id ) === $current_section ) {
						do_action( 'woocommerce_update_options_' . $this->id . '_' . $email->id );
					}
				}
			} else {
				do_action( 'woocommerce_update_options_' . $this->id . '_' . $current_section );
			}
		}
	}
}
