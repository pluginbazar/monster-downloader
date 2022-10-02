<?php

use Pluginbazar\Utils;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPDP_Reports_table extends WP_List_Table {

	/**
	 * @return array[]|void
	 */
	public function table_data() {
		global $wpdb;

		return $wpdb->get_results( "SELECT * FROM " . WPDB_TABLE_REPORTS, ARRAY_A );
	}

	private $pagination;

	/**
	 * @return array
	 */
	function get_columns() {
		return apply_filters( 'WPDP/Filters/get_report_columns',
			array(
				'id'            => esc_html__( 'ID', 'wp-downloader-plus' ),
				'object_name'   => esc_html__( 'Name', 'wp-downloader-plus' ),
				'object_type'   => esc_html__( 'Type', 'wp-downloader-plus' ),
				'downloaded_by' => esc_html__( 'Downloaded By', 'wp-downloader-plus' ),
				'datetime'      => esc_html__( 'Downloaded At', 'wp-downloader-plus' ),
			)
		);
	}


	/**
	 * @return void
	 */
	function prepare_items() {

		$columns = $this->get_columns();

		$per_page         = 20;
		$current_page     = $this->get_pagenum();
		$this->pagination = $this->table_data();
		$count            = count( $this->pagination );
		$this->pagination = array_slice( $this->pagination, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->set_pagination_args( array(
			'total_items' => $count,
			'per_page'    => $per_page,
		) );
		$this->_column_headers = array( $columns );
		$this->items           = $this->table_data();
		$this->items           = $this->pagination;
	}

	/**
	 * @param $item
	 * @param $column_name
	 *
	 * @return bool|mixed|string|void
	 */
	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
			case 'object_name':
			case 'object_type':
			case 'downloaded_by':
			case 'datetime':
			default:
				return $item[ $column_name ];
		}
	}

	/**
	 * Column object_name
	 *
	 * @param $item
	 *
	 * @return void
	 */
	function column_object_name( $item ) {

		$object_name   = Utils::get_args_option( 'object_name', $item );
		$object_name   = ucwords( str_replace( array( '-', '_' ), ' ', $object_name ) );
		$row_actions[] = sprintf( '<span class="wpdp-download"><a href="%s">%s</a></span>', '', esc_html__( 'Download' ) );

		printf( '<a href="#"><strong>%s</strong></a>', $object_name );
		printf( '<div class="row-actions visible">%s</div>', implode( ' | ', $row_actions ) );
	}

	function column_object_type( $item ) {
		$object      = isset( $item['object_type'] ) ? $item['object_type'] : '';
		$object_type = ucfirst( $object );
		printf( '<div><strong>%s</strong></div>', $object_type );
	}

	/**
	 * @param $item
	 *
	 * @return void
	 */
	function column_downloaded_by( $item ) {
		$user_id       = isset( $item['downloaded_by'] ) ? $item['downloaded_by'] : '';
		$downloaded_by = get_user_by( 'id', $user_id );

		printf( '<div><a href="#"><strong>%s</strong></a></div>', $downloaded_by->display_name );
		printf( '<div><a href="#"><strong>%s</strong></a></div>', $downloaded_by->user_email );
	}

	/**
	 * @param $time
	 *
	 * @return void
	 */
	function column_datetime( $time ) {
		$time     = isset( $time['datetime'] ) ? $time['datetime'] : '';
		$datetime = strtotime( $time );
		$datetime = esc_html( human_time_diff( $datetime, current_time( 'U' ) ) ) . ' ago';
		printf( '<div class="wpdp_time_diff">%s</div>', $datetime );
		printf( '<div class="wpdp_download_time">%s</div>', $time );
	}

}

