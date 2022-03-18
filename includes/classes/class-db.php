<?php

namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

if( class_exists( __NAMESPACE__ . '\DB' ) ) return;

final class DB {

	/**
	 * DB Connection flag
	 *
	 * @var bool
	 */
	private static $is_connected = false;

	/**
	 * Mysqli-object
	 *
	 * @var \MYSQLI
	 */
	private static $mysql = null;

	/**
	 * Word press prefix
	 *
	 * @var string
	 */
	private static $wpr = "wp_";

	/**
	 * DB name we are connected to - used for accessing DB Schema
	 *
	 * @var string
	 */
	private static $dbname = '';

	/**
	 * Last query result to free on new query
	 *
	 * @var \MYSQLI_RESULT
	 */
	private static $result = null;

	/**
	 * Database configuration for our own settings
	 */
	const DB = [
		'settings' => [
			'trefix' => 'single_to_multi_', //table prefix
			'fields' => [
				'id'        => 'bigint(20) not null auto_increment',
				'relation'  => 'bigint(20) not null default 0',
				'key'       => 'varchar(191) not null default ""',
				'value'     => 'longtext',
				'add_date'  => 'TIMESTAMP not null default CURRENT_TIMESTAMP'
			],
			'unique' => [
				'id'
			],
			'primary' => [
				'id'
			]
		]
	];

	/**
	 * Our default options
	 */
	const options_defaults = [
		'complete_copy'         => 1,
		'subdomain'             => 0,
		'maintenance_mode'      => 1,
		'clear_transients'      => 1,
		'clear_cache'           => 1,
		'remove_debug'          => 1,
		'ignore_file_errors'    => 0,
		'clear_cron'            => 1,
		'clear_woo'             => 1,
		'add_all_to_super'      => 1,
		'populate_level'        => 'all',
		'network_plugins'       => 0,
		'subdomains'            => [],
		'subpaths'              => [],
		'cp_subdomain'          => 0,
		'cp_clear_woo'          => 1,
		'cp_add_all_to_super'   => 1,
		'cp_populate_level'     => 'all',
		'cp_network_plugins'    => 0,
		'cp_subdomains'         => [],
		'cp_subpaths'           => [],
		's_primary_domain'      => 0,
		'cp_primary_domain'     => 0,
		's_erase_all'           => 1,
		's_clear_transients'    => 1,
		's_clear_cache'         => 1,
		's_clear_cron'          => 1,
		's_remove_debug'        => 1,
		's_ignore_file_errors'  => 0,
		'cp_ignore_file_errors' => 0
	];

	/**
	 * Full table names
	 *
	 * @var array
	 */
	private static $tables = [];

	/**
	 * Initialization
	 * 
	 * Connecting using WP credentials
	 */
	public static function init() {
		if( self::$is_connected ) return;
		self::$mysql = new \mysqli( \DB_HOST, \DB_USER, \DB_PASSWORD, \DB_NAME );
		if (self::$mysql->connect_error)
			die( 'Connect Error (' . self::$mysql->connect_errno . ') ' . self::$mysql->connect_error );
		else{
			self::$is_connected = true;
			self::$mysql->set_charset( "utf8" );
			self::$dbname = \DB_NAME;
			global $wpdb;
			self::$wpr = $wpdb->base_prefix;
			foreach ( self::DB as $tb_name=>$data )
				self::$tables[ $tb_name ] = self::$wpr . $data[ 'trefix' ] . $tb_name;
		}
		register_activation_hook    ( INDEX, [ __CLASS__, 'install'     ] );
		register_deactivation_hook  ( INDEX, [ __CLASS__, 'deactivate'  ] );
		register_uninstall_hook     ( INDEX, [ __CLASS__, 'uninstall'   ] );
	}

	/**
	 * Run a direct sql injection
	 *
	 * @param $sql
	 * @return \mysqli_result | bool
	 */
	public static function query( $sql ){
		( self::$is_connected ) or die('Not connected');
		if( isset( self::$result->num_rows ) )
			self::$result->free_result();
		try {
			self::$result = self::$mysql->query( $sql, \MYSQLI_USE_RESULT );
		}catch( \Exception $e ){
			Logger::write( '[FATAL SQL] CAUGHT AN EXCEPTION ON QUERY ['.$sql.'] '
			                  . $e->getCode() . ' ' . $e->getMessage()
			);
			return false;
		}finally {
			if ( self::$mysql->error ) {
				Logger::write( '[FATAL SQL] PROCESSING QUERY [' . $sql . '] MYSQL ERROR: ' . self::$mysql->error );
				return false;
			}
			return ( self::$result ? self::$result : true );
		}
	}

	/**
	 * Install hook
	 *
	 * @throws \Exception
	 */
	public static function install(){
		if ( ! empty( self::DB ) ) {
			self::init();
			foreach ( self::DB as $tname => $table_data ) {
				$sql = "CREATE TABLE IF NOT EXISTS `" . self::$tables[ $tname ] . "` (";
				foreach ( $table_data['fields'] as $field => $type ) {
					$sql .= "`$field` $type, ";
				}
				$sql .= "UNIQUE(" . implode( ',', array_map( function ( $a ) {
						return "`$a`";
					}, $table_data['unique'] ) ) . "),
                        PRIMARY KEY(" . implode( ',', array_map( function ( $a ) {
						return "`$a`";
					}, $table_data['primary'] ) ) . ") )";

				if ( $nid = self::$mysql->prepare( $sql ) ) {
					$nid->execute();
				} else {
					throw new \Exception(
						'SQL ERROR: "' . self::$mysql->error
						. '" DB TABLE: "' . self::$tables[ $tname ]
						. '" SQL : "' . $sql .'"', 500 );
				}
			}
			self::check_ms_mode();
		}
		Admin::$just_activated = true;
	}

	/**
	 * Add plugin to network activated ones on multisite
     *
     * @param bool $force
	 */
	public static function check_ms_mode( $force = false ){
	    if( ! $force || ! is_multisite() ) return;
		$pls = self::query( 
			"SELECT meta_value FROM " . self::$wpr . " sitemeta WHERE meta_key = 'active_sitewide_plugins' LIMIT 1" 
		);
		$plugins = [];
		if( $pls )
			$plugins = unserialize( $pls->fetch_assoc()['meta_value'] );
		if( isset( $plugins[ PLUGIN_ID ] ) ) return;
		$plugins[ PLUGIN_ID ] = time();
		$rz = serialize( $plugins );
		self::query( 
			"UPDATE " . self::$wpr . "sitemeta SET meta_value = '$rz' WHERE meta_key = 'active_sitewide_plugins' LIMIT 1" 
		);
	}

	/**
	 * Uninstall hook
	 *
	 * @throws \Exception
	 */
	public static function uninstall(){
		if ( ! empty( self::DB ) ) {
			self::init();
			foreach ( self::$tables as $table_name ) {
				$sql = "DROP TABLE IF EXISTS `{$table_name}`";
				if ( $nid = self::$mysql->prepare( $sql ) ) {
					$nid->execute();
				} else {
					throw new \Exception(
						'SQL ERROR: "' . self::$mysql->error
						. '" DB TABLE: "' . $table_name
						. '" SQL : "' . $sql .'"', 500 );
				}
			}
		}
	}

	/**
	 * Deactivate plugin everywhere on deactivating it from Network
	 */
	public static function deactivate(){
		if( ! is_multisite() ) return;
		$blogs = get_sites();
		Logger::write( $blogs );
		foreach( $blogs as $blog ){
			$plugins = get_blog_option( $blog->ID, 'active_plugins', true );
			Logger::write( $plugins );
			if( empty( $plugins ) ) continue;
			$plugins = array_diff( $plugins, [ PLUGIN_ID ] );
			Logger::write( $plugins );
			update_blog_option( $blog->ID, 'active_plugins', $plugins );
		}
	}

	/**
	 * SECTION: GET OPTIONS
	 *
	 * Note:    we are using our own separate table for storing options and other data because the plugin itself
	 *          is supposed to be blog-independent
	 *
	 * Get settings and options
	 */
	public static function get_options(){
		(self::$is_connected) or die('Not connected');
		self::query( "SELECT * FROM " . self::$tables['settings'] . " WHERE `relation` = 0" );
		if( !self::$result ) return [];
		$rows = self::$result->fetch_all(\MYSQLI_ASSOC);
		$rz = [];
		foreach( $rows as $row )
			$rz[ $row['key'] ] = unserialize( $row['value'] );
		return array_merge( self::options_defaults, $rz );
	}

	/**
	 * Get single option
	 *
	 * @param string $key
	 * @return string | bool
	 */
	public static function get_option( $key = '' ){
		(self::$is_connected) or die('Not connected');
		self::query( "SELECT `value` FROM " . self::$tables['settings'] . " WHERE `relation` = 0 AND `key` = '$key' LIMIT 1" );
		if( !self::$result ) return ( empty( self::options_defaults[ $key ] ) ? false : self::options_defaults[ $key ] );
		return unserialize( self::$result->fetch_row()[0] );
	}

	/**
	 * Get relative data
	 *
	 * @param int $id
	 * @param string $key
	 * @param bool $single
	 * @return string | bool | array
	 */
	public static function get_rel( $id, $key = '', $single = true ){
		(self::$is_connected) or die('Not connected');
		self::query( "SELECT `value` FROM " . self::$tables['settings'] . " WHERE `relation` = $id AND `key` = '$key'" );
		if( !self::$result ) return false;
		if( $single )
			return self::$result->fetch_all(\MYSQLI_ASSOC)[0]['value'];
		return array_map( function( $a ) { return unserialize( $a['value'] ); }, self::$result->fetch_all(\MYSQLI_ASSOC) );
	}


	/**
	 * SECTION: SET OPTIONS
	 *
	 * Set/update plugin options
	 * @param array $options
	 * @return bool
	 */
	public static function set_options( $options = [] ){
		(self::$is_connected) or die('Not connected');
		if( empty( $options ) ) return false;
		$aff = 0;
		foreach( $options as $key=>$value ){
		    $value = serialize( $value );
			self::query("DELETE FROM " . self::$tables['settings'] . " WHERE `relation` = 0 AND `key` = '$key'");
			self::query("INSERT IGNORE INTO " . self::$tables['settings'] . "( `relation`, `key`, `value`) VALUES ( 0, '$key', '$value' )");
			$aff+=( self::$mysql->insert_id > 0 );
		}
		return $aff === count( $options );
	}

	/**
	 * Set single option
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return bool
	 */
	public static function set_option( $key, $value ){
		return self::set_options( [ $key => $value ] );
	}

	/**
	 * Set relative option
	 *
	 * @param $id
	 * @param string $key
	 * @param string $value
	 *
	 * @return int
	 */
	public static function set_rel( $id, $key = '', $value = '' ){
		(self::$is_connected) or die('Not connected');
		$value = serialize( $value );
		self::query("DELETE FROM " . self::$tables['settings'] . " WHERE `relation` = $id AND `key` = '$key'");
		self::query("INSERT IGNORE INTO " . self::$tables['settings'] . "( `relation`, `key`, `value`) VALUES ( $id, '$key', '$value' )");
		return self::$mysql->insert_id;
	}


	/**
	 * SECTION: TABLES ROUTINE (GET, COPY, RENAME)
	 */


	/**
	 * Get all multi-site blogs (if any)
	 *
	 * @return array
	 */
	public static function get_blogs(){
		(self::$is_connected) or die('Not connected');
		if( ! is_multisite() ) return [];
		$r = self::query( "SELECT * FROM " . self::$wpr . "blogs ORDER BY blog_id ASC" );
		return ( $r ? $r->fetch_all(\MYSQLI_ASSOC) : [] );
	}

	/**
	 * Get all tables to copy
	 *
	 * @param int $blog_id
	 * @return array
	 */
	private static function get_all_tables( $blog_id = 0 ){
		(self::$is_connected) or die('Not connected');
		$excluded_tables = "'" . implode( "','", array_merge( self::$tables, [
			self::$wpr . 'usermeta',
			self::$wpr . 'users',
			self::$wpr . 'blogs',
			self::$wpr . 'blog_versions',
			self::$wpr . 'site',
			self::$wpr . 'sitemeta'
		] ) ) . "'";
		$excl_all_blogs = "";
		if( $blog_id <= 1 ) {
			$blogs = self::get_blogs();
			if ( ! empty( $blogs ) ) {
				foreach ( $blogs as $blog ) {
					if ( $blog['blog_id'] > 1 ) {
						$excl_all_blogs .= " AND `TABLE_NAME` NOT LIKE '" . self::$wpr . "{$blog['blog_id']}_%'";
					}
				}
			}
		}
		$r = self::query("SELECT `TABLE_NAME` as `name` 
                             FROM `INFORMATION_SCHEMA`.`TABLES`
                            WHERE `TABLE_SCHEMA` = '" . self::$dbname . "'
                              AND `TABLE_NAME` NOT IN ( $excluded_tables )"
		                  .( $blog_id > 1
								? " AND `TABLE_NAME` LIKE '" . self::$wpr . "{$blog_id}%'"
								: " AND `TABLE_NAME` LIKE '" . self::$wpr . "%'" )
						  .$excl_all_blogs
		);
		return ( $r ? $r->fetch_all(\MYSQLI_ASSOC) : [] );
	}

	/**
	 * Fetch total rows number
	 *
	 * @param $t_name
	 * @return int
	 */
	private static function get_total_rows( $t_name ){
		(self::$is_connected) or die('Not connected');
		$r = self::query("SELECT COUNT(*) FROM `$t_name`");
		if(!$r) return 0;
		return $r->fetch_row()[0];
	}

	/**
	 * Prepare for copying all tables
	 *
	 * @param int $blog_id
	 * @param $clear_cron
	 * @param $clear_transients
	 */
	private static function prepare_for_copying( $blog_id = 0, $clear_transients = 1, $clear_cron = 1 ){
		(self::$is_connected) or die('Not connected');
		$blog_suffix = ( $blog_id > 1 ? $blog_id . '_' : '' );
		if( $clear_transients ) {
            //1. Delete transients
            self::query("DELETE FROM " . self::$wpr . "{$blog_suffix}options WHERE `option_name` LIKE '%transient%'");
        }
        if( $clear_cron ){
            //2. Delete all crons
            self::query( "DELETE FROM " . self::$wpr . "{$blog_suffix}options WHERE `option_name` = 'cron'" );
        }
	}

	/**
	 * Copy all tables for new blog
	 *
	 * @param int $blog_id
	 * @param int $new_blog_id
	 * @return string
	 */
	public static function copy_tables( $blog_id = 0, $new_blog_id = 2 ){
		(self::$is_connected) or die('Not connected');
		Logger::write( '[DB_COPY] STARTING COPYING DATABASE TABLES...' );
		//Prepare
		self::prepare_for_copying( $blog_id, self::get_option( 'clear_transients' ), self::get_option( 'clear_cron' ) );
		$all_tables = self::get_all_tables( $blog_id );
		if( empty( $all_tables ) ) {
			Logger::write('[DB_COPY_ERROR] NO TABLES FOUND!');
			return __( 'No database tables found for copying', LNG );
		}

		//Set SQL mode for copying
		self::query( "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'" );

		//Make proper prefixes
		$tbpr = ( $blog_id > 1 ? self::$wpr . $blog_id . '_' : self::$wpr );
		$tbps = ( $new_blog_id > 1 ? self::$wpr . $new_blog_id . '_' : self::$wpr . '2_' );

		//Copy
		foreach( $all_tables as $t ){
			$old_t_name = $t['name'];
			$new_t_name = str_replace( $tbpr, $tbps, $t['name'] );
			if( $old_t_name === $new_t_name ) {
				Logger::write( '[DB_COPY_ERROR] TABLES NAMES EQUAL FOR [' . $new_t_name . ']!' );
				return sprintf( __( 'It is impossible to copy database table [%s] into itself', LNG ), $new_t_name );
			}
			self::query( "DROP TABLE IF EXISTS `$new_t_name`" );
			self::query( "CREATE TABLE IF NOT EXISTS `$new_t_name` LIKE `$old_t_name`" );
			self::query( "INSERT INTO `$new_t_name` SELECT * FROM `$old_t_name`" );

			//Check copied table
			$orr = self::get_total_rows( $old_t_name );
			$crr = self::get_total_rows( $new_t_name );
			if( $orr !== $crr ) {
				Logger::write('[DB_COPY_ERROR] GENERAL FAILURE ON COPYING "' . $old_t_name . '" INTO "' . $new_t_name . '": ORIGINAL ROWS ['.$orr.'] COPIED ROWS ['.$crr.']');
				return sprintf(
						__( 'Process failure for copying "%s" into "%s"', LNG ) . '. ' .
						__( 'Rows not copied: %s'),
						$old_t_name,
				        $new_t_name,
						( $orr - $crr )
				);

			}
		}
		return '';
	}

	/**
	 * Create tables for multisite
	 *
	 * @return bool
	 */
	public static function create_blog_tables(){
		(self::$is_connected) or die('Not connected');
		return ( false !== self::query(
			"CREATE TABLE IF NOT EXISTS `" . self::$wpr . "blogs` (
  				`blog_id` bigint(20) NOT NULL AUTO_INCREMENT,
  				`site_id` bigint(20) NOT NULL DEFAULT '0',
  				`domain` varchar(200) NOT NULL DEFAULT '',
  				`path` varchar(100) NOT NULL DEFAULT '',
				`registered` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`last_updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`public` tinyint(2) NOT NULL DEFAULT '1',
				`archived` tinyint(2) NOT NULL DEFAULT '0',
				`mature` tinyint(2) NOT NULL DEFAULT '0',
				`spam` tinyint(2) NOT NULL DEFAULT '0',
				`deleted` tinyint(2) NOT NULL DEFAULT '0',
				`lang_id` int(11) NOT NULL DEFAULT '0',
    			PRIMARY KEY ( `blog_id` ), 
    			UNIQUE KEY ( `blog_id` )
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" )
			&& false !== self::query(
			"CREATE TABLE IF NOT EXISTS `" . self::$wpr . "blog_versions` (
				`blog_id` bigint(20) NOT NULL DEFAULT '0',
				`db_version` varchar(20) NOT NULL DEFAULT '',
				`last_updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY ( `blog_id` )
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" )
	        && false !== self::query(
			"CREATE TABLE IF NOT EXISTS `" . self::$wpr . "site` (
			  	`id` bigint(20) NOT NULL AUTO_INCREMENT,
			  	`domain` varchar(200) NOT NULL DEFAULT '',
			  	`path` varchar(100) NOT NULL DEFAULT '',
			  	PRIMARY KEY ( `id` ), 
    			UNIQUE KEY ( `id` )
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" )
	         && false !== self::query(
			"CREATE TABLE IF NOT EXISTS `" . self::$wpr . "sitemeta` (
				  `meta_id` bigint(20) NOT NULL AUTO_INCREMENT,
				  `site_id` bigint(20) NOT NULL DEFAULT '0',
				  `meta_key` varchar(255) DEFAULT NULL,
				  `meta_value` longtext,
				  PRIMARY KEY ( `meta_id` ), 
    			  UNIQUE KEY ( `meta_id` )
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" )
	         && false !== self::query(
			"CREATE TABLE IF NOT EXISTS `" . self::$wpr . "blogmeta` (
				  `meta_id` bigint(20) NOT NULL AUTO_INCREMENT,
				  `blog_id` bigint(20) NOT NULL DEFAULT '0',
				  `meta_key` varchar(255) DEFAULT NULL,
				  `meta_value` longtext,
				  PRIMARY KEY ( `meta_id` ), 
	              UNIQUE KEY ( `meta_id` )
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" )
			&& self::fill_site_info()
		);
	}

	/**
	 * Get first admin ID for the blog
	 *
	 * @param int $blog_id
	 *
	 * @return int
	 */
	private static function get_first_admin( $blog_id = 0 ){
		( self::$is_connected ) or die( 'Not connected' );
		$prefix = self::$wpr . ( $blog_id > 1 ? $blog_id . '_' : '' );
		$r = self::query( "SELECT t1.ID 
							  FROM " . self::$wpr . "users t1, " . self::$wpr . "usermeta t2 
							 WHERE t2.meta_key = '{$prefix}user_level'
							 AND t2.meta_value = '10'
							 AND t2.user_id = t1.ID
							 ORDER BY t1.ID ASC
							 LIMIT 1" );
		if( !$r ) return 0;
		return $r->fetch_assoc()['ID'];
	}

	/**
	 * Get all admins for the blog as serialized array of logins
	 *
	 * @param int $blog_id
	 *
	 * @return array
	 */
	private static function get_all_admins( $blog_id = 0 ){
		( self::$is_connected ) or die( 'Not connected' );
		$prefix = self::$wpr . ( $blog_id > 1 ? $blog_id . '_' : '' );
		$r = self::query( "SELECT t1.user_login 
							  FROM " . self::$wpr . "users t1, " . self::$wpr . "usermeta t2 
							 WHERE t2.meta_key = '{$prefix}user_level'
							 AND t2.meta_value = '10'
							 AND t2.user_id = t1.ID" );
		if( !$r ) return [];
		return $r->fetch_all();
	}

	/**
	 * Prepare domain and path to insert into db
	 *
	 * @param $site_domain
	 * @param $site_path
	 */
	public static function prepare_domain( &$site_domain, &$site_path ){
		$site_domain = str_replace( 'http://', '', str_replace( 'https://', '', rtrim( $site_domain, '/' ) ) );
		$parts = explode( '/', $site_domain );
		$site_path = ( $site_path ? $site_path : '/' );
		if( !empty( $parts[1] ) ){
			$site_domain = $parts[0];
			array_shift( $parts );
			$site_path = '/' . implode( '/', $parts ) . '/';
		}
	}

	/**
	 * Insert global site data (https://codex.wordpress.org/Database_Description#Table:_wp_site)
	 *
	 * @return bool
	 */
	private static function fill_site_info(){
		( self::$is_connected ) or die( 'Not connected' );
		//1. Check if site info exists
		$r = self::query( "SELECT * FROM " . self::$wpr . "site" );
		if( !$r ) return false;
		$rrz = $r->fetch_all(\MYSQLI_ASSOC);
		if( !empty( $rrz[0] ) && !empty( $rrz[0]['domain'] ) ) return true;
		$ro = self::query(
			"SELECT `option_name`, `option_value` 
			   FROM " . self::$wpr . "options 
			  WHERE `option_name` 
			     IN (
			     	'siteurl',
			     	'blogname',
			     	'admin_email',
			     	'active_plugins'
			     )"
		);
		if( !$ro ) return false;
		$rrz = [];
		foreach( $ro->fetch_all(\MYSQLI_ASSOC) as $v )
			$rrz[ $v['option_name'] ] = $v['option_value'];
		if( !self::get_option( 'network_plugins' ) )
		    $rrz['active_plugins'] = serialize( [] );
		else{
		    //prepare active plugins
            $plugins = unserialize( $rrz['active_plugins'] );
            if( empty( $plugins ) )
                $plugins = [];
            $rpl = [];
            foreach( $plugins as $plugin )
                $rpl[ $plugin ] = time();
            $rrz['active_plugins'] = serialize( $rpl );
        }
		//Prepare data to insert
		$au = get_user_by( 'email', $rrz['admin_email'] );
		$auid = 0;
		if( $au )
		    $auid = $au->ID;
        if( !$auid )
            $auid = self::get_first_admin();
		if( !$auid )
		    $auid = get_current_user_id();
		$site_domain = $rrz['siteurl'];
		self::prepare_domain( $site_domain, $site_path );
		//add current theme to allowed
		$allowedthemes = serialize( [ get_stylesheet() => true ] );
		$locale = get_locale();
		$site_id = 1;
		return (
			false !== self::query("INSERT INTO `" . self::$wpr . "site` (`domain`, `path`) VALUES ( '$site_domain', '$site_path' )") &&
			$site_id = self::$mysql->insert_id &&
			false !== self::query(
				"INSERT INTO `" . self::$wpr . "sitemeta` ( `site_id`, `meta_key`, `meta_value` ) VALUES
				( $site_id, 'site_name', '{$rrz['blogname']}' ),
				( $site_id, 'admin_email', '{$rrz['admin_email']}' ),
				( $site_id, 'admin_user_id', '$auid' ),
				( $site_id, 'site_admins', 'a:0:{}' ),
				( $site_id, 'registration', 'none' ),
				( $site_id, 'ms_files_rewriting', '0' ),
				( $site_id, 'allowedthemes', '$allowedthemes' ),
				( $site_id, 'upload_filetypes', 'jpg jpeg png gif mov avi mpg 3gp 3g2 midi mid pdf doc ppt odt pptx docx pps ppsx xls xlsx key mp3 ogg flac m4a wav mp4 m4v webm ogv flv' ),
				( $site_id, 'blog_upload_space', 100 ),
				( $site_id, 'fileupload_maxk', 1500 ),
				( $site_id, 'illegalnames', 'a:9:{i:0;s:3:\"www\";i:1;s:3:\"web\";i:2;s:4:\"root\";i:3;s:5:\"admin\";i:4;s:4:\"main\";i:5;s:6:\"invite\";i:6;s:13:\"administrator\";i:7;s:5:\"files\";i:8;s:4:\"blog\";}' ),
				( $site_id, 'active_sitewide_plugins', '{$rrz['active_plugins']}' ),
				( $site_id, 'WPLANG', '$locale' ),
				( $site_id, 'site_url', '{$rrz['siteurl']}' ) 
				"
			) &&
           1 === self::insert_blog( $site_domain, $site_path )
		);
	}

	/**
	 * Create new blog
	 *
	 * @param $domain
	 * @param $path
	 *
	 * @return int|mixed
	 */
	public static function insert_blog( $domain, $path ){
		( self::$is_connected ) or die( 'Not connected' );
		if( empty( $domain ) ) return 0;
		self::prepare_domain( $domain, $path );
		$timestamp = strftime( "%Y-%m-%d %H:%M:%S" );
		if( false !== self::query(
			"INSERT INTO `" . self::$wpr . "blogs` ( `site_id`, `domain`, `path`, `registered`, `last_updated` ) " .
			"VALUES ( 1, '$domain', '$path', '$timestamp', '$timestamp' )"
			) )
			return self::$mysql->insert_id;
		return 0;
	}

	/**
	 * Drop blog
	 *
	 * @param int $blog_id
	 *
	 * @return int|mixed
	 */
	public static function drop_blog( $blog_id ){
		( self::$is_connected ) or die( 'Not connected' );
		if( $blog_id <= 1 ) return false;
		return (
			false !== self::query( "DELETE FROM `" . self::$wpr . "blogs` WHERE blog_id = $blog_id" ) &&
			false !== self::drop_blog_tables( $blog_id )
		);
	}

	/**
	 * Edit options for newly created blog
	 *
	 * @param $new_blog_id
	 * @param $domain
	 * @param $path
	 *
	 * @return bool
	 */
	public static function process_options( $new_blog_id, $domain, $path ){
		( self::$is_connected ) or die( 'Not connected' );
		if( $new_blog_id <= 1 || !$domain ) return false;
		self::prepare_domain( $domain, $path );
		$npr = self::$wpr . $new_blog_id . '_';
		$ssl = ( false !== strpos( site_url(), 'https' ) ? 'https://' : 'http://' );
		$homeurl = rtrim( $ssl . $domain . $path, '/' );
		return (
			//Rename user roles for new blog
			false !== self::query( "UPDATE {$npr}options SET option_name = '{$npr}user_roles' WHERE option_name =  '" . self::$wpr . "user_roles' LIMIT 1" ) &&
			//EDIT site_url and homeurl options
			false !== self::query( "UPDATE {$npr}options SET option_value = '$homeurl' WHERE option_name = 'siteurl' LIMIT 1" ) &&
			false !== self::query( "UPDATE {$npr}options SET option_value = '$homeurl' WHERE option_name = 'home' LIMIT 1" )
		);
	}

	/**
	 * Clean all woocommerce orders, comments, subscriptions for non-original blog
	 *
	 * @param int $blog_id
	 * @param bool $force Force even original orders to be deleted (hope wont be used)
	 *
	 * @return bool
	 */
	public static function clean_up_woo_orders( $blog_id, $force = false ){
		( self::$is_connected ) or die( 'Not connected' );
		if( !$blog_id && !$force ) return false;
		$prefix = self::$wpr . ( $blog_id > 1 ? $blog_id . '_' : '' );
		return (
			false !== self::query( "DELETE FROM {$prefix}woocommerce_order_items" ) &&
			false !== self::query( "DELETE FROM {$prefix}woocommerce_order_itemmeta" ) &&
			false !== self::query( "DELETE FROM {$prefix}commentmeta WHERE comment_id IN "
			                            ."( SELECT comment_ID as comment_id FROM {$prefix}comments "
			                                ."WHERE comment_type = 'order_note' )" ) &&
			false !== self::query( "DELETE FROM {$prefix}comments WHERE comment_type = 'order_note'" ) &&
			false !== self::query( "DELETE FROM {$prefix}postmeta 
											WHERE post_id 
											IN ( SELECT ID as post_id 
												   FROM {$prefix}posts 
												  WHERE post_type = 'shop_order' )" ) &&
			false !== self::query( "DELETE FROM {$prefix}posts WHERE post_type = 'shop_order'" ) &&
			false !== self::query( "DELETE FROM {$prefix}postmeta 
											WHERE post_id 
											IN ( SELECT ID as post_id 
												   FROM {$prefix}posts 
												  WHERE post_type = 'shop_subscription' )" ) &&
			false !== self::query( "DELETE FROM {$prefix}posts WHERE post_type = 'shop_subscription'" )
		);
	}

	/**
	 * Populate all admins from first to second blog
	 *
	 * @param int $from_blog
	 * @param int $to_blog
	 * @param string $level
	 *
	 * @return bool
	 */
	public static function populate_users( $from_blog = 0, $to_blog = 2, $level = 'admins' ){
		( self::$is_connected ) or die( 'Not connected' );
		$prfx = self::$wpr . ( $from_blog > 1 ? $from_blog . '_' : '' );
		$psfx = self::$wpr . ( $to_blog > 1 ? $to_blog . '_' : '' );
		$cuid = get_current_user_id();
		switch( $level ){
			case 'all':
				$where1 = "WHERE meta_key = '{$prfx}capabilities' AND meta_value IS NOT NULL";
				$where2 = "WHERE meta_key = '{$prfx}user_level' AND meta_value IS NOT NULL";
				break;
			case 'admins':
				$where1 = "WHERE meta_key = '{$prfx}capabilities' AND meta_value LIKE '%administrator%'";
				$where2 = "WHERE meta_key = '{$prfx}user_level' AND meta_value = 10";
				break;
			default:
				$where1 = $where2 = "WHERE user_id = $cuid";
		}
		return (
			false !== self::query( "DELETE FROM " . self::$wpr . "usermeta WHERE meta_key = '{$psfx}capabilities'" ) &&
			false !== self::query( "DELETE FROM " . self::$wpr . "usermeta WHERE meta_key = '{$psfx}user_level'" ) &&
			false !== self::query(
				"INSERT INTO " . self::$wpr . "usermeta ( user_id, meta_key, meta_value ) 
					  SELECT user_id, '{$psfx}capabilities', meta_value 
					    FROM " . self::$wpr . "usermeta " . $where1 ) &&
			false !== self::query(
				"INSERT INTO " . self::$wpr . "usermeta ( user_id, meta_key, meta_value ) 
					  SELECT user_id, '{$psfx}user_level', meta_value 
					    FROM " . self::$wpr . "usermeta " . $where2 )
		);
	}

	/**
	 * Update/create super admins
	 *
	 * @param int $from_blog
	 * @param bool $all
	 *
	 * @return bool
	 */
	public static function super_users( $from_blog = 0, $all = true ){
		( self::$is_connected ) or die( 'Not connected' );
		$super_users = get_site_option( 'site_admins' );
		if( empty( $super_users ) )
			$super_users = [];
		if( !$all ){
			$cu = wp_get_current_user();
			if( !in_array( $cu->user_login, $super_users ) )
				$super_users[] = $cu->user_login;
		}else{
			$all_admins = self::get_all_admins( $from_blog );
			if( ! empty( $all_admins ) )
				foreach ( $all_admins as $admin )
					if( !in_array( $admin, $super_users ) )
						$super_users[] = $admin[0];
		}
		$su = serialize( $super_users );
		return (
			false !== self::query( "UPDATE " . self::$wpr . "sitemeta SET meta_value = '$su' WHERE meta_key = 'site_admins'" )
		);
	}

	/**
	 * REMOVE BLOGS SECTION
	 *
	 * Note: single blog may be removed in regular WP Network Sites admin panel
	 */


	/**
	 * Remove multisite mode from DB
	 *
	 * @param int $remain_blog_id Blog ID to remain as primary
	 * @param bool $completely Clean everything up
	 *
	 * @return bool
	 */
	public static function remove_multisite( $remain_blog_id = 1, $completely = true ){
		( self::$is_connected ) or die( 'Not connected' );
		if( $remain_blog_id <= 1 && !$completely ) return true; //nothing to worry about
		self::prepare_for_copying( $remain_blog_id, self::get_option( 's_clear_transients' ), self::get_option( 's_clear_cron' ) );
		if( !$completely ) {
			$new_prefix = self::$wpr . $remain_blog_id . '_';
			return (
				Filer::set_new_prefix( $new_prefix ) &&
				false !== self::query( "RENAME TABLE " . self::$wpr . "users TO {$new_prefix}users" ) &&
				false !== self::query( "RENAME TABLE " . self::$wpr . "usermeta TO {$new_prefix}usermeta" )
			);
		}
		//Destroy all non-primary blogs and multi-site blogs
		$all_blogs = self::get_blogs();
		self::drop_blog_tables();
		if( empty( $all_blogs ) ) return true;
		foreach ( $all_blogs as $blog )
			if( $blog['blog_id'] !== $remain_blog_id )
				self::drop_blog_tables( $blog['blog_id'] );
		if( $remain_blog_id <= 1 ) return true;
		//Rename remain blog tables
		$all_tables = self::get_all_tables( $remain_blog_id );
		$_prefix = self::$wpr.$remain_blog_id.'_';
		foreach( $all_tables as $table ) {
			$new_name = str_replace( $_prefix, self::$wpr, $table['name'] );
			if ( false === self::query( "DROP TABLE IF EXISTS {$new_name}" ) ||
			     false === self::query( "RENAME TABLE {$table['name']} TO {$new_name}" ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Remove multisite tables (in case we switch back to single site and do not keep multisite data)
	 * or just remove all tables for a definite blog
	 *
	 * @param int $blog_id
	 *
	 * @return bool
	 */
	private static function drop_blog_tables( $blog_id = 0 ) {
		( self::$is_connected ) or die( 'Not connected' );
		if( $blog_id <= 1 )
			return (
				false !== self::query( "DROP TABLE IF EXISTS `" . self::$wpr . "blogs`" ) &&
				false !== self::query( "DROP TABLE IF EXISTS `" . self::$wpr . "blog_versions`" ) &&
				false !== self::query( "DROP TABLE IF EXISTS `" . self::$wpr . "site`" ) &&
				false !== self::query( "DROP TABLE IF EXISTS `" . self::$wpr . "sitemeta`" ) &&
				false !== self::query( "DROP TABLE IF EXISTS `" . self::$wpr . "blogmeta`" )
			);
		$all_tables = self::get_all_tables( $blog_id );
		if( empty( $all_tables ) ) return true;
		foreach ($all_tables as $table)
			if( false === self::query( "DROP TABLE IF EXISTS `{$table['name']}`" ) ) return false;
		$psfx = self::$wpr . $blog_id . '_';
		return (
			//remove user levels for blog
			false !== self::query( "DELETE FROM " . self::$wpr . "usermeta WHERE meta_key = '{$psfx}capabilities'" ) &&
			false !== self::query( "DELETE FROM " . self::$wpr . "usermeta WHERE meta_key = '{$psfx}user_level'" )
		);
	}

}


