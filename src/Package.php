<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

/**
 * Main package class.
 */
class Package {

	/**
	 * Version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Init the package
	 */
	public static function init() {
		if ( ! self::has_dependencies() ) {
			return;
		}

		self::includes();
		self::init_hooks();

		if ( is_admin() ) {
			Admin::init();
		}

		//add_action( 'admin_init', array( __CLASS__, 'test' ) );
	}

	public static function test() {
		// Queue::start( 'quarterly' );

		/*
		$generator = new AsyncReportGenerator();
		$generator->next();
		$generator->complete();
		*/

		// $csv = new CSVExporter( 'oss_quarterly_report_2021-04-01_2021-06-30' );
		// $csv->export();

		// $report = new Report( 'oss_quarterly_report_2021-04-01_2021-06-30' );

		$date_start = new \WC_DateTime( "now" );
		$date_start->modify( '-1 day' );

		Queue::start( 'observer', $date_start );

		// Refund test
		// $order = wc_get_order( 100 );
		exit();
	}

	public static function string_to_datetime( $time_string ) {
		if ( ! is_numeric( $time_string ) ) {
			$time_string = strtotime( $time_string );
		}

		$date_time = $time_string;

		if ( ! is_a( $time_string, 'WC_DateTime' ) ) {
			$date_time = new \WC_DateTime( "@{$time_string}", new \DateTimeZone( 'UTC' ) );
		}

		return $date_time;
	}

	/**
	 * @param $rate_id
	 * @param \WC_Order $order
	 */
	public static function get_tax_rate_percent( $rate_id, $order ) {
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

	/**
	 * @param $id
	 *
	 * @return false|Report
	 */
	public static function get_report( $id ) {
		$report = new Report( $id );

		if ( $report->exists() ) {
			return $report;
		}

		return false;
	}

	public static function get_report_data( $id ) {
		$id_parts = explode( '_', $id );
		$data     = array(
			'id'         => $id,
			'type'       => $id_parts[1],
			'date_start' => self::string_to_datetime( $id_parts[3] ),
			'date_end'   => self::string_to_datetime( $id_parts[4] ),
		);

		return $data;
	}

	public static function get_report_title( $id ) {
		$args  = self::get_report_data( $id );
		$title = _x( 'Report', 'oss', 'oss-woocommerce' );

		if ( 'quarterly' === $args['type'] ) {
			$date_start = $args['date_start'];
			$quarter    = 1;
			$month_num  = $date_start->date_i18n( 'n' );

			if ( 4 == $month_num ) {
				$quarter = 2;
			} elseif ( 7 == $month_num ) {
				$quarter = 3;
			} elseif ( 10 == $month_num ) {
				$quarter = 4;
			}

			$title = sprintf( _x( 'Q%1$s/%2$s', 'oss', 'oss-woocommerce' ), $quarter, $date_start->date_i18n( 'Y' ) );
		} elseif( 'monthly' === $args['type'] ) {
			$date_start = $args['date_start'];
			$month_num  = $date_start->date_i18n( 'm' );

			$title = sprintf( _x( '%1$s/%2$s', 'oss', 'oss-woocommerce' ), $month_num, $date_start->date_i18n( 'Y' ) );
		} elseif( 'yearly' === $args['type'] ) {
			$date_start = $args['date_start'];

			$title = sprintf( _x( '%1$s', 'oss', 'oss-woocommerce' ), $date_start->date_i18n( 'Y' ) );
		} elseif( 'custom' === $args['type'] ) {
			$date_start = $args['date_start'];
			$date_end   = $args['date_end'];

			$title = sprintf( _x( '%1$s - %2$s', 'oss', 'oss-woocommerce' ), $date_start->date_i18n( 'Y-m-d' ), $date_end->date_i18n( 'Y-m-d' ) );
		}

		return $title;
	}

	public static function get_reports( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'type'    => '',
			'limit'   => -1,
			'offset'  => 0,
			'orderby' => 'date_start'
		) );

		$ids = Queue::get_report_ids();

		if ( ! empty( $args['type'] ) ) {
			$report_ids = array_key_exists( $args['type'], $ids ) ? $ids[ $args['type'] ] : array();
		} else {
			$report_ids = array_merge( ...array_values( $ids ) );
		}

		$reports_sorted = array();

		foreach( $report_ids as $id ) {
			$reports_sorted[] = self::get_report_data( $id );
		}

		if ( array_key_exists( $args['orderby'], array( 'date_start', 'date_end' ) ) ) {
			usort($reports_sorted, function( $a, $b ) use ( $args ) {
				if ( $a[ $args['orderby'] ] == $b[ $args['orderby'] ] ) {
					return 0;
				}

				return $a[ $args['orderby'] ] < $b[ $args['orderby'] ] ? -1 : 1;
			} );
		}

		if ( -1 !== $args['limit'] ) {
			$reports_sorted = array_slice( $reports_sorted, $args['offset'], $args['limit'] );
		}

		$reports = array();

		foreach( $reports_sorted as $data ) {
			if ( $report = Package::get_report( $data['id'] ) ) {
				$reports[] = $report;
			}
		}

		return $reports;
 	}

 	public static function clear_caches() {
		delete_transient( 'oss_reports_counts' );
    }

 	public static function get_report_counts() {
	    $types     = array_keys( Package::get_available_types() );
	    $cache_key = 'oss_reports_counts';
	    $counts    = get_transient( $cache_key );

	    if ( false === $counts ) {
		    $counts = array();

		    foreach( $types as $type ) {
			    $counts[ $type ] = 0;
		    }

		    foreach( self::get_reports() as $report ) {
		    	if ( ! array_key_exists( $report->get_type(), $counts ) ) {
		    		continue;
			    }

			    $counts[ $report->get_type() ] += 1;
		    }

		    set_transient( $cache_key, $counts );
	    }

	    return (array) $counts;
    }

	protected static function init_hooks() {
		/**
		 * Support a taxable country field within Woo order queries
		 */
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( __CLASS__, 'query_taxable_country' ), 10, 2 );

		/**
		 * Listen to action scheduler hooks for report generation
		 */
		foreach( Queue::get_reports_running() as $id => $status ) {
			if ( 'pending' === $status ) {
				$data = Package::get_report_data( $id );
				$type = $data['type'];

				add_action( 'oss_woocommerce_' . $id, function( $args ) use ( $type ) {
					Queue::next( $type, $args );
				}, 10, 1 );
			}
		}

		add_action( 'init', array( __CLASS__, 'setup_recurring_observer' ), 10 );
		add_action( 'oss_woocommerce_daily_observer', array( __CLASS__, 'update_observer_report' ), 10 );
	}

	public static function update_observer_report() {
		$date_start = new \WC_DateTime();
		$date_start->modify( '-1 day' );

		Queue::start( 'observer', $date_start );
	}

	public static function setup_recurring_observer() {
		if ( $queue = Queue::get_queue() ) {
			// Schedule once per day at 3:00
			if ( null === $queue->get_next( 'oss_woocommerce_daily_observer', array(), 'oss_woocommerce' ) ) {
				$timestamp = strtotime('tomorrow midnight' );
				$date      = new \WC_DateTime();

				$date->setTimestamp( $timestamp );
				$date->modify( '+3 hours' );

				$queue->cancel_all( 'oss_woocommerce_daily_observer', array(), 'oss_woocommerce' );
				$queue->schedule_recurring( $date->getTimestamp(), DAY_IN_SECONDS, 'oss_woocommerce_daily_observer', array(), 'oss_woocommerce' );
			}
		}
	}

	public static function get_available_types() {
		return array(
			'quarterly' => _x( 'Quarterly', 'oss', 'oss-woocommerce' ),
			'yearly'    => _x( 'Yearly', 'oss', 'oss-woocommerce' ),
			'monthly'   => _x( 'Monthly', 'oss', 'oss-woocommerce' ),
			'custom'    => _x( 'Custom', 'oss', 'oss-woocommerce' ),
			'observer'  => '',
		);
	}

	public static function get_type_title( $type ) {
		$types = Package::get_available_types();

		return array_key_exists( $type, $types ) ? $types[ $type ] : '';
	}

	public static function get_statuses() {
		return array(
			'pending'   => _x( 'Pending', 'oss', 'oss-woocommerce' ),
			'completed' => _x( 'Completed', 'oss', 'oss-woocommerce' ),
			'failed'    => _x( 'Failed', 'oss', 'oss-woocommerce' )
		);
	}

	public static function get_status_title( $status ) {
		$statuses = Package::get_statuses();

		return array_key_exists( $status, $statuses ) ? $statuses[ $status ] : '';
	}

	public static function query_taxable_country( $query, $query_vars ) {
		if ( ! empty( $query_vars['taxable_country'] ) ) {
			$taxable_country = is_array( $query_vars['taxable_country'] ) ? $query_vars['taxable_country'] : array( $query_vars['taxable_country'] );
			$taxable_country = wc_clean( $taxable_country );

			$query['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'relation' => 'AND',
					array(
						'key'     => '_shipping_country',
						'compare' => 'NOT_EXISTS',
					),
					array(
						'key'     => '_billing_country',
						'value'   => $taxable_country,
						'compare' => 'IN',
					),
				),
				array(
					'key'     => '_shipping_country',
					'value'   => $taxable_country,
					'compare' => 'IN',
				),
			);
		}

		return $query;
	}

	public static function has_dependencies() {
		return ( class_exists( 'WooCommerce' ) );
	}

	private static function includes() {
		// include_once self::get_path() . '/includes/wc-gzd-dhl-core-functions.php';
	}

	/**
	 * Returns a list of EU countries except base country.
	 *
	 * @return string[]
	 */
	public static function get_non_base_eu_countries() {
		$countries    = WC()->countries->get_european_union_countries( 'eu_vat' );
		$base_country = wc_get_base_location()['country'];
		$countries    = array_diff( $countries, array( $base_country ) );

		return $countries;
	}

	public static function install() {
		self::init();
		Install::install();
	}

	public static function install_integration() {
		self::install();
	}

	public static function is_integration() {
		return class_exists( 'WooCommerce_Germanized' ) ? true : false;
	}

	/**
	 * Return the version of the package.
	 *
	 * @return string
	 */
	public static function get_version() {
		return self::VERSION;
	}

	/**
	 * Return the path to the package.
	 *
	 * @return string
	 */
	public static function get_path() {
		return dirname( __DIR__ );
	}

	/**
	 * Return the path to the package.
	 *
	 * @return string
	 */
	public static function get_url() {
		return plugins_url( '', __DIR__ );
	}

	public static function get_assets_url() {
		return self::get_url() . '/assets';
	}

	private static function define_constant( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	public static function log( $message, $type = 'info' ) {
		$logger = wc_get_logger();

		if ( ! $logger || ! apply_filters( 'one_stop_shop_woocommerce_enable_logging', true ) ) {
			return;
		}

		if ( ! is_callable( array( $logger, $type ) ) ) {
			$type = 'info';
		}

		$logger->{$type}( $message, array( 'source' => 'one-stop-shop-woocommerce' ) );
	}

	public static function extended_log( $message, $type = 'info' ) {
		if ( apply_filters( 'one_stop_shop_woocommerce_enable_extended_logging', true ) ) {
			self::log( $message, $type );
		}
	}
}