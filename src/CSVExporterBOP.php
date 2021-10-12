<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

class CSVExporterBOP extends CSVExporter {

	/**
	 * Return an array of columns to export.
	 *
	 * @since 3.1.0
	 * @return array
	 */
	public function get_default_column_names() {
		return apply_filters( "one_stop_shop_woocommerce_bop_export_default_columns", array(
			'country'      => 'Land des Verbrauchs',
			'tax_type'     => 'Umsatzsteuertyp',
			'tax_rate'     => 'Umsatzsteuersatz',
			'taxable_base' => 'Steuerbemessungsgrundlage, Nettobetrag',
			'amount'       => 'Umsatzsteuerbetrag',
		) );
	}

	protected function get_column_value_tax_type( $country, $tax_rate ) {
		return 'STANDARD';
	}

	protected function export_column_headers() {
		$buffer = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		ob_start();
		fwrite( $buffer, "#v1.0\r\n" );
		fwrite( $buffer, "#ve1.1.0\r\n" );
		$content = ob_get_clean();

		return $content . parent::export_column_headers();
	}
}