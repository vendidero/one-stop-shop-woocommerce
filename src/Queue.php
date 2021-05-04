<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

class Queue {

	public static function start( $type = 'quarterly', $date = null, $end_date = null ) {
		$types = Package::get_available_types();

		if ( ! array_key_exists( $type, $types ) ) {
			return false;
		}

		$args       = self::get_timeframe( $type, $date, $end_date );
		$interval   = $args['start']->diff( $args['end'] );

		// Minimum span is 1d
		if ( $interval->d <= 0 ) {
			return false;
		}

		$generator  = new AsyncReportGenerator( $type, $args );
		$queue_args = $generator->get_args();
		$queue      = self::get_queue();

		self::cancel( $generator->get_id() );

		$report = $generator->start();

		if ( is_a( $report, '\Vendidero\OneStopShop\Report' ) && $report->exists() ) {
			$reports_available = Queue::get_report_ids();

			if ( ! in_array( $report->get_id(), $reports_available[ $type ] ) ) {
				array_unshift( $reports_available[ $type ], $report->get_id() );
				update_option( 'oss_woocommerce_reports', $reports_available );
			}

			Package::log( sprintf( 'Starting new %1$s', $report->get_title() ) );
			Package::extended_log( sprintf( 'Default report arguments: %s', wc_print_r( $queue_args, true ) ) );

			$queue->schedule_single(
				time() + 10,
				'oss_woocommerce_' . $generator->get_id(),
				array( 'args' => $queue_args ),
				'oss_woocommerce'
			);

			$running = self::get_reports_running();
			$running[ $generator->get_id() ] = 'pending';

			update_option( 'oss_woocommerce_reports_running', $running );

			return $generator->get_id();
		}

		return false;
	}

	public static function cancel( $id ) {
		$data      = Package::get_report_data( $id );
		$generator = new AsyncReportGenerator( $data['type'], $data );
		$queue     = self::get_queue();
		$running   = self::get_reports_running();

		$generator->delete();

		if ( array_key_exists( $id, $running ) ) {
			unset( $running[ $id ] );
			Package::log( sprintf( 'Cancelled %s', Package::get_report_title( $id ) ) );
			update_option( 'oss_woocommerce_reports_running', $running );
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

		if ( array_key_exists( $id, $running ) && 'pending' === $running[ $id ] ) {
			if ( self::get_queue()->get_next( 'oss_woocommerce_' . $id ) ) {
				return true;
			}
		}

		return false;
	}

	public static function is_completed( $id ) {
		$running = self::get_reports_running();

		if ( array_key_exists( $id, $running ) && 'completed' === $running[ $id ] ) {
			if ( ! self::get_queue()->get_next( 'oss_woocommerce_' . $id ) ) {
				return true;
			}
		}

		return false;
	}

	public static function next( $type, $args ) {
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
			$new_args['offset'] = $new_args['offset'] + $new_args['limit'];

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
			$reports_available = Queue::get_report_ids();
			$status            = 'completed';

			if ( 'observer' !== $report->get_type() && ! in_array( $report->get_id(), $reports_available[ $type ] ) ) {
				array_unshift( $reports_available[ $type ], $report->get_id() );
				update_option( 'oss_woocommerce_reports', $reports_available );
			}
		}

		Package::log( sprintf( 'Completed %1$s. Status: %2$s', $report->get_title(), $status ) );

		$running = self::get_reports_running();

		if ( ! array_key_exists( $generator->get_id(), $running ) ) {
			$running[ $generator->get_id() ] = $status;
		} else {
			$running[ $generator->get_id() ] = $status;
		}

		update_option( 'oss_woocommerce_reports_running', $running );

		if ( 'observer' === $report->get_type() ) {
			self::update_observer( $report );
		}
	}

	public static function get_observer_report( $year = null ) {
		if ( is_null( $year ) ) {
			$year = date( 'Y' );
		}

		$report_id = get_option( 'oss_woocommerce_observer_report_' . $year );
		$report    = false;

		if ( ! empty( $report_id ) ) {
			$report = Package::get_report( $report_id );
		}

		return $report;
	}

	/**
	 * @param Report $report
	 */
	protected static function update_observer( $report ) {
		$end  = $report->get_date_end();
		$year = $end->date( 'Y' );

		if ( ! $observer_report = self::get_observer_report( $year ) ) {
			$observer_report = $report;
		} else {
			$observer_report->set_net_total( $observer_report->get_net_total( false ) + $report->get_net_total( false ) );
			$observer_report->set_tax_total( $observer_report->get_tax_total( false ) + $report->get_tax_total( false ) );

			foreach( $report->get_countries() as $country ) {
				foreach( $report->get_tax_rates_by_country( $country ) as $tax_rate ) {
					$observer_report->set_country_tax_total( $country, $tax_rate, ( $observer_report->get_country_tax_total( $country, $tax_rate, false ) + $report->get_country_tax_total( $country, $tax_rate, false ) ) );
					$observer_report->get_country_net_total( $country, $tax_rate, ( $observer_report->get_country_net_total( $country, $tax_rate, false ) + $report->get_country_net_total( $country, $tax_rate, false ) ) );
				}
			}

			// Delete the tmp report
			$report->delete();
		}

		$observer_report->set_date_requested( $report->get_date_requested() );
		$observer_report->set_id( $report->get_id() );
		$observer_report->save();

		update_option( 'oss_woocommerce_observer_report_' . $year, $observer_report->get_id() );
	}

	public static function get_report_ids() {
		$reports = (array) get_option( 'oss_woocommerce_reports', array() );

		foreach( array_keys( Package::get_available_types() ) as $type ) {
			if ( ! array_key_exists( $type, $reports ) ) {
				$reports[ $type ] = array();
			}
		}

		return $reports;
	}

	public static function get_reports_running() {
		return (array) get_option( 'oss_woocommerce_reports_running', array() );
	}

	public static function get_timeframe( $type, $date = null, $date_end = null ) {
		$date_start      = null;
		$date_end        = is_null( $date_end ) ? null : $date_end;
		$start_indicator = is_null( $date ) ? new \WC_DateTime() : $date;

		if ( ! is_a( $start_indicator, 'WC_DateTime' ) && is_numeric( $start_indicator ) ) {
			$start_indicator = new \WC_DateTime( "@" . $start_indicator );
		}

		if ( ! is_null( $date_end ) && ! is_a( $date_end, 'WC_DateTime' ) && is_numeric( $date_end ) ) {
			$date_end = new \WC_DateTime( "@" . $date_end );
		}

		if ( 'quarterly' === $type ) {
			$month       = $start_indicator->date( 'n' );
			$quarter     = (int) ceil( $month / 3 );
			$start_month = 'Jan';
			$end_month   = 'Mar';

			if ( 2 === $quarter ) {
				$start_month = 'Apr';
				$end_month   = 'Jun';
			} elseif ( 3 === $quarter ) {
				$start_month = 'Jul';
				$end_month   = 'Sep';
			} elseif ( 4 === $quarter ) {
				$start_month = 'Oct';
				$end_month   = 'Dec';
			}

			$date_start = new \WC_DateTime( "first day of " . $start_month . " " . $start_indicator->format( 'Y' ) . " midnight" );
			$date_end   = new \WC_DateTime( "last day of " . $end_month . " " . $start_indicator->format( 'Y' ) . " midnight" );
		} elseif ( 'monthly' === $type ) {
			$month = $start_indicator->format( 'M' );

			$date_start = new \WC_DateTime( "first day of " . $month . " " . $start_indicator->format( 'Y' ) . " midnight" );
			$date_end   = new \WC_DateTime( "last day of " . $month . " " . $start_indicator->format( 'Y' ) . " midnight" );
		} elseif ( 'yearly' === $type ) {
			$date_end   = clone $start_indicator;
			$date_start = clone $start_indicator;

			$date_end->modify( "last day of dec " . $start_indicator->format( 'Y' ) . " midnight" );
			$date_start->modify( "first day of jan " . $start_indicator->format( 'Y' ) . " midnight" );
		} elseif ( 'observer' === $type ) {
			$date_start = clone $start_indicator;
			$report     = self::get_observer_report( $date_start->format( 'Y' ) );

			if ( ! $report ) {
				// Calculate starting with the first day of the current year until yesterday
				$date_end   = clone $date_start;
				$date_start = new \WC_DateTime( "first day of jan " . $start_indicator->format( 'Y' ) . " midnight" );
			} else {
				// In case a report has already been generated lets do only calculate the timeframe between the end of the last report and now
				$date_end   = clone $date_start;
				$date_end->setTime( 0, 0 );

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

		/**
		 * Always set start and end time to midnight
		 */
		if ( $date_start ) {
			$date_start->setTime( 0, 0 );
		}

		if ( $date_end ) {
			$date_end->setTime( 0, 0 );
		}

		return array(
			'start' => $date_start,
			'end'   => $date_end
		);
	}
}