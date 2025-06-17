<?php

namespace Vendidero\OneStopShop;

use Vendidero\EUTaxHelper\Helper;

defined( 'ABSPATH' ) || exit;

class Queue {

	public static function start( $type = 'quarterly', $date = null, $end_date = null ) {
		$types = Package::get_available_report_types( true );

		if ( ! array_key_exists( $type, $types ) ) {
			return false;
		}

		$args     = self::get_timeframe( $type, $date, $end_date );
		$interval = $args['start']->diff( $args['end'] );

		/**
		 * Except observers, all new queries treat refunds separately
		 */
		if ( 'observer' !== $type ) {
			$args['order_types'] = array(
				'shop_order',
				'shop_order_refund',
			);
		}

		// Add version
		$args['version'] = Package::get_version();

		$generator  = new AsyncReportGenerator( $type, $args );
		$queue_args = $generator->get_args();
		$queue      = self::get_queue();

		self::cancel( $generator->get_id() );

		$report = $generator->start();

		if ( is_a( $report, '\Vendidero\OneStopShop\Report' ) && $report->exists() ) {
			Package::log( sprintf( 'Starting new %1$s', $report->get_title() ) );
			Package::extended_log( sprintf( 'Default report arguments: %s', wc_print_r( $queue_args, true ) ) );

			$queue->schedule_single(
				time() + 10,
				'oss_woocommerce_' . $generator->get_id(),
				array( 'args' => $queue_args ),
				'oss_woocommerce'
			);

			$running = self::get_reports_running();

			if ( ! in_array( $generator->get_id(), $running, true ) ) {
				$running[] = $generator->get_id();
			}

			update_option( 'oss_woocommerce_reports_running', $running, false );
			self::clear_cache();

			return $generator->get_id();
		}

		return false;
	}

	public static function clear_cache() {
		wp_cache_delete( 'oss_woocommerce_reports_running', 'options' );
	}

	public static function get_queue_details( $report_id ) {
		$details = array(
			'next_date'   => null,
			'link'        => admin_url( 'admin.php?page=wc-status&tab=action-scheduler&s=' . esc_attr( $report_id ) . '&status=pending' ),
			'order_count' => 0,
			'has_action'  => false,
			'is_finished' => false,
			'action'      => false,
		);

		if ( $queue = self::get_queue() ) {

			if ( $next_date = $queue->get_next( 'oss_woocommerce_' . $report_id ) ) {
				$details['next_date'] = $next_date;
			}

			$search_args = array(
				'hook'     => 'oss_woocommerce_' . $report_id,
				'status'   => \ActionScheduler_Store::STATUS_RUNNING,
				'order'    => 'DESC',
				'per_page' => 1,
			);

			$results = $queue->search( $search_args );

			/**
			 * Search for pending as fallback
			 */
			if ( empty( $results ) ) {
				$search_args['status'] = \ActionScheduler_Store::STATUS_PENDING;
				$results               = $queue->search( $search_args );
			}

			/**
			 *  Last resort: Search for completed (e.g. if no pending and no running are found - must have been completed)
			 */
			if ( empty( $results ) ) {
				$search_args['status'] = \ActionScheduler_Store::STATUS_COMPLETE;
				$results               = $queue->search( $search_args );
			}

			if ( ! empty( $results ) ) {
				$action    = array_values( $results )[0];
				$args      = $action->get_args();
				$processed = isset( $args['args']['orders_processed'] ) ? (int) $args['args']['orders_processed'] : 0;

				$details['order_count'] = absint( $processed );
				$details['has_action']  = true;
				$details['action']      = $action;
				$details['is_finished'] = $action->is_finished();
			}
		}

		return $details;
	}

	public static function get_batch_size() {
		return apply_filters( 'oss_woocommerce_report_batch_size', 25 );
	}

	public static function use_date_paid() {
		$use_date_paid = 'date_paid' === get_option( 'oss_report_date_type', 'date_paid' );

		return apply_filters( 'oss_woocommerce_report_use_date_paid', $use_date_paid );
	}

	public static function get_order_statuses() {
		$statuses = array_keys( wc_get_order_statuses() );
		$statuses = array_diff( $statuses, array( 'wc-cancelled', 'wc-failed' ) );

		if ( self::use_date_paid() ) {
			$statuses = array_diff( $statuses, array( 'wc-pending' ) );
		}

		return apply_filters( 'oss_woocommerce_valid_order_statuses', $statuses );
	}

	public static function build_posts_query( $args ) {
		global $wpdb;

		$joins = array(
			"LEFT JOIN {$wpdb->postmeta} AS mt1 ON {$wpdb->posts}.ID = mt1.post_id AND (mt1.meta_key = '_shipping_country' OR mt1.meta_key = '_billing_country')",
		);

		$taxable_countries_in = self::generate_in_query_sql( Helper::get_non_base_eu_countries( true ) );
		$post_status_in       = self::generate_in_query_sql( $args['status'] );
		$post_type_in         = self::generate_in_query_sql( isset( $args['order_types'] ) ? (array) $args['order_types'] : array( 'shop_order' ) );
		$where_country_sql    = "mt1.meta_value IN {$taxable_countries_in}";

		if ( in_array( 'shop_order_refund', $args['order_types'], true ) ) {
			$joins[]           = "LEFT JOIN {$wpdb->postmeta} AS mt1_parent ON {$wpdb->posts}.post_parent = mt1_parent.post_id AND (mt1_parent.meta_key = '_shipping_country' OR mt1_parent.meta_key = '_billing_country')";
			$where_country_sql = "( {$wpdb->posts}.post_parent > 0 AND (mt1_parent.meta_value IN {$taxable_countries_in}) ) OR ( mt1.meta_value IN {$taxable_countries_in} )";
		}

		$start_gmt      = Package::local_time_to_gmt( $args['start'] )->date( 'Y-m-d H:i:s' );
		$end_gmt        = Package::local_time_to_gmt( $args['end'] )->date( 'Y-m-d H:i:s' );
		$where_date_sql = $wpdb->prepare( "{$wpdb->posts}.post_date_gmt >= %s AND {$wpdb->posts}.post_date_gmt < %s", $start_gmt, $end_gmt );

		if ( 'date_paid' === $args['date_field'] ) {
			/**
			 * Add one day to the end date to capture timestamps (including time data) in between
			 */
			$start_gmt_timestamp = Package::local_time_to_gmt( $args['start'] )->getTimestamp();
			$end_gmt_timestamp   = Package::local_time_to_gmt( $args['end'] )->getTimestamp();

			/**
			 * Use a max end date to limit potential query results in case date_paid meta field is used.
			 * This way we will only register payments made max 2 month after the order created date.
			 */
			$max_end = Package::local_time_to_gmt( $args['end'] );
			$max_end->modify( '+2 months' );

			$joins[] = "LEFT JOIN {$wpdb->postmeta} AS mt3 ON ( {$wpdb->posts}.ID = mt3.post_id AND mt3.meta_key = '_date_paid' )";

			$where_date_sql = $wpdb->prepare(
				"( {$wpdb->posts}.post_date_gmt >= %s AND {$wpdb->posts}.post_date_gmt < %s ) 
					AND ( 
						( NOT mt3.post_id IS NULL AND (
			  		        mt3.meta_key = '_date_paid' AND mt3.meta_value >= %s AND mt3.meta_value < %s
			  	        ) ) OR ( {$wpdb->posts}.post_parent > 0 AND (
			  	            {$wpdb->posts}.post_date_gmt >= %s AND {$wpdb->posts}.post_date_gmt < %s
	                    ) )
                    )
                ",
				$start_gmt,
				$max_end->format( 'Y-m-d H:i:s' ),
				$start_gmt_timestamp,
				$end_gmt_timestamp,
				$start_gmt,
				$end_gmt
			);
		}

		$join_sql = implode( ' ', $joins );

		// @codingStandardsIgnoreStart
		$sql = $wpdb->prepare(
			"
			SELECT {$wpdb->posts}.ID as order_id FROM {$wpdb->posts}  
			$join_sql
			WHERE 1=1 
				AND ( {$wpdb->posts}.post_type IN {$post_type_in} ) AND ( {$wpdb->posts}.post_status IN {$post_status_in} ) AND ( {$where_date_sql} )
				AND ( {$where_country_sql} )
			GROUP BY {$wpdb->posts}.ID 
			ORDER BY {$wpdb->posts}.post_date ASC 
			LIMIT %d, %d",
			$args['offset'],
			$args['limit']
		);
		// @codingStandardsIgnoreEnd

		return $sql;
	}

	public static function build_hpos_query( $args ) {
		global $wpdb;

		if ( ! Package::is_hpos_enabled() || ! class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) ) {
			return self::build_posts_query( $args );
		}

		$orders_table_name      = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_orders_table_name();
		$addresses_table_name   = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_addresses_table_name();
		$operational_table_name = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_operational_data_table_name();

		$joins = array(
			"LEFT JOIN {$addresses_table_name} AS billing_address ON {$orders_table_name}.id = billing_address.order_id AND billing_address.address_type = 'billing'",
			"LEFT JOIN {$addresses_table_name} AS shipping_address ON {$orders_table_name}.id = shipping_address.order_id AND shipping_address.address_type = 'shipping'",
			"LEFT JOIN {$operational_table_name} AS operational_data ON {$orders_table_name}.id = operational_data.order_id",
		);

		$taxable_countries_in = self::generate_in_query_sql( Helper::get_non_base_eu_countries( true ) );
		$order_status_in      = self::generate_in_query_sql( $args['status'] );
		$order_type_in        = self::generate_in_query_sql( isset( $args['order_types'] ) ? (array) $args['order_types'] : array( 'shop_order' ) );
		$where_country_sql    = "((NOT shipping_address.country IS NULL AND (shipping_address.country IN {$taxable_countries_in})) or billing_address.country IN {$taxable_countries_in})";

		if ( in_array( 'shop_order_refund', $args['order_types'], true ) ) {
			$joins = array_merge(
				$joins,
				array(
					"LEFT JOIN {$addresses_table_name} AS billing_parent_address ON {$orders_table_name}.parent_order_id = billing_parent_address.order_id AND billing_parent_address.address_type = 'billing'",
					"LEFT JOIN {$addresses_table_name} AS shipping_parent_address ON {$orders_table_name}.parent_order_id = shipping_parent_address.order_id AND shipping_parent_address.address_type = 'shipping'",
					"LEFT JOIN {$operational_table_name} AS operational_parent_data ON {$orders_table_name}.parent_order_id = operational_data.order_id",
				)
			);

			$where_country_sql = $where_country_sql . " OR ( {$orders_table_name}.parent_order_id > 0 AND ((NOT shipping_parent_address.country IS NULL AND (shipping_parent_address.country IN {$taxable_countries_in})) OR billing_parent_address.country IN {$taxable_countries_in}))";
		}

		$start_gmt      = Package::local_time_to_gmt( $args['start'] )->date( 'Y-m-d H:i:s' );
		$end_gmt        = Package::local_time_to_gmt( $args['end'] )->date( 'Y-m-d H:i:s' );
		$where_date_sql = $wpdb->prepare( "{$orders_table_name}.date_created_gmt >= %s AND {$orders_table_name}.date_created_gmt < %s", $start_gmt, $end_gmt ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 'date_paid' === $args['date_field'] ) {
			/**
			 * Use a max end date to limit potential query results in case date_paid meta field is used.
			 * This way we will only register payments made max 2 month after the order created date.
			 */
			$max_end = Package::local_time_to_gmt( $args['end'] );
			$max_end->modify( '+2 months' );

			// @codingStandardsIgnoreStart
			$where_date_sql = $wpdb->prepare(
				"( {$orders_table_name}.date_created_gmt >= %s AND {$orders_table_name}.date_created_gmt < %s ) 
				AND (
					( NOT operational_data.date_paid_gmt IS NULL AND (
		                operational_data.date_paid_gmt >= %s AND operational_data.date_paid_gmt < %s
	                ) ) OR (
	                    {$orders_table_name}.parent_order_id > 0 AND (
		                    {$orders_table_name}.date_created_gmt >= %s AND {$orders_table_name}.date_created_gmt < %s
	                    )
                    )
			  	)",
				$start_gmt,
				$max_end->format( 'Y-m-d H:i:s' ),
				$start_gmt,
				$end_gmt,
				$start_gmt,
				$end_gmt
			);
			// @codingStandardsIgnoreEnd
		}

		$join_sql = implode( ' ', $joins );

		// @codingStandardsIgnoreStart
		$sql = $wpdb->prepare(
			"
			SELECT {$orders_table_name}.id as order_id FROM {$orders_table_name}  
			$join_sql
			WHERE 1=1 
				AND ( {$orders_table_name}.type IN {$order_type_in} ) AND ( {$orders_table_name}.status IN {$order_status_in} ) AND ( {$where_date_sql} )
				AND ( {$where_country_sql} )
			GROUP BY {$orders_table_name}.id 
			ORDER BY {$orders_table_name}.date_created_gmt ASC 
			LIMIT %d, %d",
			$args['offset'],
			$args['limit']
		);
		// @codingStandardsIgnoreEnd

		return $sql;
	}

	public static function build_query( $args ) {
		if ( Package::is_hpos_enabled() ) {
			return self::build_hpos_query( $args );
		} else {
			return self::build_posts_query( $args );
		}
	}

	private static function generate_in_query_sql( $values ) {
		global $wpdb;

		$in_query = array();

		foreach ( $values as $value ) {
			$in_query[] = $wpdb->prepare( "'%s'", $value ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.QuotedSimplePlaceholder
		}

		return '(' . implode( ',', $in_query ) . ')';
	}

	public static function query( $args ) {
		global $wpdb;

		$query = self::build_query( $args );

		Package::extended_log( sprintf( 'Building new query: %s', wc_print_r( $args, true ) ) );
		Package::extended_log( $query );

		return $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function cancel( $id ) {
		$data      = Package::get_report_data( $id );
		$generator = new AsyncReportGenerator( $data['type'], $data );
		$queue     = self::get_queue();
		$running   = self::get_reports_running();

		if ( self::is_running( $id ) ) {
			$running = array_diff( $running, array( $id ) );
			Package::log( sprintf( 'Cancelled %s', Package::get_report_title( $id ) ) );

			update_option( 'oss_woocommerce_reports_running', $running, false );
			self::clear_cache();
			$generator->delete();
		}

		/**
		 * Cancel outstanding events and queue new.
		 */
		$queue->cancel_all( 'oss_woocommerce_' . $id );
	}

	public static function get_queue() {
		return function_exists( 'WC' ) ? WC()->queue() : false;
	}

	public static function is_running( $id ) {
		$running = self::get_reports_running();

		if ( in_array( $id, $running, true ) && self::get_queue()->get_next( 'oss_woocommerce_' . $id ) ) {
			return true;
		}

		return false;
	}

	public static function next( $type, $args ) {
		/**
		 * Older versions didn't include refunds as separate orders
		 */
		if ( ! isset( $args['order_types'] ) ) {
			$args['order_types'] = array( 'shop_order' );
		}

		$generator = new AsyncReportGenerator( $type, $args );
		$result    = $generator->next();
		$is_empty  = false;
		$queue     = self::get_queue();

		if ( is_wp_error( $result ) ) {
			$is_empty = $result->get_error_message( 'empty' );
		}

		if ( ! $is_empty ) {
			$new_args = $generator->get_args();

			// Increase offset
			$new_args['offset'] = (int) $new_args['offset'] + (int) $new_args['limit'];

			$queue->cancel_all( 'oss_woocommerce_' . $generator->get_id() );

			Package::extended_log( sprintf( 'Starting new queue: %s', wc_print_r( $new_args, true ) ) );

			$queue->schedule_single(
				time() + 10,
				'oss_woocommerce_' . $generator->get_id(),
				array( 'args' => $new_args ),
				'oss_woocommerce'
			);
		} else {
			self::complete( $generator );
		}
	}

	/**
	 * @param AsyncReportGenerator $generator
	 */
	public static function complete( $generator ) {
		$queue = self::get_queue();
		$type  = $generator->get_type();

		/**
		 * Cancel outstanding events.
		 */
		$queue->cancel_all( 'oss_woocommerce_' . $generator->get_id() );

		$report = $generator->complete();
		$status = 'failed';

		if ( is_a( $report, '\Vendidero\OneStopShop\Report' ) && $report->exists() ) {
			$status = 'completed';
		}

		Package::log( sprintf( 'Completed %1$s. Status: %2$s', $report->get_title(), $status ) );

		self::maybe_stop_report( $report->get_id() );

		if ( 'observer' === $report->get_type() ) {
			self::update_observer( $report );
		}
	}

	/**
	 * @param Report $report
	 */
	protected static function update_observer( $report ) {
		$end  = $report->get_date_end();
		$year = $end->date_i18n( 'Y' );

		if ( ! $observer_report = Package::get_observer_report( $year ) ) {
			$observer_report = $report;
		} else {
			$observer_report->set_net_total( $observer_report->get_net_total( false ) + $report->get_net_total( false ) );
			$observer_report->set_tax_total( $observer_report->get_tax_total( false ) + $report->get_tax_total( false ) );

			foreach ( $report->get_countries() as $country ) {
				foreach ( $report->get_tax_rates_by_country( $country ) as $tax_rate ) {
					$observer_report->set_country_tax_total( $country, $tax_rate, ( $observer_report->get_country_tax_total( $country, $tax_rate, false ) + $report->get_country_tax_total( $country, $tax_rate, false ) ) );
					$observer_report->set_country_net_total( $country, $tax_rate, ( $observer_report->get_country_net_total( $country, $tax_rate, false ) + $report->get_country_net_total( $country, $tax_rate, false ) ) );
				}
			}

			// Delete the old observer report
			$observer_report->delete();
		}

		// Delete the tmp report
		$report->delete();

		$observer_report->set_date_requested( $report->get_date_requested() );

		// Use the last report date as new end date
		$observer_report->set_date_end( $report->get_date_end() );
		$observer_report->save();

		update_option( 'oss_woocommerce_observer_report_' . $year, $observer_report->get_id(), false );

		do_action( 'oss_woocommerce_updated_observer', $observer_report );
	}

	/**
	 * @return false|Report
	 */
	public static function get_running_observer() {
		$report = false;

		foreach ( self::get_reports_running() as $id ) {
			/**
			 * Make sure to return the last running observer in case more of one observer exists
			 * in running queue.
			 */
			if ( strstr( $id, 'observer_' ) ) {
				$report = Package::get_report( $id );
			}
		}

		return $report;
	}

	public static function maybe_stop_report( $report_id ) {
		$reports_running = self::get_reports_running();

		if ( in_array( $report_id, $reports_running, true ) ) {
			$reports_running = array_diff( $reports_running, array( $report_id ) );
			update_option( 'oss_woocommerce_reports_running', $reports_running, false );

			if ( $queue = self::get_queue() ) {
				$queue->cancel_all( 'oss_woocommerce_' . $report_id );
			}

			/**
			 * Force non-cached running option
			 */
			wp_cache_delete( 'oss_woocommerce_reports_running', 'options' );

			return true;
		}

		return false;
	}

	public static function get_reports_running() {
		return (array) get_option( 'oss_woocommerce_reports_running', array() );
	}

	/**
	 * PHP's +1 month only adds 31 days - we do not want that.
	 * We want to add +X month and find the last day of this month.
	 *
	 * @see https://stackoverflow.com/questions/3602405/php-datetimemodify-adding-and-subtracting-months
	 *
	 * @param \WC_DateTime $datetime
	 * @param integer $number_of_months
	 *
	 * @return \WC_DateTime
	 */
	protected static function add_months_to_datetime( $datetime, $number_of_months ) {
		$datetime  = clone $datetime;
		$month_str = 1 === $number_of_months ? 'month' : 'months';

		$datetime->modify( "first day of +{$number_of_months} {$month_str}" );
		$datetime->modify( '+' . ( $datetime->format( 't' ) - 1 ) . ' days' );

		return $datetime;
	}

	public static function get_timeframe( $type, $date = null, $date_end = null ) {
		$date_start      = null;
		$date_end        = is_null( $date_end ) ? null : $date_end;
		$start_indicator = is_null( $date ) ? Package::string_to_datetime( 'now' ) : $date;

		if ( ! is_a( $start_indicator, 'WC_DateTime' ) && is_numeric( $start_indicator ) ) {
			$start_indicator = Package::string_to_datetime( $start_indicator );
		}

		if ( ! is_null( $date_end ) && ! is_a( $date_end, 'WC_DateTime' ) && is_numeric( $date_end ) ) {
			$date_end = Package::string_to_datetime( $date_end );
		}

		if ( 'quarterly' === $type ) {
			$month       = $start_indicator->date( 'n' );
			$quarter     = (int) ceil( $month / 3 );
			$start_month = 'Jan';

			if ( 2 === $quarter ) {
				$start_month = 'Apr';
			} elseif ( 3 === $quarter ) {
				$start_month = 'Jul';
			} elseif ( 4 === $quarter ) {
				$start_month = 'Oct';
			}

			$date_start = Package::string_to_datetime( 'first day of ' . $start_month . ' ' . $start_indicator->date( 'Y' ) . ' midnight' );
			$date_end   = clone( $date_start );
			$date_end   = self::add_months_to_datetime( $date_end, 3 );
		} elseif ( 'monthly' === $type ) {
			$month      = $start_indicator->date( 'M' );
			$date_start = Package::string_to_datetime( 'first day of ' . $month . ' ' . $start_indicator->date( 'Y' ) . ' midnight' );

			$date_end = clone $date_start;
			$date_end = self::add_months_to_datetime( $date_end, 1 );
		} elseif ( 'yearly' === $type ) {
			$date_start = clone $start_indicator;
			$date_start->modify( 'first day of jan ' . $start_indicator->date( 'Y' ) . ' midnight' );

			$date_end = clone $date_start;
			$date_end->modify( '+1 year' );
		} elseif ( 'observer' === $type ) {
			$date_start = clone $start_indicator;
			$report     = Package::get_observer_report( $date_start->date( 'Y' ) );

			if ( ! $report ) {
				// Calculate starting with the first day of the current year until yesterday
				$date_end   = clone $date_start;
				$date_end   = Package::string_to_datetime( $date_end->date( 'Y-m-d' ) . ' midnight' );
				$date_start = Package::string_to_datetime( 'first day of jan ' . $start_indicator->date( 'Y' ) . ' midnight' );
			} else {
				// In case a report has already been generated lets do only calculate the timeframe between the end of the last report and yesterday
				$date_end = Package::string_to_datetime( $start_indicator->date( 'Y-m-d' ) . ' midnight' );

				$date_start = clone $report->get_date_end();
				$date_start->modify( '+1 day' );

				if ( $date_start > $date_end ) {
					$date_start = clone $date_end;
				}
			}
		} else {
			if ( is_null( $date_end ) ) {
				$date_end = clone $start_indicator;
				$date_end->modify( '-1 year' );
			}

			$date_start = clone $start_indicator;
		}

		return array(
			'start' => $date_start,
			'end'   => $date_end,
		);
	}
}
