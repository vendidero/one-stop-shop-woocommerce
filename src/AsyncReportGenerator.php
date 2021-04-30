<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

class AsyncReportGenerator {

	protected $args = array();

	protected $type = '';

	public function __construct( $type = 'quarterly', $args = array() ) {
		$this->type = $type;

		$default_end   = new \WC_DateTime();
		$default_start = new \WC_DateTime( 'now' );
		$default_start->modify( '-1 year' );

		$args = wp_parse_args( $args, array(
			'start'  => $default_start->date_i18n(),
			'end'    => $default_end->date_i18n(),
			'limit'  => $this->get_batch_size(),
			'status' => $this->get_order_statuses(),
			'offset' => 0,
		) );

		foreach( array( 'start', 'end' ) as $date_field ) {
			if ( is_a( $args[ $date_field ], 'WC_DateTime' ) ) {
				$args[ $date_field ] = $args[ $date_field ]->date_i18n();
			} elseif( is_numeric( $args[ $date_field ] ) ) {
				$args[ $date_field ] = date( 'Y-m-d', $args[ $date_field ] );
			}
		}

		$this->args = $args;
	}

	public function get_type() {
		return $this->type;
	}

	public function get_batch_size() {
		return 50;
	}

	public function get_args() {
		return $this->args;
	}

	protected function use_date_paid() {
		return apply_filters( 'oss_woocommerce_use_date_paid', true );
	}

	public function get_id() {
		return sanitize_key( 'oss_' . $this->type . '_report_' . $this->args['start'] . '_' . $this->args['end'] );
	}

	public function reset() {
		delete_option( $this->get_id() . '_tmp' );
	}

	protected function get_temporary_result() {
		return (array) get_option( $this->get_id() . '_tmp_result', array() );
	}

	protected function get_order_statuses() {
		$statuses = array_keys( wc_get_order_statuses() );
		$statuses = array_diff( $statuses, array( 'wc-refunded', 'wc-pending', 'wc-cancelled', 'wc-failed' ) );

		return apply_filters( 'oss_woocommerce_valid_order_statuses', $statuses );
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
			'limit'           => $args['limit'],
			'orderby'         => 'date',
			'order'           => 'ASC',
			$date_key         => $args['start'] . '...' . $args['end'],
			'offset'          => $args['offset'],
			'taxable_country' => Package::get_non_base_eu_countries(),
			'type'            => 'shop_order',
			'status'          => $args['status']
		);

		Package::extended_log( sprintf( 'Building next order query: %s', wc_print_r( $query_args, true ) ) );

		$orders   = wc_get_orders( $query_args );
		$tax_data = $this->get_temporary_result();

		Package::extended_log( sprintf( '%d applicable orders found', sizeof( $orders ) ) );

		if ( ! empty( $orders ) ) {
			foreach( $orders as $order ) {
				$taxable_country_type = ! empty( $order->get_shipping_country() ) ? 'shipping' : 'billing';
				$taxable_country      = 'shipping' === $taxable_country_type ? $order->get_shipping_country() : $order->get_billing_country();

				if ( ! in_array( $taxable_country, Package::get_non_base_eu_countries() ) ) {
					continue;
				}

				Package::extended_log( sprintf( 'Processing order #%1$s based on %2$s country (%3$s)', $order->get_order_number(), $taxable_country_type, $taxable_country ) );

				if ( ! isset( $tax_data[ $taxable_country ] ) ) {
					$tax_data[ $taxable_country ] = array();
				}

				foreach ( $order->get_taxes() as $key => $tax ) {
					$refunded    = (float) $order->get_total_tax_refunded_by_rate_id( $tax->get_rate_id() );
					$tax_percent = $this->get_rate_percent( $tax->get_rate_id(), $order );
					$tax_total   = (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total() - $refunded;

					if ( $tax_percent <= 0 || $tax_total <= 0 ) {
						continue;
					}

					if ( ! isset( $tax_data[ $taxable_country ][ $tax_percent ] ) ) {
						$tax_data[ $taxable_country ][ $tax_percent ] = array(
							'tax_total' => 0,
							'net_total' => 0,
						);
					}

					$net_total = ( $tax_total / ( (float) $tax_percent / 100 ) );

					Package::extended_log( sprintf( 'Refunded tax %1$s = %2$s', $tax_percent, $refunded ) );
					Package::extended_log( sprintf( 'Tax total %1$s = %2$s', $tax_percent, $tax_total ) );
					Package::extended_log( sprintf( 'Net total %1$s = %2$s', $tax_percent, $net_total ) );

					$net_total = wc_add_number_precision( $net_total, false );
					$tax_total = wc_add_number_precision( $tax_total, false );

					$tax_data[ $taxable_country ][ $tax_percent ]['tax_total'] = (float) $tax_data[ $taxable_country ][ $tax_percent ]['tax_total'];
					$tax_data[ $taxable_country ][ $tax_percent ]['tax_total'] += $tax_total;

					$tax_data[ $taxable_country ][ $tax_percent ]['net_total'] = (float) $tax_data[ $taxable_country ][ $tax_percent ]['net_total'];
					$tax_data[ $taxable_country ][ $tax_percent ]['net_total'] += $net_total;
				}
			}

			update_option( $this->get_id() . '_tmp_result', $tax_data );

			return true;
		} else {
			return new \WP_Error( 'empty', _x( 'No orders found.', 'oss', 'oss-woocommerce' ) );
		}
	}

	public function complete() {
		Package::extended_log( sprintf( 'Completed called' ) );

		$tmp_result = $this->get_temporary_result();
		$result     = array(
			'countries' => array(),
			'totals'    => array(
				'net_total' => 0,
				'tax_total' => 0,
			),
		);

		foreach( $tmp_result as $country => $tax_data ) {
			$result['countries'][ $country ] = array();

			foreach( $tax_data as $percent => $totals ) {
				$result['totals']['tax_total'] += (float) $totals['tax_total'];
				$result['totals']['net_total'] += (float) $totals['net_total'];

				$result['countries'][ $country ][ $percent ] = array(
					'net_total' => (float) wc_remove_number_precision( $totals['net_total'] ),
					'tax_total' => (float) wc_remove_number_precision( $totals['tax_total'] ),
				);
			}
		}

		$result['totals']['tax_total'] = wc_format_decimal( wc_round_tax_total( wc_remove_number_precision( $result['totals']['tax_total'] ) ), '' );
		$result['totals']['net_total'] = wc_format_decimal( wc_remove_number_precision( $result['totals']['net_total'] ), '' );

		update_option( $this->get_id() . '_result', $result );
		delete_option( $this->get_id() . '_tmp_result' );

		return true;
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