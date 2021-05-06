<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

/**
 * Main package class.
 */
class Settings {

	public static function get_settings() {
		return array(
			array( 'title' => '', 'type' => 'title', 'id' => 'oss_options' ),

			array(
				'title'    => _x( 'OSS status', 'oss', 'oss-woocommerce' ),
				'desc'     => _x( 'Yes, I\'m currently participating in the OSS procedure.', 'oss', 'oss-woocommerce' ),
				'id'       => 'oss_use_oss_procedure',
				'type'     => Package::is_integration() ? 'gzd_toggle' : 'checkbox',
				'default'  => 'no',
			),

			array(
				'title'    => sprintf( _x( 'Delivery threshold %s', 'oss', 'oss-woocommerce' ), date( 'Y' ) ),
				'id'       => 'oss_delivery_threshold',
				'type'     => 'html',
				'html'     => self::get_observer_report_html(),
				'desc_tip' => _x( 'This is the amount applicable for the OSS procedure for the current year.', 'oss', 'oss-woocommerce' ),
			),

			array(
				'title'    => _x( 'Participation', 'oss', 'oss-woocommerce' ),
				'id'       => 'oss_switch',
				'type'     => 'html',
				'html'     => self::get_oss_switch_html(),
			),

			array( 'type' => 'sectionend', 'id' => 'oss_options' ),
		);
	}

	public static function oss_is_enabled() {
		return 'yes' === get_option( 'oss_use_oss_procedure' );
	}

	public static function get_oss_switch_link() {
		return  add_query_arg( array( 'action' => 'oss_switch' ), wp_nonce_url( admin_url( 'admin-post.php' ), 'oss-switch' ) );
	}

	protected static function get_oss_switch_html() {
		ob_start();
		?>
		<p>
			<a class="button button-secondary" href="<?php echo self::get_oss_switch_link(); ?>"><?php echo ( self::oss_is_enabled() ? _x( 'End OSS participation', 'oss', 'oss-woocommerce' ) : _x( 'Start OSS participation', 'oss', 'oss-woocommerce' ) ); ?></a>
			<a class="" href="#"><?php _ex( 'learn more', 'oss', 'oss-woocommerce' ); ?></a>
		</p>
			<p class="desc smaller"><?php _ex( 'Use this option to automatically adjust tax-related options in WooCommerce. Warning: This option will delete your current tax rates and add new tax rates based on your OSS participation status.', 'oss', 'oss-woocommerce' ); ?></p>
		<?php

		return ob_get_clean();
	}

	protected static function get_observer_report_html() {
		$observer_report = Package::get_observer_report();
		$total_class     = 'observer-total-green';

		if ( $observer_report->get_net_total() >= Package::get_delivery_threshold() ) {
			$total_class = 'observer-total-red';
		} elseif ( $observer_report->get_net_total() >= Package::get_delivery_notification_threshold() ) {
			$total_class = 'observer-total-orange';
		}

		ob_start();
		?>
			<p class="oss-observer-details"><span class="oss-observer-total <?php echo esc_attr( $total_class ); ?>"><?php echo wc_price( $observer_report->get_net_total() ); ?></span> <?php _ex( 'of', 'oss-amounts', 'oss-woocommerce' ); ?> <span class="oss-observer-delivery-threshold"><?php echo wc_price( Package::get_delivery_threshold() ); ?></span> <a href="<?php echo esc_url( $observer_report->get_url() ); ?>"><?php _ex( 'see details', 'oss', 'oss-woocommerce' ); ?></a></p>
		<?php

		return ob_get_clean();
	}

	public static function get_settings_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=oss' );
	}
}