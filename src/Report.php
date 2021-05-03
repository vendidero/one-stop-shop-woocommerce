<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

class Report {

	private $id;

	private $args = array();

	private $type = 'yearly';

	private $date_start = null;

	private $date_end = null;

	public function __construct( $id, $args = array() ) {
		$this->id         = $id;
		$data             = Package::get_report_data( $this->id );
		$this->type       = $data['type'];
		$this->date_start = $data['date_start'];
		$this->date_end   = $data['date_end'];

		if ( empty( $args ) ) {
			$args = (array) get_option( $this->id . '_result', array() );
		}

		$args = wp_parse_args( $args, array(
			'countries' => array(),
			'totals'    => array(),
			'meta'      => array(),
		) );

		$args['totals'] = wp_parse_args( $args['totals'], array(
			'net_total' => 0,
			'tax_total' => 0
		) );

		$args['meta'] = wp_parse_args( $args['meta'], array(
			'date_requested' => null,
			'status'         => 'pending'
		) );

		$this->set_date_requested( $args['meta']['date_requested'] );
		$this->set_status( $args['meta']['status'] );

		$this->args = $args;
	}

	public function exists() {
		return get_option( $this->id . '_result', false );
	}

	public function get_title() {
		$title = Package::get_report_title( $this->get_id() );

		if ( $this->get_date_requested() ) {
			$title = $title . ' @ ' . $this->get_date_requested()->date_i18n();
		}

		return $title;
	}

	public function get_url() {
		return admin_url( 'admin.php?page=oss-reports&report=' . $this->get_id() );
	}

	public function get_type() {
		return $this->type;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_date_start() {
		return $this->date_start;
	}

	public function get_date_end() {
		return $this->date_end;
	}

	public function get_status() {
		return $this->args['meta']['status'];
	}

	public function get_date_requested() {
		return is_null( $this->args['meta']['date_requested'] ) ? null : wc_string_to_datetime( $this->args['meta']['date_requested'] );
	}

	public function set_date_requested( $date ) {
		if ( is_numeric( $date ) ) {
			$date = new \WC_DateTime( "@" . $date );
		} elseif ( ! empty( $date ) ) {
			$date = wc_string_to_datetime( $date );
		}

		$this->args['meta']['date_requested'] = is_a( $date, 'WC_DateTime' ) ? $date->date_i18n( 'Y-m-d' ) : null;
	}

	public function set_status( $status ) {
		$this->args['meta']['status'] = $status;
	}

	public function get_tax_total() {
		return wc_format_decimal( $this->args['totals']['tax_total'], '' );
	}

	public function get_net_total() {
		return wc_format_decimal( $this->args['totals']['net_total'], '' );
	}

	public function set_tax_total( $total ) {
		$this->args['totals']['tax_total'] = wc_format_decimal( wc_round_tax_total( floatval( $total ) ), '' );
	}

	public function set_net_total( $total ) {
		$this->args['totals']['net_total'] = wc_format_decimal( floatval( $total ), '' );
	}

	public function get_countries() {
		return array_keys( $this->args['countries'] );
	}

	public function reset() {
		$this->args['countries'] = array();

		$this->set_net_total( 0 );
		$this->set_tax_total( 0 );
		$this->set_date_requested( new \WC_DateTime() );
		$this->set_status( 'pending' );
	}

	public function get_tax_rates_by_country( $country ) {
		$tax_rates = array();

		if ( array_key_exists( $country, $this->args['countries'] ) ) {
			$tax_rates = array_keys( $this->args['countries'][ $country ] );
		}

		return $tax_rates;
	}

	public function get_country_tax_total( $country, $tax_rate, $round = true ) {
		$tax_total = 0;

		if ( isset( $this->args['countries'][ $country ], $this->args['countries'][ $country ][ $tax_rate ] ) ) {
			$tax_total = $this->args['countries'][ $country ][ $tax_rate ]['tax_total'];
		}

		if ( $round ) {
			$tax_total = wc_format_decimal( $tax_total, '' );
		}

		return (float) $tax_total;
	}

	public function get_country_net_total( $country, $tax_rate, $round = true ) {
		$net_total = 0;

		if ( isset( $this->args['countries'][ $country ], $this->args['countries'][ $country ][ $tax_rate ] ) ) {
			$net_total = $this->args['countries'][ $country ][ $tax_rate ]['net_total'];
		}

		if ( $round ) {
			$net_total = wc_format_decimal( $net_total, '' );
		}

		return (float) $net_total;
	}

	public function set_country_tax_total( $country, $tax_rate, $tax_total = 0 ) {
		if ( ! isset( $this->args['countries'][ $country ] ) ) {
			$this->args['countries'][ $country ] = array();
		}

		if ( ! isset( $this->args['countries'][ $country ][ $tax_rate ] ) ) {
			$this->args['countries'][ $country ][ $tax_rate ] = array(
				'net_total' => 0,
				'tax_total' => 0,
			);
		}

		$this->args['countries'][ $country ][ $tax_rate ]['tax_total'] = $tax_total;
	}

	public function set_country_net_total( $country, $tax_rate, $net_total = 0 ) {
		if ( ! isset( $this->args['countries'][ $country ] ) ) {
			$this->args['countries'][ $country ] = array();
		}

		if ( ! isset( $this->args['countries'][ $country ][ $tax_rate ] ) ) {
			$this->args['countries'][ $country ][ $tax_rate ] = array(
				'net_total' => 0,
				'tax_total' => 0,
			);
		}

		$this->args['countries'][ $country ][ $tax_rate ]['net_total'] = $net_total;
	}

	public function save() {
		update_option( $this->id . '_result', $this->args );
		Package::clear_caches();

		return $this->id;
	}

	public function delete() {
		delete_option( $this->id . '_result' );
		Package::clear_caches();

		return true;
	}
}