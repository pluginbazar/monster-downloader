<?php
/**
 * Plugin Name: WP Downloader Plus
 * Plugin URI: http://pluginbazar.com/
 * Description: This plugin for download WordPress plugin and theme.
 * Version: 1.0.0
 * Author: Pluginbazar
 * Author URI: http://pluginbazar.com/
 *
 */

defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WPDP_Main' ) ) {
	/**
	 * Class WPDP_Main
	 */
	class WPDP_Main {
		protected static $_instance = null;

		function __construct() {
			add_action( 'plugins_loaded', array( $this, 'wpdb_loaded_hooks' ), 10, 3 );
		}

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * @return void
		 */
		function wpdb_loaded_hooks() {
			add_filter( 'plugin_action_links', array( $this, 'wpdp_plugin_action' ) );
		}

	
	}
}