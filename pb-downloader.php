<?php
/**
 * Plugin Name: All WP Downloader
 * Plugin URI: http://pluginbazar.com/
 * Description: This plugin for download WordPress plugin and theme.
 * Version: 1.0
 * Author: Md Khorshed Alam
 * Author URI: http://pluginbazar.com/
 *
 */


add_action( 'plugins_loaded', 'pb_loaded_hooks' );

if ( ! function_exists( 'pb_loaded_hooks' ) ) {
	/**
	 * @return void
	 */
	function pb_loaded_hooks() {
		add_filter( 'plugin_action_links', 'pb_plugin_action', 10, 4 );
		add_filter( 'theme_action_links', 'pb_theme_action', 10, 2 );
		add_action( 'admin_footer-themes.php', 'pb_load_scripts', 99 );
		if ( isset( $_GET['wpd'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'wpd-download' ) ) {
			pb_download();
		}
	}
}


if ( ! function_exists( 'pb_plugin_action' ) ) {
	/**
	 * @param $links
	 * @param $file
	 * @param $plugin_data
	 * @param $context
	 *
	 * @return mixed
	 */
	function pb_plugin_action( $links, $file, $plugin_data, $context ) {
		if ( 'dropins' === $context ) {
			return $links;
		}
		if ( 'mustuse' === $context ) {
			$what = 'muplugin';
		} else {
			$what = 'plugin';
		}

		$text          = esc_html( 'Download' );
		$dowload_query = build_query( array( 'wpd' => $what, 'object' => $file ) );
		$download_link = sprintf( '<a href="%s">%s</a>',
			wp_nonce_url( admin_url( '?' . $dowload_query ), 'wpd-download' ), $text );

		array_push( $links, $download_link );

		return $links;
	}
}


if ( ! function_exists( 'pb_theme_action' ) ) {
	/**
	 * @param $links
	 * @param $theme
	 *
	 * @return mixed
	 */
	function pb_theme_action( $links, $theme ) {
		$genarate_query = build_query( array(
			'wpd'    => 'theme',
			'object' => $theme->get_stylesheet(),
		) );
		$genarate_links = sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( '?' . $genarate_query ), 'wpd-download' ), esc_html( 'Download' ) );
		array_push( $links, $genarate_links );

		return $links;
	}
}


if ( ! function_exists( 'pb_load_scripts' ) ) {
	/**
	 * @return void
	 */
	function pb_load_scripts() {
		$pb_query     = build_query( array(
			'wpd'    => 'theme',
			'object' => '_object_'
		) );
		$pb_url       = wp_nonce_url( admin_url( '?' . $pb_query ), 'wpd-download' );
		$pb_text      = esc_html( 'Download' );
		$active_theme = get_stylesheet();

		$scripts_template = '<script type="text/javascript" id="wp-downloader">
			(function ($){
                var pbUrl = "%s",
                	pbLabel = "%s",
                	currentTheme= "%s",
                	pbButton= \'<a class="button button-info download hide-if-no-js" href= "\'+ pbUrl +\'">\'+ pbLabel +\' </a>\';
                	
                	$(window).load(function (){
						$("#current-theme .theme-options").after(\'<div class="theme-options"><a href="\'+ pbUrl.replace("_object_", currentTheme)\'">\'+ pbLabel +\'</a></div>\');
					$("#wpbody .theme .theme-actions .load-customize").each(function (i,e){
                        var button = $(pbButton),
                        	$e = $(e),
							link = $e.prop("href");
                        button.prop("href", pbUrl.replace("_object_", link.replace(/.*theme=(.*)(&|$)/,"$1")));
                        $e.parent().append(button);
					});							                        
                	});
                        var id = $("#tmpl-theme-single").html(),
                           	at = new RegExp(\'(<div class="active-theme">)(([\n\t]*(<#|<a).*[\n\t]*)*)(</div>)\');
                           	it = new RegExp(\'(<div class="inactive-theme">)(([\n\t]*(<#|<a).*[\n\t]*)*)(</div>)\');
                       id = id.replace(at, "$1$2"+ pbButton +"$5");
                       id = id.replace(it, "$1$2"+ pbButton +"$5");
                 $("#tmpl-theme-single").html(id),
                 
                 $(document).on("click", "a.button.download", function (e){
                     e.preventDefault();
                     var $this = $(this),
                     	link = $(this).parent().find(".load-customize").attr("href"),theme;
                     theme = link.replace(/.*theme=(.*)(&|$)/,"$1");
                     link = pbUrl.replace("_object_", theme).replace(new RegExp("&amp;", "g"), "&")
                     window.location = link;
                 });

			}(jQuery))	
		</script>';
		printf( $scripts_template, $pb_url, $pb_text, $active_theme );
	}
}


if ( ! function_exists( 'pb_download' ) ) {
	/**
	 * @return void
	 */
	function pb_download() {
		if ( ! class_exists( 'PclZip' ) ) {
			include ABSPATH . 'wp-admin/includes/class-pclzip.php';
		}

		$key    = $_GET['wpd'];
		$object = $_GET['object'];
		switch ( $key ) {
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
				wp_die( 'Badrequest&#8217; Opps?' );
		}

		$object = sanitize_file_name( $object );
		if ( empty( $object ) ) {
			wp_die( 'Badrequest&#8217; Opps?' );
		}

		$path     = $root . '/' . $object;
		$fileName = $object . '.zip';

		$upload_dir = wp_upload_dir();
		$tmpFile    = trailingslashit( $upload_dir['path'] ) . $fileName;


		$archive = new PclZip( $tmpFile );
		$archive->add( $path, PCLZIP_OPT_REMOVE_PATH, $root );
		header( 'Content-type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $fileName . '"' );

		readfile( $tmpFile );
		unlink( $tmpFile );

		exit;
	}
}





