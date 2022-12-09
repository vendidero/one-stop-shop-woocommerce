<?php

namespace Vendidero\OneStopShop;

use Vendidero\EUTaxHelper\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Main package class.
 */
class Settings {

	public static function get_sections() {
		return array(
			'' => _x( 'General', 'oss', 'one-stop-shop-woocommerce' ),
		);
	}

	public static function get_description() {
		return sprintf( _x( 'Find useful options regarding the <a href="https://ec.europa.eu/taxation_customs/business/vat/oss_en" target="_blank" rel="noopener">One Stop Shop procedure</a> here.', 'oss', 'one-stop-shop-woocommerce' ) );
	}

	public static function get_help_url() {
		return 'https://vendidero.github.io/one-stop-shop-woocommerce/';
	}

	public static function get_settings( $current_section = '' ) {
		$settings = array(
			array(
				'title' => '',
				'type'  => 'title',
				'id'    => 'oss_options',
				'desc'  => Package::is_integration() ? '' : self::get_description(),
			),

			array(
				'title'   => _x( 'OSS status', 'oss', 'one-stop-shop-woocommerce' ),
				'desc'    => _x( 'Yes, I\'m currently participating in the OSS procedure.', 'oss', 'one-stop-shop-woocommerce' ),
				'id'      => 'oss_use_oss_procedure',
				'type'    => Package::is_integration() ? 'gzd_toggle' : 'checkbox',
				'default' => 'no',
			),

			array(
				'title'   => _x( 'Observation', 'oss', 'one-stop-shop-woocommerce' ),
				'desc'    => _x( 'Automatically observe the delivery threshold of the current year.', 'oss', 'one-stop-shop-woocommerce' ) . '<p class="oss-woocommerce-additional-desc wc-gzd-additional-desc">' . _x( 'This option will automatically calculate the amount applicable for the OSS procedure delivery threshold once per day for the current year. The report will only recalculated for the days which are not yet subject to the observation to save processing time.', 'oss', 'one-stop-shop-woocommerce' ) . '</p>',
				'id'      => 'oss_enable_auto_observation',
				'type'    => Package::is_integration() ? 'gzd_toggle' : 'checkbox',
				'default' => 'yes',
			),
		);

		if ( Package::enable_auto_observer() ) {
			$settings = array_merge(
				$settings,
				array(
					array(
						'title' => sprintf( _x( 'Delivery threshold', 'oss', 'one-stop-shop-woocommerce' ) ),
						'id'    => 'oss_delivery_threshold',
						'type'  => 'html',
						'html'  => self::get_observer_report_html(),
					),
				)
			);
		}

		$settings = array_merge(
			$settings,
			array(
				array(
					'title' => _x( 'Participation', 'oss', 'one-stop-shop-woocommerce' ),
					'id'    => 'oss_switch',
					'type'  => 'html',
					'html'  => self::get_oss_switch_html(),
				),

				array(
					'title'   => _x( 'Report Order Date', 'oss', 'one-stop-shop-woocommerce' ),
					'desc'    => '<p class="oss-woocommerce-additional-desc wc-gzd-additional-desc">' . _x( 'Select the relevant order date to be used to determine whether to include an order in a report.', 'oss', 'one-stop-shop-woocommerce' ) . '</p>',
					'id'      => 'oss_report_date_type',
					'type'    => 'select',
					'default' => 'date_paid',
					'options' => array(
						'date_paid'    => _x( 'Date paid', 'oss', 'one-stop-shop-woocommerce' ),
						'date_created' => _x( 'Date created', 'oss', 'one-stop-shop-woocommerce' ),
					),
				),
			)
		);

		if ( Helper::oss_procedure_is_enabled() && wc_prices_include_tax() ) {
			$settings = array_merge(
				$settings,
				array(
					array(
						'title'   => _x( 'Fixed gross prices', 'oss', 'one-stop-shop-woocommerce' ),
						'desc'    => _x( 'Apply the same gross price regardless of the tax rate for EU countries.', 'oss', 'one-stop-shop-woocommerce' ) . '<p class="oss-woocommerce-additional-desc wc-gzd-additional-desc">' . _x( 'This option will make sure that your customers pay the same price no matter the tax rate (based on the country chosen) to be applied.', 'oss', 'one-stop-shop-woocommerce' ) . '</p>',
						'id'      => 'oss_fixed_gross_prices',
						'type'    => Package::is_integration() ? 'gzd_toggle' : 'checkbox',
						'default' => 'yes',
					),
					array(
						'title'             => _x( 'Third countries', 'oss', 'one-stop-shop-woocommerce' ),
						'desc'              => _x( 'Apply the same gross price for third countries too.', 'oss', 'one-stop-shop-woocommerce' ),
						'id'                => 'oss_fixed_gross_prices_for_third_countries',
						'type'              => Package::is_integration() ? 'gzd_toggle' : 'checkbox',
						'default'           => 'no',
						'custom_attributes' => array(
							'data-show_if_oss_fixed_gross_prices' => '',
						),
					),
				)
			);
		}

		$settings = array_merge(
			$settings,
			array(
				array(
					'type' => 'sectionend',
					'id'   => 'oss_options',
				),
			)
		);

		return $settings;
	}

	public static function get_oss_switch_link() {
		return add_query_arg( array( 'action' => 'oss_switch_procedure' ), wp_nonce_url( admin_url( 'admin-post.php' ), 'oss_switch_procedure' ) );
	}

	protected static function get_oss_switch_html() {
		ob_start();
		?>
		<p>
			<a class="button button-secondary" onclick="return confirm('<?php echo esc_html( _x( 'Are you sure? Please backup your tax rates before proceeding.', 'oss', 'one-stop-shop-woocommerce' ) ); ?>');" href="<?php echo esc_url( self::get_oss_switch_link() ); ?>"><?php echo ( Helper::oss_procedure_is_enabled() ? esc_html_x( 'End OSS participation', 'oss', 'one-stop-shop-woocommerce' ) : esc_html_x( 'Start OSS participation', 'oss', 'one-stop-shop-woocommerce' ) ); ?></a>
			<a class="oss-settings-refresh-tax-rates" onclick="return confirm('<?php echo esc_html( _x( 'Are you sure? Please backup your tax rates before proceeding.', 'oss', 'one-stop-shop-woocommerce' ) ); ?>');" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wc-status&tab=tools&action=refresh_oss_tax_rates' ), 'debug_action' ) ); ?> "><?php echo esc_html_x( 'refresh VAT rates', 'oss', 'one-stop-shop-woocommerce' ); ?></a>
			<a class="oss-settings-learn-more" href="https://vendidero.github.io/one-stop-shop-woocommerce/tax-adjustments"><?php echo esc_html_x( 'learn more', 'oss', 'one-stop-shop-woocommerce' ); ?></a>
		</p>
			<p class="oss-woocommerce-additional-desc wc-gzd-additional-desc"><?php echo esc_html_x( 'Use this option to automatically adjust tax-related options in WooCommerce. Warning: This option will delete your current tax rates and add new tax rates based on your OSS participation status.', 'oss', 'one-stop-shop-woocommerce' ); ?></p>
		<?php

		return ob_get_clean();
	}

	public static function before_save() {
		/**
		 * In case observer is switched on and the current report is outdated - queue the observer report now.
		 */
		if ( ! Package::enable_auto_observer() && isset( $_POST['oss_enable_auto_observation'] ) && Package::observer_report_is_outdated() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option( 'oss_enable_auto_observation', 'yes' );
			Package::update_observer_report();
		}

		if ( Helper::oss_procedure_is_enabled() && ( ! isset( $_POST['oss_use_oss_procedure'] ) || 'no' === wc_bool_to_string( wc_clean( wp_unslash( $_POST['oss_use_oss_procedure'] ) ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			do_action( 'woocommerce_oss_disabled_oss_procedure' );
		} elseif ( ! Helper::oss_procedure_is_enabled() && isset( $_POST['oss_use_oss_procedure'] ) && 'yes' === wc_bool_to_string( wc_clean( wp_unslash( $_POST['oss_use_oss_procedure'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			do_action( 'woocommerce_oss_enabled_oss_procedure' );
		}
	}

	public static function after_save() {

	}

	protected static function get_observer_report_html() {
		$observer_report = Package::get_completed_observer_report();

		if ( ! $observer_report || Queue::get_running_observer() ) {
			$running = Package::get_observer_report() ? Package::get_observer_report() : Queue::get_running_observer();

			$status_link = $running ? '<a href="' . esc_url( $running->get_url() ) . '">' . esc_html_x( 'See status', 'oss', 'one-stop-shop-woocommerce' ) . '</a>' : '<a href="' . esc_url( add_query_arg( array( 'action' => 'oss_init_observer' ), wp_nonce_url( admin_url( 'admin-post.php' ), 'oss_init_observer' ) ) ) . '">' . esc_html_x( 'Start initial report', 'oss', 'one-stop-shop-woocommerce' ) . '</a>';
			$status_text = sprintf( ( $running ? esc_html_x( 'Report not yet completed. %s', 'oss', 'one-stop-shop-woocommerce' ) : esc_html_x( 'Report not yet started. %s', 'oss', 'one-stop-shop-woocommerce' ) ), $status_link );
			ob_start();
			?>
			<p class="oss-observer-details"><?php echo $status_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php
			return ob_get_clean();
		}

		$total_class = 'observer-total-green';

		if ( $observer_report->get_net_total() >= Package::get_delivery_threshold() ) {
			$total_class = 'observer-total-red';
		} elseif ( $observer_report->get_net_total() >= Package::get_delivery_notification_threshold() ) {
			$total_class = 'observer-total-orange';
		}

		ob_start();
		?>
			<p class="oss-observer-details"><span class="oss-observer-total <?php echo esc_attr( $total_class ); ?>"><?php echo wc_price( $observer_report->get_net_total() ); ?></span> <?php echo esc_html_x( 'of', 'oss-amounts', 'one-stop-shop-woocommerce' ); ?> <span class="oss-observer-delivery-threshold"><?php echo wc_price( Package::get_delivery_threshold() ); ?></span> <span class="oss-observer-date-end"><?php printf( esc_html_x( 'As of: %s', 'oss', 'one-stop-shop-woocommerce' ), wc_format_datetime( $observer_report->get_date_end() ) ); ?></span> <a class="oss-settings-learn-more" href="<?php echo esc_url( $observer_report->get_url() ); ?>"><?php echo esc_html_x( 'see details', 'oss', 'one-stop-shop-woocommerce' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></p>
			<p class="oss-woocommerce-additional-desc wc-gzd-additional-desc"><?php echo wp_kses_post( sprintf( _x( 'This value indicates your current net total amount applicable for the One Stop Shop procedure delivery threshold of the current year. You should take action in case the delivery threshold is or is close to being exceeded. <a href="%s">Find out more</a> about the calculation.', 'oss', 'one-stop-shop-woocommerce' ), 'https://vendidero.github.io/one-stop-shop-woocommerce/report-calculation' ) ); ?></p>
		<?php

		return ob_get_clean();
	}

	public static function get_settings_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=oss' );
	}
}
