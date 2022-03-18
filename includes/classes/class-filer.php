<?php
namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

if( class_exists( __NAMESPACE__ . '\Filer' ) ) return;

/**
 * Class Filer
 * @package Wetail\SSMS
 *
 * Files processing
 *
 */
final class Filer {

    /**
     * Paths to files
     */
    const paths = [
        'cache'     => \ABSPATH . 'wp-content/cache',
        'uploads'   => \ABSPATH . 'wp-content/uploads',
        'htaccess'  => \ABSPATH . '.htaccess',
        'config'    => \ABSPATH . 'wp-config.php'
    ];

	/**
	 * Enable or disable maintenance mode
	 *
	 * @param $enable
	 *
	 * @return mixed
	 */
    public static function maintenance( $enable ){
    	$mf = \ABSPATH . '.maintenance';
    	$mode = file_exists( $mf );
    	if( $enable ){
    	    if ( $mode ) return true;
    	    @file_put_contents( $mf, '<?php $upgrading = ' . time() . '; ?>' );
    	    return file_exists( $mf );
	    }
    	if( !$mode ) return true;
    	unlink( $mf );
    	return !file_exists( $mf );
    }

    /**
     * Check files accessibility for writing
     *
     * @return mixed|string
     */
    public static function check_files(){
        foreach( self::paths as $pth ) {
	        if ( ( is_file( $pth ) || is_dir( $pth ) )
	             && ! is_writable( $pth ) ) {
		        return $pth;
	        }
        }
	    return '';
    }

	/**
	 * Measure insufficient disk space for the copy operations
	 *
	 * @param int $from_blog
	 * @return int
	 */
    public static function check_disk( $from_blog = 0 ){
    	$dir = self::paths['uploads'] . ( $from_blog > 1 ? '/sites/' . $from_blog : '' );
		$total_free = disk_free_space( $dir );
		$required = self::dir_size( $dir ) + 4096; //for safe wp-config and htaccess changes
	    if( $total_free < $required )
	    	return ceil( ( $required - $total_free )/1024/1024 );
	    return 0;
    }

    /**
     * Set new DB prefix
     *
     * @param string $prefix
     * @return bool
     */
    public static function set_new_prefix( $prefix = 'wp_' ){
        $fc = file_get_contents( self::paths['config'] );
        $fc = preg_replace( '/\$table_prefix(.*?);/', '$table_prefix = "' . $prefix . '";', $fc );
        if( !$fc ) return false;
        return ( false !== file_put_contents( self::paths['config'], $fc ) );
    }

    /**
     * Enable multisite mode
     *
     * @param string $domain
     * @param string $path
     * @param bool $subdomain
     *
     * @return bool
     */
    public static function enable_multisite( $domain = '', $path = '/', $subdomain = false ){
        if( defined( 'MULTISITE' ) && MULTISITE ) return self::set_ms_mode( $subdomain );
        $or_config = @file_get_contents( self::paths['config'] );
        if( !$or_config ) return false;
        DB::set_option( 'original_config', base64_encode( $or_config ) );
        $htaccess = @file_get_contents( self::paths['htaccess'] );
        if( !$htaccess ) return false;
        DB::set_option( 'original_htaccess', base64_encode( $htaccess ) );
        if( !$domain ) $domain = site_url();
        DB::prepare_domain( $domain, $path );
        //check DOMAIN CURRENT SITE definition
        if( defined( 'DOMAIN_CURRENT_SITE' ) )
            $or_config = preg_replace(
                    [
                        '/define(.*?)DOMAIN_CURRENT_SITE(.*?);/',
                        '/define(.*?)PATH_CURRENT_SITE(.*?);/'
                    ],
                    [ '', '' ],
                    $or_config
                );
        $or_config = preg_replace(
        	'/define(.*?)ABSPATH(.*?);/',
        	"   define('ABSPATH', dirname(__FILE__) . '/');\r\n\r\n".
	        "/*Multisite Mode*/\r\n" .
	        "define('MULTISITE', true);\r\n".
	        "define('WP_ALLOW_MULTISITE', true);\r\n".
	        "define('DOMAIN_CURRENT_SITE', '$domain');\r\n".
	        "define('PATH_CURRENT_SITE', '$path');\r\n".
	        "define('SITE_ID_CURRENT_SITE', 1);\r\n".
	        "define('BLOG_ID_CURRENT_SITE', 1);\r\n\r\n",
            $or_config
        );
        return (
            false !== file_put_contents(
                self::paths['config'],
                $or_config
            )
            && self::set_htaccess()
            && self::set_ms_mode( $subdomain )
        );
    }

	/**
	 * Disable multisite mode
	 *
	 * @return bool
	 */
	public static function disable_multisite(){
		if( !defined( 'MULTISITE' ) ) return true;
		$or_config = @file_get_contents( self::paths['config'] );
		if( !$or_config ) return false;
		DB::set_option( 'original_config', base64_encode( $or_config ) );
		$htaccess = @file_get_contents( self::paths['htaccess'] );
		if( !$htaccess ) return false;
		DB::set_option( 'original_htaccess', base64_encode( $htaccess ) );
		$or_config = preg_replace(
			[
				'/define(.*?)SUBDOMAIN_INSTALL(.*?);/',
				'/define(.*?)DOMAIN_CURRENT_SITE(.*?);/',
				'/define(.*?)PATH_CURRENT_SITE(.*?);/',
				'/define(.*?)WP_ALLOW_MULTISITE(.*?);/',
				'/define(.*?)MULTISITE(.*?);/',
				'/define(.*?)SITE_ID_CURRENT_SITE(.*?);/',
				'/define(.*?)BLOG_ID_CURRENT_SITE(.*?);/',
				'/\/\*Multisite Mode\*\//'
			],
			[ '', '', '', '', '', '', '', '' ],
			$or_config
		);
		if( !$or_config ) return false;
		return (
			false !== file_put_contents(
				self::paths['config'],
				$or_config
			) &&
			self::unset_htaccess()
		);
	}

    /**
     * Enable subdomain mode
     *
     * @param $mode
     * @return bool
     */
    public static function set_ms_mode( $mode ){
        $fc = file_get_contents( self::paths['config'] );
        if( false === strpos( $fc, 'SUBDOMAIN_INSTALL' ) ){
	        return
		        @file_put_contents(
		        	self::paths['config'],
			        preg_replace(
		                '/define(.*?)BLOG_ID_CURRENT_SITE(.*?);/',
		                "define('BLOG_ID_CURRENT_SITE', 1);\r\n".
		                "define('SUBDOMAIN_INSTALL', " . ( $mode ? 'true' : 'false' ) . ");",
		                $fc)
	            );
        }
        $fc = preg_replace(
            '/define(.*?)SUBDOMAIN_INSTALL(.*?);/',
            "define('SUBDOMAIN_INSTALL', " . ( $mode ? 'true' : 'false' ) . ");",
            $fc
        );
        if( !$fc ) return false;
        return ( false !== file_put_contents( self::paths['config'], $fc ) );
    }

    /**
     * Set htaccess file for multisite
     *
     * @return bool
     */
    private static function set_htaccess(){
        $fc = file_get_contents( self::paths['htaccess'] );
        $c ="# BEGIN WordPress\r\n" .
			"<IfModule mod_rewrite.c>\r\n" .
            "RewriteEngine On\r\n" .
			"RewriteBase /\r\n" .
			"RewriteRule ^index\.php".'\$'." - [L]\r\n" .
			"# add a trailing slash to /wp-admin\r\n" .
			"RewriteRule ^wp-admin".'\$'." wp-admin\/ [R=301,L]\r\n" .
			"# uploaded files\r\n" .
			"RewriteRule ^([_0-9a-zA-Z-]+\/)?files\/(.+)  wp-includes\/ms-files.php?file=".'\$2'." [L]\r\n" .
			"RewriteCond %{REQUEST_FILENAME} -f [OR]\r\n" .
			"RewriteCond %{REQUEST_FILENAME} -d\r\n" .
			"RewriteRule ^ - [L]\r\n" .
			"RewriteRule ^(wp-(content|admin|includes).*) ".'\$1'." [L]\r\n" .
			"RewriteRule  ^([_0-9a-zA-Z-]+\/)?(wp-(content|admin|includes).*) ".'\$2'." [L]\r\n" .
			"RewriteRule  ^([_0-9a-zA-Z-]+\/)?(.*.php)".'\$'." ".'\$2'." [L]\r\n" .
			"RewriteRule ^(.*\\.php)".'$'." ".'\$1'." [L]\r\n" .
			"RewriteRule . index.php [L]\r\n" .
			"</IfModule>\r\n".
			'# END WordPress';
        if( false === mb_strpos( mb_strtolower( $fc ), 'begin wordpress' ) )
	        return ( false !== file_put_contents( self::paths['htaccess'], $c, FILE_APPEND ) );
        $fc = preg_replace(
            '/#([ ]*)begin(.*?)wordpress(.*?)end(.*?)wordpress/ims',
            $c,
            $fc
        );
        if( ! $fc ) return false;
        return ( false !== file_put_contents( self::paths['htaccess'], $fc ) );
    }

	/**
	 * Set htaccess file for single site
	 *
	 * @return bool
	 */
	private static function unset_htaccess(){
		$fc = file_get_contents( self::paths['htaccess'] );
		$c ="# BEGIN WordPress\r\n".
			"<IfModule mod_rewrite.c>\r\n".
			"RewriteEngine On\r\n".
			"RewriteBase /\r\n".
			'RewriteRule ^index\.php$ - [L]' . "\r\n".
			"RewriteCond %{REQUEST_FILENAME} !-f\r\n".
			"RewriteCond %{REQUEST_FILENAME} !-d\r\n".
			"RewriteRule . /index.php [L]\r\n".
			"</IfModule>\r\n".
			"# END WordPress";
		if( false === mb_strpos( mb_strtolower( $fc ), 'begin wordpress' ) )
			return ( false !== file_put_contents( self::paths['htaccess'], $c, FILE_APPEND ) );
		$fc = preg_replace(
			'/#([ ]*)begin(.*?)wordpress(.*?)end(.*?)wordpress/is',
			$c,
			$fc
		);
		if( !$fc ) return false;
		return ( false !== file_put_contents( self::paths['htaccess'], $fc ) );
	}

    /**
     * Restore original files
     *
     * @return bool
     */
    public static function restore_core(){
        return (
            false !== file_put_contents( self::paths['htaccess'], base64_decode( DB::get_option( 'original_htaccess' ) ) ) &&
            false !== file_put_contents( self::paths['config'], base64_decode( DB::get_option( 'original_config' ) ) )
        );
    }

	/**
	 * Multisite to single site
	 *
	 * @param $blog_id
	 *
	 * @return bool
	 */
    public static function blog_to_single( $blog_id ){
    	if( $blog_id <= 1 ) return (
		    self::clear_dir( self::paths['uploads'] . '/sites', true ) &&
		    ( DB::get_option( 's_remove_debug' ) ? self::delete_wp_log() : true )
	    );
    	if( ! self::before_copy( $blog_id ) ){
    		Logger::write( '[WARNING] Copy preparation failed!' );
	    }
		return (
			self::copy_dir_content( self::paths['uploads'] . '/sites/' . $blog_id, self::paths['uploads'] ) &&
			self::clear_dir( self::paths['uploads'] . '/sites', true ) &&
			( DB::get_option( 's_remove_debug' ) ? self::delete_wp_log() : true )
		);
    }

	/**
	 * Delete WordPress log file
	 *
	 * @return bool
	 */
    private static function delete_wp_log(){
    	$fn = ini_get( 'error_log' );
	    return ( is_file( $fn ) ? unlink( $fn ) : true );
    }

	/**
	 * Get directory size
	 *
	 * @param $dir
	 *
	 * @return int
	 */
	private static function dir_size( $dir ) {
		if( is_file( $dir ) ) return filesize( $dir );
		if( !is_dir( $dir ) ) return 0;
		$files = scandir( $dir );
		$rz = 0;
		if( !empty( $files ) )
			foreach( $files as $file ) {
				if ( $file !== "." && $file !== ".." ) {
					if ( is_dir( $dir . '/' . $file ) )
						$rz += self::dir_size( $dir . '/' . $file );
					else
						$rz += filesize( $dir . '/' . $file );
				}
			}
		return $rz;
	}

	/**
	 * Remove files in the directory
	 *
	 * @param string $dir
	 * @param bool $self
	 *
	 * @return true
	 */
    private static function clear_dir( $dir, $self = false ) {
    	if( !is_dir( $dir ) ) return true;
	    $files = scandir( $dir );
	    if( !empty( $files ) )
		    foreach( $files as $file ) {
			    if ( $file !== "." && $file !== ".." ) {
				    if ( is_dir( $dir . '/' . $file ) )
				        self::clear_dir( $dir . '/' . $file, true );
				    //Ignore .htaccess in root if we do not remove root
				    elseif ( $self || '.htaccess' !== $file )
					    unlink( $dir . '/' . $file );
			    }
		    }
	    if( $self ) rmdir( $dir );
	    return true;
    }

	/**
	 * Copy all files and subfolders to the new destination
	 *
	 * @param string $init
	 * @param string $destination
	 * @param array $filter - files and folders to bypass
	 *
	 * @return bool
	 */
	private static function copy_dir_content( $init, $destination, $filter = [ 'sites' ] ) {
		if( ! is_dir( $init ) ) return false;
		$files = scandir( $init );
		Logger::write( 'Copying from ' . $init . ' to ' . $destination );
		if ( !is_dir( $destination ) ) {
            if( !@mkdir( $destination, 0775, true ) ){
            	$msg = error_get_last();
	            Logger::write( 'Folder "' . $destination . '" could not be created: ' . $msg['message'] );
                return false;
            }
        }
		if( ! empty( $files ) )
			foreach( $files as $file ) {
				if ( $file === "." || $file === ".." || in_array( $file, $filter ) ) continue;
				if ( is_dir( $init . '/' . $file ) ) {
					if( !self::copy_dir_content( $init . '/' . $file, $destination . '/' . $file ) )
						return false;
				} else {
					if( ! @copy( $init . '/' . $file, $destination . '/' . $file ) ){
						$msg = error_get_last();
						Logger::write( 'File "' . $file . '" could not be copied into "' . $destination . '/' . $file . '". Error: ' . $msg['message'] );
						return false;
					} elseif( $error = self::verify_copied_file( $init . '/' . $file, $destination . '/' . $file ) ) {
						Logger::write( $error );
						return false;
					}
				}
			}
		return true;
	}
	
	/**
	 * Verify copied file, try to copy it the PHP way
	 *
	 * @since 0.0.4
	 *
	 * @param string $source
	 * @param string $destination
	 *
	 * @return string Error message
	 */
	private static function verify_copied_file( $source, $destination ){
		if( ! file_exists( $source ) ) return null;
		if( ! file_exists( $destination ) || filesize( $source ) !== filesize( $destination ) ) {
			if( ! ( $ff = fopen( $source, 'rb' ) ) ) return 'Could not open source file "' . $source . '" for copying...';
			if( ! ( $tf = fopen( $destination, 'wb' ) ) ) return 'Could not open target file "' . $destination . '" for writing...';
			while( ! feof( $ff ) ){
				$buffer = fread( $ff, 32768 );
				if( $buffer )
					fwrite( $tf, $buffer );
			}
			fclose( $ff );
			fclose( $tf );
			if( ! file_exists( $destination ) || filesize( $source ) !== filesize( $destination ) )
				return '[FAIL] Attempt to copy file "' . $source . '" into "' . $destination . '" failed completely!';
		}
		return null;
	}

	/**
	 * Clean up cache and logs before copying
	 *
	 * @param int $blog_id
	 * @return bool
	 */
    private static function before_copy( $blog_id ){
        if( ! DB::get_option( 'clear_cache' ) ) {
	        Logger::write( '"Clear cache" option is disabled, skipping clearing cache and logs' );
        	return true;
        }
	    Logger::write( 'Clearing cache and logs...' );
		return (
			wp_cache_flush() &&
            self::clear_dir( self::paths['cache'] ) &&
			self::clear_dir( self::paths['uploads'] . ( $blog_id > 1 ? '/sites/' . $blog_id : '' ) . '/wc-logs' ) &&
			( DB::get_option( 'remove_debug' ) ? self::delete_wp_log() : true )
		);
    }


	/**
	 * Complete blog copy
	 *
	 * @param int $from_blog
	 * @param int $to_blog
	 * @return bool
	 */
    public static function make_copy( $from_blog, $to_blog ){
	    if( ! self::before_copy( $from_blog ) ){
		    Logger::write( '[WARNING] Copy preparation failed!' );
	    }
        return (
	        self::copy_dir_content(
	        	self::paths['uploads'] . ( $from_blog > 1 ? '/sites/' . $from_blog : '' ) ,
		        self::paths['uploads'] . '/sites/' . $to_blog )
        );
    }

    /**
     * Remove all sites
     *
     * @return true
     */
    public static function remove_all_sites(){
        return self::clear_dir( self::paths['uploads'] . '/sites', 1 );
    }

    /**
     * Fix file access permission
     *
     * @param $file
     * @return string
     */
    public static function fix_file( $file ){
        if ( !chmod( $file, 0755) ) return $file;
        return '';
    }
}