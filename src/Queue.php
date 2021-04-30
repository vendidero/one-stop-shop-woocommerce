<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

class Queue {

	public static function get_available_types() {
		return array(
			'quarterly' => _x( 'Quarterly', 'oss', 'oss-woocommerce' ),
			'yearly'    => _x( 'Yearly', 'oss', 'oss-woocommerce' ),
			'monthly'   => _x( 'Monthly', 'oss', 'oss-woocommerce' )
		);
	}

	public static function get_type_title( $type ) {
		$types = self::get_available_types();

		return array_key_exists( $type, $types ) ? $types[ $type ] : '';
	}

	public static function start( $type = 'quarterly', $date = null ) {
		$types = self::get_available_types();

		if ( ! array_key_exists( $type, $types ) ) {
			return false;
		}

		$args       = self::get_timeframe( $type, $date );
		$generator  = new AsyncReportGenerator( $type, $args );
		$queue_args = $generator->get_args();
		$queue      = self::get_queue();

		self::cancel( $type, $args );

		Package::log( sprintf( 'Starting new %1$s report for %2$s - %3$s', self::get_type_title( $type ), $queue_args['start'], $queue_args['end'] ) );
		Package::extended_log( sprintf( 'Default report arguments: %s', wc_print_r( $queue_args, true ) ) );

		$queue->schedule_single(
			time() + 10,
			'oss_woocommerce_' . $type,
			array( 'args' => $queue_args ),
			'oss_woocommerce'
		);

		$running = self::get_reports_running();
		$running[ $type ] = array( 'id' => $generator->get_id(), 'status' => 'pending' );

		update_option( 'oss_woocommerce_reports_running', $running );

		return true;
	}

	public static function cancel( $type, $args ) {
		$generator = new AsyncReportGenerator( $type, $args );
		$queue     = self::get_queue();

		$generator->reset();

		$running = self::get_reports_running();

		if ( array_key_exists( $type, $running ) ) {
			unset( $running[ $type ] );
			Package::log( sprintf( 'Cancelled %s report', self::get_type_title( $type ) ) );
			update_option( 'oss_woocommerce_reports_running', $running );
		}

		/**
		 * Cancel outstanding events and queue new.
		 */
		$queue->cancel_all( 'oss_woocommerce_' . $type );
	}

	public static function get_queue() {
		return WC()->queue();
	}

	public static function is_running( $type ) {
		$running = self::get_reports_running();

		if ( array_key_exists( $type, $running ) && 'pending' === $running[ $type ]['status'] ) {
			if ( self::get_queue()->get_next( 'oss_woocommerce_' . $type ) ) {
				return true;
			}
		}

		return false;
	}

	public static function is_completed( $type ) {
		$running = self::get_reports_running();

		if ( array_key_exists( $type, $running ) && 'completed' === $running[ $type ]['status'] ) {
			if ( ! self::get_queue()->get_next( 'oss_woocommerce_' . $type ) ) {
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
				'oss_woocommerce_' . $type,
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
		$queue->cancel_all( 'oss_woocommerce_' . $type );

		$success = $generator->complete();
		$status  = 'failed';

		if ( $success ) {
			$reports_available = Queue::get_report_keys();
			$status            = 'completed';

			if ( ! in_array( $generator->get_id(), $reports_available ) ) {
				$reports_available[ $type ][] = $generator->get_id();

				update_option( 'oss_woocommerce_reports', $reports_available );
			}
		}

		Package::log( sprintf( 'Completed %1$s report. Status: %2$s', self::get_type_title( $type ), $status ) );

		$running = self::get_reports_running();

		if ( ! array_key_exists( $type, $running ) ) {
			$running[ $type ] = array(
				'status' => $status,
				'id'     => $generator->get_id()
			);
		} else {
			$running[ $type ]['status'] = $status;
		}

		update_option( 'oss_woocommerce_reports_running', $running );
	}

	protected static function get_report_keys() {
		$reports = (array) get_option( 'oss_woocommerce_reports', array() );

		foreach( array_keys( self::get_available_types() ) as $type ) {
			if ( ! array_key_exists( $type, $reports ) ) {
				$reports[ $type ] = array();
			}
		}

		return $reports;
	}

	public static function get_reports_running() {
		return (array) get_option( 'oss_woocommerce_reports_running', array() );
	}

	public static function get_timeframe( $type, $date = null ) {
		$date_start      = null;
		$date_end        = null;
		$start_indicator = is_null( $date ) ? new \WC_DateTime() : $date;

		if ( ! is_a( $start_indicator, 'WC_DateTime' ) && is_numeric( $start_indicator ) ) {
			$start_indicator = new \WC_DateTime( "@" . $start_indicator );
		}

		if ( 'quarterly' === $type ) {
			$month       = $start_indicator->date_i18n( 'n' );
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

			$date_start = new \WC_DateTime( "first day of " . $start_month . " " . $start_indicator->date_i18n( 'Y' ) . " midnight" );
			$date_end   = new \WC_DateTime( "last day of " . $end_month . " " . $start_indicator->date_i18n( 'Y' ) . " midnight" );
		} elseif ( 'monthly' ) {
			$month = $start_indicator->date_i18n( 'M' );

			$date_start = new \WC_DateTime( "first day of " . $month . " " . $start_indicator->date_i18n( 'Y' ) . " midnight" );
			$date_end   = new \WC_DateTime( "last day of " . $month . " " . $start_indicator->date_i18n( 'Y' ) . " midnight" );
		} else {
			$date_end   = clone $start_indicator;
			$date_start = clone $date_end;

			$date_start->modify( '-1 year' );
		}

		return array(
			'start' => $date_start,
			'end'   => $date_end
		);
	}
}