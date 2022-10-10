<?php
/*
	Plugin Name: WP Downloader Plus
	Plugin URI: https://pluginbazar.com/
	Description: This plugin for download WordPress plugin and theme.
	Version: 1.0.0
	Author: Pluginbazar
	Text Domain: wp-downloader-plus
	Author URI: https://pluginbazar.com/
	License: GPLv2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

global $wpdb;

defined( 'ABSPATH' ) || exit;
defined( 'WPDB_PLUGIN_URL' ) || define( 'WPDB_PLUGIN_URL', WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/' );
defined( 'WPDB_PLUGIN_DIR' ) || define( 'WPDB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'WPDB_PLUGIN_FILE' ) || define( 'WPDB_PLUGIN_FILE', plugin_basename( __FILE__ ) );
defined( 'WPDB_PLUGIN_VERSION' ) || define( 'WPDB_PLUGIN_VERSION', '1.0.0' );
defined( 'WPDB_TABLE_REPORTS' ) || define( 'WPDB_TABLE_REPORTS', sprintf( '%swpdp_reports', $wpdb->prefix ) );

if ( ! class_exists( 'WPDP_Main' ) ) {
	/**
	 * Class WPDP_Main
	 */
	class WPDP_Main {

		protected static $_instance = null;

		protected static $_script_version = null;

		/**
		 * WPDP_Main constructor.
		 */
		function __construct() {

			add_action( 'init', array( $this, 'register_everything' ) );
			$this->include_files();
			self::$_script_version = defined( 'WP_DEBUG' ) && WP_DEBUG ? current_time( 'U' ) : WPDB_PLUGIN_VERSION;

			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
			add_filter( 'plugin_action_links', array( $this, 'add_plugin_action_links' ), 10, 4 );
			add_action( 'admin_init', array( $this, 'download_object' ) );
			add_action( 'admin_menu', array( $this, 'downloader_data_table' ) );
		}

		/**
		 * @return void
		 */
		function include_files() {
			require_once WPDB_PLUGIN_DIR . 'wp-list-table.php';
		}

		/**
		 * Handle downloading the object
		 */
		public function download_object() {

			if ( ! isset( $_GET['wpdp'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'wpdp-download' ) ) {
				return;
			}

			if ( ! class_exists( 'PclZip' ) ) {
				include ABSPATH . 'wp-admin/includes/class-pclzip.php';
			}

			$context = isset( $_GET['wpdp'] ) ? sanitize_text_field( $_GET['wpdp'] ) : '';
			$object  = isset( $_GET['object'] ) ? sanitize_text_field( $_GET['object'] ) : '';

			switch ( $context ) {
				case 'plugin':
					if ( strpos( $object, '/' ) ) {
						$object = dirname( $object );
					}
					$root = WP_PLUGIN_DIR;
					break;
				case 'muplugin':
					if ( strpos( $object, '/' ) ) {
						$object = dirname( $object );
					}
					$root = WPMU_PLUGIN_DIR;
					break;
				case 'theme':
					$root = get_theme_root( $object );
					break;
				default:
					wp_die( esc_html__( 'Something went wrong!', 'wp-downloader-plus' ) );
			}

			$object = sanitize_file_name( $object );

			if ( empty( $object ) ) {
				wp_die( esc_html__( 'Something went wrong!', 'wp-downloader-plus' ) );
			}

			$path       = $root . '/' . $object;
			$fileName   = $object . '.zip';
			$upload_dir = wp_upload_dir();
			$tmpFile    = trailingslashit( $upload_dir['path'] ) . $fileName;
			$archive    = new PclZip( $tmpFile );

			$archive->add( $path, PCLZIP_OPT_REMOVE_PATH, $root );

			header( 'Content-type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . $fileName . '"' );

			readfile( $tmpFile );
			unlink( $tmpFile );

			global $wpdb;

			$wpdb->insert( WPDB_TABLE_REPORTS,
				array(
					'object_name'   => $object,
					'object_type'   => $context,
					'downloaded_by' => get_current_user_id(),
					'datetime'      => current_time( 'mysql' ),
				)
			);

			exit;
		}


		/**
		 * Add custom links to the plugins list page.
		 *
		 * @param $links
		 * @param $file
		 * @param $plugin_data
		 * @param $context
		 *
		 * @return mixed
		 */
		function add_plugin_action_links( $links, $file, $plugin_data, $context ) {

			if ( 'dropins' === $context ) {
				return $links;
			}

			$what      = ( 'mustuse' === $context ) ? 'muplugin' : 'plugin';
			$new_links = array();

			foreach ( $links as $link_id => $link ) {

				if ( 'deactivate' == $link_id && WPDB_PLUGIN_FILE == $file) {
					$new_links['wpdp-reports']  = sprintf( '<a href="%s">%s</a>', admin_url( 'tools.php?page=wp-downloader-reports' ), esc_html__( 'Reports', 'wp-downloader-plus' ) );
				}

				$new_links[ $link_id ] = $link;
			}

			$new_links['wpdp-download'] = sprintf( '<a href="%s">%s</a>', $this->get_object_download_link( $file, $what ), esc_html__( 'Download', 'wp-downloader-plus' ) );

			return $new_links;
		}


		/**
		 * Return object download link
		 *
		 * @param string $object
		 * @param string $object_type
		 *
		 * @return mixed|void
		 */
		public function get_object_download_link( $download_object = '', $object_type = 'plugin' ) {
			$download_object = empty( $download_object ) ? 'object_name' : $download_object;
			$download_query  = build_query( array( 'wpdp' => $object_type, 'object' => $download_object ) );
			$download_link   = wp_nonce_url( admin_url( '?' . $download_query ), 'wpdp-download' );

			return apply_filters( 'WPDP/Filters/get_object_download_link', $download_link, $download_object, $object_type );
		}

		/**
		 * Admin Scripts
		 */
		function admin_scripts() {

			wp_enqueue_script( 'wpdp-admin', plugins_url( '/assets/admin/js/scripts.js', __FILE__ ), array( 'jquery' ), self::$_script_version, true );
			wp_localize_script( 'wpdp-admin', 'wpdp', array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'themeDownloadText' => esc_html__( 'Download' ),
				'themeDownloadLink' => $this->get_object_download_link( '', 'theme' ),
			) );
			wp_enqueue_style( 'downloader-plus-admin', WPDB_PLUGIN_URL . 'assets/admin/css/style.css' );

		}


		/**
		 * @return void
		 */
		function downloader_data_table() {
			add_submenu_page( 'tools.php', 'WP Downloader Plus', 'WP Downloader Plus', 'manage_options', 'wp-downloader-reports', array( $this, 'all_download_list' ), 4 );
		}

		/**
		 * @return void
		 */
		function all_download_list() {

			$report_table = new WPDP_Reports_table();

			ob_start();

			printf( '<h2>%s</h2>', esc_html__( 'WP Downloader Plus - Reports', 'wp-downloader-plus' ) );
			printf( '<p>%s</p>', esc_html__( 'Complete download reports.', 'wp-downloader-plus' ) );
			$report_table->prepare_items(); ?>

            <form action="" method="get">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>"/>
				<?php $report_table->search_box( __( 'search' ), 'search_id' ); ?>
            </form> <?php
			$report_table->display();

			printf( '<div class="wrap wpdp-table-colum">%s</div>', ob_get_clean() );
		}


		/**
		 * Register everything in init action
		 */
		function register_everything() {

			if ( ! function_exists( 'maybe_create_table' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			}

			$sql_create_table = "CREATE TABLE " . WPDB_TABLE_REPORTS . " (
                            id int(100) NOT NULL AUTO_INCREMENT,
                            object_name VARCHAR(255) NOT NULL,
                            object_type VARCHAR(255) NOT NULL,
                            downloaded_by VARCHAR(100) NOT NULL,
                            datetime DATETIME NOT NULL,
                            PRIMARY KEY (id)
                            );";

			maybe_create_table( WPDB_TABLE_REPORTS, $sql_create_table );
		}

		/**
		 * @return WPDP_Main|null
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}
	}
}

WPDP_Main::instance();

function pb_sdk_init_wp_downloader_plus() {

	if ( ! function_exists( 'get_plugins' ) ) {
		include_once ABSPATH . '/wp-admin/includes/plugin.php';
	}

	if ( ! class_exists( 'Pluginbazar\Client' ) ) {
		require_once( plugin_dir_path( __FILE__ ) . 'includes/sdk/classes/class-client.php' );
	}

	global $wpdp_sdk;

	$wpdp_sdk = new Pluginbazar\Client( esc_html( 'WP Downloader Plus' ), 'wp-downloader-plus', 0, __FILE__ );
}

/**
 * @global \Pluginbazar\Client $wpdp_sdk
 */
global $wpdp_sdk;

pb_sdk_init_wp_downloader_plus();

