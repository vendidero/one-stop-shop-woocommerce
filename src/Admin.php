<?php

namespace Vendidero\OneStopShop;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Admin class.
 */
class Admin {

	/**
	 * Constructor.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_styles' ), 15 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ), 15 );

		add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'register_settings' ) );

		add_action( 'load-woocommerce_page_oss-reports', array( __CLASS__, 'setup_table' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 15 );

		add_action( 'admin_post_create_oss_report', array( __CLASS__, 'create_report' ) );
	}

	public static function create_report() {
	    if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : '', 'create_oss_report' ) ) {
	        wp_die();
        }

	    $report_type = ! empty( $_POST['report_type'] ) ? wc_clean( $_POST['report_type'] ) : 'yearly';
	    $report_type = array_key_exists( $report_type, Package::get_available_types() ) ? $report_type : 'yearly';
	    $start_date  = null;
		$end_date    = null;

	    if ( 'quarterly' === $report_type ) {
	        $start_date = ! empty( $_POST['report_quarter'] ) ? wc_clean( $_POST['report_quarter'] ) : null;
        } elseif ( 'yearly' === $report_type ) {
			$start_date = ! empty( $_POST['report_year'] ) ? wc_clean( $_POST['report_year'] ) : null;
		} elseif ( 'monthly' === $report_type ) {
		    $start_date = ! empty( $_POST['report_month'] ) ? wc_clean( $_POST['report_month'] ) : null;
	    } elseif ( 'custom' === $report_type ) {
		    $start_date = ! empty( $_POST['date_start'] ) ? wc_clean( $_POST['date_start'] ) : null;
		    $end_date   = ! empty( $_POST['date_end'] ) ? wc_clean( $_POST['date_end'] ) : null;
	    }

	    if ( ! is_null( $start_date ) ) {
	        $start_date = wc_string_to_datetime( $start_date );
        }

		if ( ! is_null( $end_date ) ) {
			$end_date = wc_string_to_datetime( $end_date );
		}

	    $generator_id = Queue::start( $report_type, $start_date, $end_date );

		wp_safe_redirect( admin_url( 'admin.php?page=oss-reports&created=' . $generator_id ) );
		exit();
	}

	public static function add_menu() {
		add_submenu_page( 'woocommerce', _x( 'OSS', 'oss', 'oss-woocommerce' ), _x( 'One Stop Shop', 'oss', 'oss-woocommerce' ), 'manage_woocommerce', 'oss-reports', array( __CLASS__, 'render_report_page' ) );
	}

	public static function get_reports_notices() {
	    foreach( Queue::get_reports_running() as $type => $reports ) {

        }
    }

	protected static function render_create_report() {
	    $years   = array();
		$years[] = date( 'Y' );
		$years[] = date( 'Y', strtotime("-1 year" ) );

		$quarters_selectable = array();
		$years_selectable    = array();
		$months_selectable   = array();

		foreach( $years as $year ) {
			$start_day                      = date( 'Y-m-d', strtotime( $year . '-01-01' ) );
		    $years_selectable[ $start_day ] = $year;

		    for ( $i = 4; $i>=1; $i-- ) {
		        $start_month = ( $i - 1 ) * 3 + 1;
		        $start_day   = date( 'Y-m-d', strtotime( $year . '-' . $start_month . '-01' ) );

		        if ( date( 'Y-m-d' ) >= $start_day ) {
		            $quarters_selectable[ $start_day ] = sprintf( _x( 'Q%1$s/%2$s', 'oss', 'oss-woocommerce' ), $i, $year );
                }
            }

			for ( $i = 12; $i>=1; $i-- ) {
				$start_day = date( 'Y-m-d', strtotime( $year . '-' . $i . '-01' ) );
				$month     = date( 'm', strtotime( $year . '-' . $i . '-01' ) );

				if ( date( 'Y-m-d' ) >= $start_day ) {
					$months_selectable[ $start_day ] = sprintf( _x( '%1$s/%2$s', 'oss', 'oss-woocommerce' ), $month, $year );
				}
			}
        }
        ?>
        <div class="wrap oss-reports create-oss-reports">
            <form class="create-oss-report" method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                <header>
                    <h2><?php _ex( 'New Report', 'oss', 'oss-woocommerce' ); ?></h2>
                </header>
                <section>
                    <table class="form-table oss-report-options">
                        <tbody>
                        <tr id="oss-report-type-wrapper">
                            <th scope="row">
                                <label for="oss-report-type"><?php echo esc_html_x( 'Type', 'oss', 'oss-woocommerce' ); ?></label>
                            </th>
                            <td id="oss-report-type-data">
                                <select name="report_type" id="oss-report-type" class="wc-enhanced-select">
                                    <?php foreach( Package::get_available_types() as $type => $title ) : ?>
                                        <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $title ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr id="oss-report-year-wrapper" class="oss-report-hidden oss-report-yearly">
                            <th scope="row">
                                <label for="oss-report-year"><?php echo esc_html_x( 'Year', 'storeabill-core', 'storeabill' ); ?></label>
                            </th>
                            <td id="oss-report-year-data">
                                <select name="report_year" id="oss-report-year" class="wc-enhanced-select">
			                        <?php foreach( $years_selectable as $value => $title ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $title ); ?></option>
			                        <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr id="oss-report-quarter-wrapper" class="oss-report-hidden oss-report-quarterly">
                            <th scope="row">
                                <label for="oss-report-quarter"><?php echo esc_html_x( 'Quarter', 'storeabill-core', 'storeabill' ); ?></label>
                            </th>
                            <td id="oss-report-quarter-data">
                                <select name="report_quarter" id="oss-report-quarter" class="wc-enhanced-select">
	                                <?php foreach( $quarters_selectable as $value => $title ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $title ); ?></option>
	                                <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr id="oss-report-month-wrapper" class="oss-report-hidden oss-report-monthly">
                            <th scope="row">
                                <label for="oss-report-month"><?php echo esc_html_x( 'Month', 'storeabill-core', 'storeabill' ); ?></label>
                            </th>
                            <td id="oss-report-month-data">
                                <select name="report_month" id="oss-report-month" class="wc-enhanced-select">
			                        <?php foreach( $months_selectable as $value => $title ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $title ); ?></option>
			                        <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr id="oss-report-timeframe-wrapper" class="oss-report-hidden oss-report-custom">
                            <th scope="row">
                                <label for="sab-exporter-start-date"><?php echo esc_html_x( 'Date range', 'storeabill-core', 'storeabill' ); ?></label>
                            </th>
                            <td id="sab-exporter-date-range">
                                <input type="text" size="11" placeholder="yyyy-mm-dd" value="" name="date_start" class="range_datepicker from" autocomplete="off" /><?php //@codingStandardsIgnoreLine ?>
                                <span>&ndash;</span>
                                <input type="text" size="11" placeholder="yyyy-mm-dd" value="" name="date_end" class="range_datepicker to" autocomplete="off" /><?php //@codingStandardsIgnoreLine ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </section>
                <div class="oss-actions">
                    <button type="submit" class="oss-new-report-button button button-primary" value="<?php echo esc_attr_x( 'Start report', 'oss', 'oss-woocommerce' ); ?>"><?php echo esc_attr_x( 'Start report', 'oss', 'oss-woocommerce' ); ?></button>
                </div>
                <?php wp_nonce_field( 'create_oss_report' ); ?>
                <input type="hidden" name="action" value="create_oss_report" />
            </form>
        </div>
        <?php
    }

	protected static function render_reports() {
		global $wp_list_table;
		?>
        <div class="wrap oss-reports">
            <h1 class="wp-heading-inline"><?php echo _x( 'One Stop Shop', 'oss', 'oss-woocommerce' ); ?></h1>
            <a href="<?php echo add_query_arg( array( 'new' => 'yes' ), admin_url( 'admin.php?page=oss-reports' ) ); ?>" class="page-title-action"><?php _ex( 'New report', 'oss', 'oss-woocommerce' ); ?></a>

            <hr class="wp-header-end" />

			<?php
			$wp_list_table->output_notices();
			$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'updated', 'changed', 'deleted', 'trashed', 'untrashed' ), $_SERVER['REQUEST_URI'] );
			?>

			<?php $wp_list_table->views(); ?>

            <form id="posts-filter" method="get">
                <input type="hidden" name="page" value="oss-reports" />

				<?php $wp_list_table->display(); ?>
            </form>

            <div id="ajax-response"></div>
            <br class="clear" />
        </div>
		<?php
	}

	public static function render_report_details() {
	    $report_id = wc_clean( $_GET['report'] );

	    if ( ! $report = Package::get_report( $report_id ) ) {
	        return;
	    }

		$columns = array(
			'country'   => _x( 'Country', 'oss', 'oss-woocommerce' ),
			'tax_rate'  => _x( 'Tax Rate', 'oss', 'oss-woocommerce' ),
			'net_total' => _x( 'Net Total', 'oss', 'oss-woocommerce' ),
			'tax_total' => _x( 'Tax Total', 'oss', 'oss-woocommerce' ),
		);

	    ?>
        <div class="wrap oss-reports oss-report-<?php echo esc_attr( $report->get_id() ); ?>">
            <h1 class="wp-heading-inline"><?php echo $report->get_title(); ?></h1>

            <hr class="wp-header-end" />

            <table class="oss-report-details widefat" cellspacing="0">
                <thead>
                <tr>
			        <?php foreach ( $columns as $key => $column ) : ?>
				       <th class="oss-report-table-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $column ); ?></th>
			        <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
		        <?php
		        foreach ( $report->get_countries() as $country ) :
		            foreach( $report->get_tax_rates_by_country( $country ) as $tax_rate ) :
			        ?>
                    <tr>
                        <td class="oss-report-table-country"><?php echo esc_html( $country ); ?></td>
                        <td class="oss-report-table-tax_rate"><?php echo esc_html( sprintf( _x( '%1$s %%', 'oss', 'oss-woocommerce' ), $tax_rate ) ); ?></td>
                        <td class="oss-report-table-net_total"><?php echo wc_price( $report->get_country_net_total( $country, $tax_rate ) ); ?></td>
                        <td class="oss-report-table-tax_total"><?php echo wc_price( $report->get_country_tax_total( $country, $tax_rate ) ); ?></td>
                    </tr>
		            <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
	}

	public static function render_report_page() {
		global $wp_list_table;

		if ( current_user_can( 'manage_woocommerce' ) ) {
			if ( isset( $_GET['new'] ) ) {
				self::render_create_report();
			} elseif ( isset( $_GET['report'] ) ) {
				self::render_report_details();
			} else {
				self::render_reports();
			}
		}
	}

	public static function setup_table() {
        global $wp_list_table;

        $wp_list_table = new ReportTable();
        $doaction      = $wp_list_table->current_action();

        if ( $doaction ) {
            /**
             * This nonce is dynamically constructed by WP_List_Table and uses
             * the normalized plural argument.
             */
            check_admin_referer( 'bulk-' . sanitize_key( _x( 'Reports', 'oss', 'oss-woocommerce' ) ) );

            $pagenum       = $wp_list_table->get_pagenum();
            $parent_file   = $wp_list_table->get_main_page();
            $sendback      = remove_query_arg( array( 'deleted', 'ids', 'changed', 'bulk_action' ), wp_get_referer() );

            if ( ! $sendback ) {
                $sendback = admin_url( $parent_file );
            }

            $sendback   = add_query_arg( 'paged', $pagenum, $sendback );
            $report_ids = array();

            if ( isset( $_REQUEST['ids'] ) ) {
                $report_ids = explode( ',', $_REQUEST['ids'] );
            } elseif ( ! empty( $_REQUEST['document'] ) ) {
                $report_ids = array_map( 'intval', $_REQUEST['reports'] );
            }

            if ( ! empty( $report_ids ) ) {
                $sendback = $wp_list_table->handle_bulk_actions( $doaction, $report_ids, $sendback );
            }

            $sendback = remove_query_arg( array( 'action', 'action2', '_status', 'bulk_edit', 'report' ), $sendback );

            wp_redirect( $sendback );
            exit();
        } elseif ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
            wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
            exit;
        }

        $wp_list_table->set_bulk_notice();
        $wp_list_table->prepare_items();

        add_screen_option( 'per_page' );
	}

	public static function register_settings( $settings ) {
		$settings[] = new Settings();
	}

	public static function get_screen_ids() {
		$screen_ids = array();

		foreach ( wc_get_order_types() as $type ) {
			$screen_ids[] = $type;
			$screen_ids[] = 'edit-' . $type;
		}

		return $screen_ids;
	}

	public static function admin_styles() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		wp_register_style( 'oss_woo', Package::get_url() . '/assets/styles/admin.css', array(), Package::get_version() );

		// Admin styles for WC pages only.
		if ( in_array( $screen_id, self::get_screen_ids() ) ) {
			wp_enqueue_style( 'oss_woo' );
		}
	}

	public static function admin_scripts() {
		global $post;

		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		/*

		wp_register_script( 'storeabill_woo_admin_edit_order', Package::get_build_url() . '/admin/edit-order.js', array( 'woocommerce_admin', 'storeabill_admin_global', 'wc-admin-order-meta-boxes' ), Package::get_version() );
		wp_register_script( 'storeabill_woo_admin_bulk_actions', Package::get_build_url() . '/admin/bulk-actions.js', array( 'woocommerce_admin', 'storeabill_admin_global' ), Package::get_version() );

		if ( 'edit-shop_order' === $screen_id ) {
			wp_enqueue_script( 'storeabill_woo_admin_bulk_actions' );

			$bulk_actions = array();

			foreach( \Vendidero\StoreaBill\Admin\Admin::get_bulk_actions_handlers( 'shop_order' ) as $handler ) {
				$bulk_actions[ sanitize_key( $handler->get_action() ) ] = array(
					'title' => $handler->get_title(),
					'nonce' => wp_create_nonce( $handler->get_nonce_action() ),
				);
			}

			wp_localize_script(
				'storeabill_woo_admin_bulk_actions',
				'storeabill_admin_bulk_actions_params',
				array(
					'ajax_url'               => admin_url( 'admin-ajax.php' ),
					'bulk_actions'           => $bulk_actions,
					'table_type'             => 'post',
					'object_input_type_name' => 'post_type',
				)
			);
		}
		*/
	}
}