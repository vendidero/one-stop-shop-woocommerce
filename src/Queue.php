<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

class Queue {

	public static function get_available_types() {
		return array(
			'QUARTERLY' => _x( 'Quarterly', 'oss', 'oss-woocommerce' ),
			'YEARLY'    => _x( 'Yearly', 'oss', 'oss-woocommerce' )
		);
	}

	public static function start( $type = 'QUARTERLY', $date = null ) {
		$date_start      = null;
		$date_end        = null;
		$start_indicator = is_null( $date ) ? new \WC_DateTime() : $date;

		if ( ! is_a( $start_indicator, 'WC_DateTime' ) && is_numeric( $start_indicator ) ) {
			$start_indicator = new \WC_DateTime( "@" . $start_indicator );
		}

		if ( 'QUARTERLY' === $type ) {
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
		} elseif ( 'MONTHLY' ) {
			$month = $start_indicator->date_i18n( 'M' );

			$date_start = new \WC_DateTime( "first day of " . $month . " " . $start_indicator->date_i18n( 'Y' ) . " midnight" );
			$date_end   = new \WC_DateTime( "last day of " . $month . " " . $start_indicator->date_i18n( 'Y' ) . " midnight" );
		} else {
			$date_end   = clone $start_indicator;
			$date_start = clone $date_end;

			$date_start->modify( '-1 year' );
		}

		var_dump($date_start);
		var_dump($date_end);
		exit();
	}
}