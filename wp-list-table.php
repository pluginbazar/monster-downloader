<?php

use Pluginbazar\Utils;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPDP_Reports_table extends WP_List_Table {

	private $users_data;

	private function get_users_data( $search = "" ) {
		global $wpdb;
		$filter = isset( $_POST['filter-object-type'] ) ? $_POST['filter-object-type'] : '';
		if ( ! empty( $search ) ) {
			return $wpdb->get_results(
				"SELECT id,object_name,object_type,downloaded_by,datetime FROM " . WPDB_TABLE_REPORTS . " WHERE id Like '%{$search}%' OR object_name Like '%{$search}%' OR object_type Like '%{$search}%' OR datetime Like '%{$search}%'",
				ARRAY_A
			);
		} elseif ( $filter !== 'all' ) {
			return $wpdb->get_results(
				"SELECT id,object_name,object_type,downloaded_by,datetime FROM " . WPDB_TABLE_REPORTS . " WHERE  object_type Like '%{$filter}%' ",
				ARRAY_A
			);
		} else {
			return $wpdb->get_results(
				"SELECT id,object_name,object_type,downloaded_by,datetime FROM " . WPDB_TABLE_REPORTS,
				ARRAY_A
			);
		}
	}


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

		if ( isset( $_POST['page'] ) && isset( $_POST['s'] ) ) {
			$this->users_data = $this->get_users_data( $_POST['s'] );
		} else {
			$this->users_data = $this->get_users_data();
		}

		$columns = $this->get_columns();

		$per_page         = 20;
		$current_page     = $this->get_pagenum();
		$this->users_data = $this->users_data;
		$count            = count( $this->users_data );
		$this->pagination = array_slice( $this->users_data, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->set_pagination_args( array(
			'total_items' => $count,
			'per_page'    => $per_page,
		) );
		$this->_column_headers = array( $columns );
		$this->items           = $this->users_data;
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
		$row_actions[] = sprintf( '<span class="wpdp - download"><a href=" % s">%s</a></span>', '', esc_html__( 'Download' ) );

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

	function extra_tablenav( $which ) {
		if ( $which == "top" ) {
			?>
            <div class="alignleft actions bulkactions">
                <form action="" method="post">
                    <select name="filter-object-type">
                        <option value="all">All</option>
                        <option value="plugin">Plugin</option>
                        <option value="theme">Theme</option>
                    </select>
                    <button class="button" type="submit">Filter</button>
                </form>
            </div>
			<?php
		}
	}
}

