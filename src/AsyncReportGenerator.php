<?php

namespace Vendidero\OneStopShop;

use Automattic\WooCommerce\Utilities\NumberUtil;

defined( 'ABSPATH' ) || exit;

class AsyncReportGenerator {

	/**
	 * @var null|\WP_Error
	 */
	protected $error = null;

	protected $args = array();

	public function __construct( $args = array() ) {
		$default_start = new \WC_DateTime( 'now' );
		$default_start->modify( '-1 year' );

		$default_end = new \WC_DateTime();

		$args = wp_parse_args( $args, array(
			'start'      => $default_start->date_i18n(),
			'end'        => $default_end->date_i18n(),
			'batch_size' => 50,
			'offset'     => 0,
		) );

		$this->args  = $args;
		$this->error = new \WP_Error();
		$this->logs  = array();
	}

	protected function use_date_paid() {
		return apply_filters( 'oss_woocommerce_use_date_paid', true );
	}

	public function get_id() {
		return sanitize_key( 'oss_woocommerce_report_' . $this->args['start'] . '_' . $this->args['end'] );
	}

	public function reset() {
		delete_option( $this->get_id() . '_tmp_result' );
	}

	/**
	 * @return true|\WP_Error
	 */
	public function next() {
		$date_key = $this->use_date_paid() ? 'date_paid' : 'date_created';
		$args     = $this->args;

		/**
		 * Add/remove one day to make sure we do find orders of the same day too
		 * as the date_paid is stored as timestamp meta data.
		 */
		if ( 'date_paid' === $date_key ) {
			$args['start'] = strtotime( $args['start'] ) - DAY_IN_SECONDS;
			$args['end']   = strtotime( $args['end'] ) + DAY_IN_SECONDS;
		}

		$query_args = array(
			'limit'           => $args['batch_size'],
			'orderby'         => 'date',
			'order'           => 'ASC',
			$date_key         => $args['start'] . '...' . $args['end'],
			'offset'          => $args['offset'],
			'taxable_country' => Package::get_non_base_eu_countries(),
		);

		$orders   = wc_get_orders( $query_args );
		$tax_data = (array) get_option( $this->get_id() . '_tmp_result', array() );

		if ( ! empty( $orders ) ) {
			foreach( $orders as $order ) {
				$country = ! empty( $order->get_shipping_country() ) ? $order->get_shipping_country() : $order->get_billing_country();

				if ( ! in_array( $country, Package::get_non_base_eu_countries() ) ) {
					continue;
				}

				if ( ! isset( $tax_data[ $country ] ) ) {
					$tax_data[ $country ] = array();
				}

				foreach ( $order->get_taxes() as $key => $tax ) {
					$tax_percent = $this->get_rate_percent( $tax->get_rate_id(), $order );
					$tax_total   = (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total();

					if ( $tax_percent <= 0 || $tax_total <= 0 ) {
						continue;
					}

					if ( ! isset( $tax_data[ $country ][ $tax_percent ] ) ) {
						$tax_data[ $country ][ $tax_percent ] = array(
							'tax_total' => 0,
							'net_total' => 0,
						);
					}

					$tax_total = wc_add_number_precision( $tax_total, false );

					$tax_data[ $country ][ $tax_percent ]['tax_total'] = (float) $tax_data[ $country ][ $tax_percent ]['tax_total'];
					$tax_data[ $country ][ $tax_percent ]['tax_total'] += $tax_total;
				}

				foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
					$taxes = $item->get_taxes();

					foreach( $taxes['total'] as $tax_rate_id => $tax_total ) {
						$tax_percent = $this->get_rate_percent( $tax_rate_id, $order );

						if ( $tax_percent <= 0 || $tax_total <= 0 ) {
							continue;
						}

						if ( sizeof( $taxes['total'] ) > 1 ) {
							$net_total = ( (float) $tax_percent / 100 ) / $tax_total;
						} else {
							$net_total = (float) $item->get_total();
						}

						$net_total = wc_add_number_precision( $net_total, false );

						if ( ! isset( $tax_data[ $country ][ $tax_percent ] ) ) {
							$tax_data[ $country ][ $tax_percent ] = array(
								'tax_total' => 0,
								'net_total' => 0,
							);
						}

						$tax_data[ $country ][ $tax_percent ]['net_total'] = (float) $tax_data[ $country ][ $tax_percent ]['net_total'];
						$tax_data[ $country ][ $tax_percent ]['net_total'] += $net_total;
					}
				}
			}

			update_option( $this->get_id() . '_tmp_result', $tax_data );

			return true;
		} else {
			return new \WP_Error( 'empty', _x( 'No orders found.', 'oss', 'oss-woocommerce' ) );
		}
	}

	/**
	 * @param $rate_id
	 * @param \WC_Order $order
	 */
	protected function get_rate_percent( $rate_id, $order ) {
		$taxes      = $order->get_taxes();
		$percentage = null;

		foreach( $taxes as $tax ) {
			if ( $tax->get_rate_id() == $rate_id ) {
				if ( is_callable( array( $tax, 'get_rate_percent' ) ) ) {
					$percentage = $tax->get_rate_percent();
				}
			}
		}

		/**
		 * WC_Order_Item_Tax::get_rate_percent returns null by default.
		 * Fallback to global tax rates (DB) in case the percentage is not available within order data.
		 */
		if ( is_null( $percentage ) || '' === $percentage ) {
			$percentage = \WC_Tax::get_rate_percent_value( $rate_id );
		}

		if ( ! is_numeric( $percentage ) ) {
			$percentage = 0;
		}

		return $percentage;
	}
}