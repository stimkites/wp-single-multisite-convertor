<?php
/**
 * Plugin Name: WP Single to Multisite convertor
 * Description:  Automatic transformations to multi-site and back to single or create a complete blog copy with a few clicks. Also allows cleaning up on WooCommerce data upon new blog creation.
 * Author: Stim (Wetail AB)
 * Version: 0.0.4
 * Author URI: http://wetail.io
 */

namespace Wetail\SSMS;

defined( 'ABSPATH' ) or die();

/**
 * Constants
 */
define( __NAMESPACE__ . '\LNG',         basename( __DIR__ ) );
define( __NAMESPACE__ . '\PATH',        dirname( __FILE__ ) );
define( __NAMESPACE__ . '\INDEX',       __FILE__            );
define( __NAMESPACE__ . '\NAME',        basename( __DIR__ ) );
define( __NAMESPACE__ . '\PLUGIN_ID',   basename( __DIR__ ) . '/' . basename( INDEX ) );
define( __NAMESPACE__ . '\URL',         dirname( plugins_url() ) . '/' . basename( dirname( __DIR__ ) ) . '/' . NAME  );

const AJAX_H = 'wt_ssms_aj';

/**
 * Load text  domain
 */
load_plugin_textdomain( LNG, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

/**
 * Autoloader for plugin parts
 */
require "autoload.php";

/**
 * Initialize plugin
 */
Logger  ::init();
Rest    ::init();
DB      ::init();
Ajax    ::init();
Admin   ::init();
