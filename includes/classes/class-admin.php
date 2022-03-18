<?php

namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

if( class_exists( __NAMESPACE__ . '\Admin' ) ) return;
    
 /**
  * Class _Admin
  *
  * Back-end plugin UI
  */
final class Admin {

	 const setting = 'wetail-ssms-settings'; //settings page slug in back-end

	/**
	 * Activation redirection flag
	 *
	 * @var bool
	 */
	public static $just_activated = false;

	 /**
	  * Adding actions
	  */
	 public static function init(){
		 $multi = defined( 'MULTISITE' ) && MULTISITE;
		 if( !$multi )
	        add_action( 'admin_menu',          __CLASS__ . '::add_admin_menu'           );
		 else
			 add_action( 'network_admin_menu', __CLASS__ . '::add_network_admin_menu'   );
	     add_action( 'admin_enqueue_scripts',  __CLASS__ . '::add_scripts', 999         );
	     add_filter( 'plugin_action_links_' . plugin_basename( INDEX ),
		                                       __CLASS__ . '::setting_link'             );
	     add_action( 'activated_plugin',       __CLASS__ . '::activation_redirect'      );
	 }

	/**
	 * Redirect after successful activation
	 */
	 public static function activation_redirect(){
	 	if( ! self::$just_activated ) return;
	 	wp_safe_redirect( self::get_panel_url() );
	 	exit;
	 }

	/**
	 * Retrieve own control panel URL
	 *
	 * @return string
	 */
	 public static function get_panel_url(){
	 	return admin_url(
		    ( defined( 'MULTISITE' ) && MULTISITE
			    ? 'network/sites.php?page=' . self::setting
			    : 'tools.php?page=' . self::setting
		    )
	    );
	 }

	 /**
	  * Link to backend settings
	  *
	  * @param $l
	  * @return array
	  */
	 public static function setting_link( $l ) {
         return array_merge( [
             '<a href="' . self::get_panel_url() . '">' . __( 'Control panel', LNG ) . '</a>'
             ],
             $l
         );
	 }

	 /**
	  * Add styles and scripts to back-end
	  */
	 public static function add_scripts(){
	     if( !isset( $_REQUEST['page'] ) || self::setting !== $_REQUEST['page'] ) return;
	     wp_enqueue_style(   'wtssms_be_css', URL . '/assets/css/styles.css', null, time() );
	     wp_register_script( 'wtssms_be_js',  URL . '/assets/js/default.js', [ 'jquery' ], time(), false );
	     wp_enqueue_script(  'wtssms_be_js',  URL . '/assets/js/default.js', [ 'jquery' ], time(), false );
	     wp_localize_script( 'wtssms_be_js', 'wtssms', [
	         'nonce'        => wp_create_nonce( LNG ),
	         'action'       => AJAX_H,
		     'check_url'    => Logger::stat_url,
             'no_cache'     => time(),
             'init_msg'     => __('Initializing...', LNG),
		     'log_url'      => '<a href="' . Rest::urls( 'log' ) . '" target="_blank" title="' .
		                       __( 'View latest operations log', LNG ) . '" >' . __( 'Log', LNG ) . '</a> | ' .
							  '<a href="' . admin_url() . '" target="_self" title="' .
		                       __( 'Go to admin panel', LNG ) . '" >' . __( 'WP Admin', LNG ) . '</a>'
	     ] );
	 }

	 /**
	  * Adding admin menus
	  */
	 public static function add_admin_menu(){
		 $admin_page = add_management_page(
			 __( 'Multi Site Converter', LNG),
			 __( 'Multi Site Converter', LNG),
			 'manage_options',
			 self::setting,
			 function (){
				 include PATH . '/templates/plugin-settings.php';
			 }
		 );
	     //Load help
	     add_action( 'load-'.$admin_page,	[ __CLASS__, 'load_help_tab' ] );
	 }

	 /**
	  * Adding admin menus
	  */
	 public static function add_network_admin_menu(){
		 $admin_page = add_submenu_page(
			 'sites.php',
			 __( 'Multi Site Converter', LNG),
			 __( 'Multi Site Converter', LNG),
			 'manage_options',
			 self::setting,
			 function (){
				 include PATH . '/templates/plugin-settings.php';
			 }
		 );
	     //Load help
	     add_action( 'load-'.$admin_page,	[ __CLASS__, 'load_help_tab' ] );
	 }

	 /**
	  * Load help file into help tab
	  */
	 public static function load_help_tab(){
	     $screen = get_current_screen();
	     $help = file_get_contents( PATH . "/assets/help/help.html" );
	     $ti = 0;
	     $pos = strpos( $help, '<tab>' );
	     while( false !== $pos ){
	         $title_start = strpos( $help, '<h3>', $pos ) + 4;
	         $title = substr( $help, $title_start, strpos( $help, '</h3>', $title_start ) - $title_start );
	         $end_content = strpos( $help, '</tab>', $pos + 5 );
	         $content = substr( $help, $pos, $end_content - $pos );
	         $screen->add_help_tab( [
	             'id'	=> 'wtssms_help_tab_' . ++$ti,
	             'title'	=> $title,
	             'content'	=> $content
	         ] );
	         $pos = strpos( $help, '<tab>', $end_content + 6 );
	     }
	 }

}