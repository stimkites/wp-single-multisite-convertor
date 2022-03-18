<?php

namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

if( class_exists( __NAMESPACE__ . '\Runner' ) ) return;

/**
 * Run scripts with passed options
 */
final class Runner {

    /**
     * Fake processing for testing purposes
     */
    public static function fake(){
        Logger::start_stat( 10 );
        for( $i = 1; $i <= 10; $i++ ){
            Logger::write_stat( $i, time() );
            sleep( rand( 1,3 ) );
        }
    }

	/**
	 * Convert from single site to multisite using options from DB
	 *
	 * @return bool
	 */
	public static function from_single_to_multi(){

		$rez = true;

        $opts = DB::get_options();

        $dms = count( $opts['domains'] );

		Logger::start_stat( 7 + (
		    $opts['complete_copy']
                ? $opts['clear_woo']
                    ? $dms * 6
                    : $dms * 5
                : 0 )
        );

		if( $opts['maintenance_mode'] ) {
			if( Filer::maintenance( true ) )
				Logger::write_stat( 0, __( 'Maintenance mode enabled', LNG) );
			else{
				Logger::write_stat( 0, '[ERROR]' . __( 'Could not enable maintenance mode!', LNG) );
				return false;
			}
		}

		$step = 1;

		Logger::write_stat( $step, __( 'Checking files access...', LNG) );
		$fchk = Filer::check_files();
		while( ! empty( $fchk ) ){
			Logger::write_stat( $step, '[WARNING] ' . sprintf(
				__('Write access to "%s" is denied. Trying to fix...', LNG ),
				$fchk
				)
			);
            $fchk = Filer::fix_file( $fchk );
            if( !empty( $fchk ) ) {
            	if( ! $opts['ignore_file_errors'] ) {
		            Logger::write_stat( $step, '[FATAL_ERROR] ' . sprintf(
				            __( 'Write access to "%s" is denied. Cannot fix it. Report this to your system administrator.', LNG ),
				            $fchk
			            )
		            );

		            return false;
	            }else{
		            Logger::write_stat( $step, '[WARNING] ' . sprintf(
				            __( 'Write access to "%s" is denied. Cannot fix it. Ignored...', LNG ),
				            $fchk
			            )
		            );
	            }
            }
            $fchk = '';
            if( ! $opts['ignore_file_errors'] ) $fchk = Filer::check_files();
		}

		$step++;

		Logger::write_stat( $step, __( 'Checking disk space...', LNG ) );
		$insufficient = Filer::check_disk();
		if( $insufficient ){
			Logger::write_stat( $step, '[ERROR] ' . sprintf(
					__('There are %sMB of disk space insufficient for operations. Clear your debug files, log files and '
					   .'local backups to free the space or increase quota', LNG ),
					$insufficient
				)
			);
			Filer::maintenance( false );
			return false;
		}

        $step++;

		Logger::write_stat( $step, __( 'Creating multi-site data tables...', LNG ) );
		if( ! DB::create_blog_tables() ){
			Logger::write_stat( $step, '[ERROR] ' . __( 'DB creating blog tables generic failure', LNG ) );
			return false;
		}

		$step++;

		$domain = site_url();

        DB::prepare_domain( $domain, $path );

        Logger::write_stat( $step, __( 'Setting multi-site mode in wp-config.php and .htaccess files...', LNG ) );
        if( ! Filer::enable_multisite( $domain, $path, $opts['subdomain'] ) ){
	        if( ! $opts['ignore_file_errors'] ) {
		        Logger::write_stat( $step, '[FATAL_ERROR] ' . __( 'Could not write to .htaccess and wp-config.php files!', LNG ) . ' ' . __( 'Reverting changes...', LNG ) );
		        self::DB_revert_to_ss( $step );
		        return false;
	        }else{
		        Logger::write_stat( $step, '[WARNING] ' . __( 'Could not change .htaccess and wp-config.php!', LNG ) . ' ' . __( 'Ignored...', LNG ) );
		        sleep(3);
		        $rez = false;
	        }
        }

        $step++;

        Logger::write_stat( $step, __( 'Creating multi-site super admins...', LNG ) );
        if( !DB::super_users( 0, $opts['add_all_to_super'] ) ){
            Logger::write_stat( $step, '[ERROR] ' . __( 'Could not process new blog options!', LNG ) . ' ' . __( 'Reverting changes...', LNG ) );
            self::DB_revert_to_ss( $step );
            return false;
        }

        $step++;

        if( $opts['complete_copy'] )
            foreach( $opts['domains'] as $domain ){
            
                DB::prepare_domain( $domain, $path );

                Logger::write_stat( $step, __( 'Inserting new blog...', LNG ) );
                $new_blog_id = DB::insert_blog( $domain, $path );
                if( ! $new_blog_id ){
                    Logger::write_stat( $step, '[ERROR] ' . __( 'Could not create new blog.', LNG ) . ' ' . __( 'Reverting changes...', LNG ) );
                    self::DB_revert_to_ss( $step );
                    return false;
                }

                $step++;

                Logger::write_stat( $step, __( 'Copying DB tables. This may take a while...', LNG ) );
                $r = DB::copy_tables( 0, $new_blog_id );
                if( ! empty( $r ) ){
                    Logger::write_stat( $step, '[SQL_ERROR] ' . $r . ' ' . __( 'Reverting changes...', LNG )  );
                    self::DB_revert_to_ss( $step );
                    return false;
                }

                $step++;

                Logger::write_stat( $step, __( 'Processing options for new blog...', LNG ) );
                if( ! DB::process_options( $new_blog_id, $domain, $path ) ){
                    Logger::write_stat( $step, '[ERROR] ' . __( 'Could not process new blog options!', LNG ) . ' ' . __( 'Reverting changes...', LNG ) );
                    self::DB_revert_to_ss( $step );
                    return false;
                }

                $step++;

                Logger::write_stat( $step, __( 'Creating new blog admins and users...', LNG ) );
                if( ! DB::populate_users( 0, $new_blog_id, $opts['populate_level'] ) ) {
                    Logger::write_stat( $step, '[WARNING] ' . __( 'Could not process all users. Proceeding...', LNG ) );
	                sleep(3);
	                $rez = false;
                }

                $step++;

                if( $opts['clear_woo'] ) {
                    Logger::write_stat( $step, __( 'Cleaning WooCommerce Orders for new blog...', LNG ) );
                    if( !DB::clean_up_woo_orders( $new_blog_id ) ) {
                        Logger::write_stat( $step, '[WARNING] ' . __( 'Could not clean all WooCommerce orders. Proceeding...', LNG ) );
                        sleep(3);
	                    $rez = false;
                    }
                    $step++;
                }


                Logger::write_stat( $step, __( 'Copying files...', LNG ) );
                if( ! Filer::make_copy( 0, $new_blog_id ) ){
                	if( !$opts['ignore_file_errors'] ) {
		                Logger::write_stat( $step, '[FATAL_ERROR] ' . __( 'Could not copy all necessary files!', LNG ) . ' ' . __( 'Reverting changes...', LNG ) );
		                self::DB_revert_to_ss( $step );

		                return false;
	                }else{
		                $rez = false;
		                Logger::write_stat( $step, '[WARNING] ' . __( 'Could not copy all necessary files!', LNG ) . ' ' . __( 'Ignored...', LNG ) );
		                sleep( 3 );
	                }
                }

                $step++;

            }



		if( $opts['maintenance_mode'] ) {
			if( Filer::maintenance( false ) )
				Logger::write_stat( $step, __( 'Maintenance mode disabled', LNG) );
			else{
				Logger::write_stat( $step, '[WARNING]' . __( 'Could not disable maintenance mode! Please report this to your system administrator!', LNG) );
				sleep(3);
				$rez = false;
			}
		}

		$step++;

		Logger::write_stat( $step, __( 'Testing network mode...', LNG ) );
        if( ! self::make_test( Rest::urls( 'network' ) ) ){
            Logger::write_stat( $step, '[WARNING] ' . __( 'Test for network mode failed! Please, contact system administrator if anything seems not ok!', LNG ) );
            $rez = false;
        }

		if( $rez )
			Logger::write_stat( $step, __( 'Operation completed successfully! Redirecting to admin...', LNG ) );
		else
			Logger::write_stat( $step, __( 'Operation completed successfully, but some warnings there, please, check.', LNG ) );

        DB::check_ms_mode( true );

		return $rez;
	}


	/**
	 * Create a new copy for multi-site
	 *
	 * @return bool
	 */
	public static function copy_on_multi(){

		$rez = true;

		$opts = DB::get_options();

		$dms = count( $opts['domains'] );

		Logger::start_stat( 2 + ( $opts['clear_woo'] ? $dms * 6 : $dms * 5 ) );

		if( empty( $opts['domains'] ) ){
			Logger::write_stat( 0, __( 'To make a copy new domain name is required to be specified', LNG) );
			return false;
		}

		if( empty( $opts['cp_primary_domain'] ) || $opts['cp_primary_domain'] < 0 ){
			Logger::write_stat( 0, __( 'Primary domain to copy from is not defined!', LNG) );
			return false;
		}

		if( $opts['maintenance_mode'] ) {
			if( Filer::maintenance( true ) )
				Logger::write_stat( 0, __( 'Maintenance mode enabled', LNG) );
			else{
				Logger::write_stat( 0, '[ERROR]' . __( 'Could not enable maintenance mode!', LNG) );
				return false;
			}
		}

		$step = 1;

		Logger::write_stat( $step, __( 'Checking disk space...', LNG ) );
		$insufficient = Filer::check_disk( ( empty( $opts['cp_primary_domain'] ) ? 0 : $opts['cp_primary_domain'] ) );
		if( $insufficient ){
			Logger::write_stat( $step, '[ERROR] ' . sprintf(
					__('There are %sMB of disk space insufficient for operations. Clear your debug files, log files and '
					   .'local backups to free the space or increase quota', LNG ),
					$insufficient
				)
			);
			Filer::maintenance( false );
			return false;
		}

		$step++;

		foreach( $opts['domains'] as $domain ){

			DB::prepare_domain( $domain, $path );

			Logger::write_stat( $step, __( 'Inserting new blog...', LNG ) );
			$new_blog_id = DB::insert_blog( $domain, $path );
			if( !$new_blog_id ){
				Logger::write_stat( $step, '[ERROR] ' . __( 'Could not create new blog.', LNG ) );
				Filer::maintenance( false );
				return false;
			}

			$step++;

			Logger::write_stat( $step, __( 'Copying DB tables. This may take a while...', LNG ) );
			$r = DB::copy_tables( $opts['cp_primary_domain'], $new_blog_id );
			if( ! empty( $r ) ){
				Logger::write_stat( $step, '[SQL_ERROR] ' . $r  );
				DB::drop_blog( $new_blog_id );
				Filer::maintenance( false );
				return false;
			}

			$step++;

			Logger::write_stat( $step, __( 'Processing options for new blog...', LNG ) );
			if( ! DB::process_options( $new_blog_id, $domain, $path ) ){
				Logger::write_stat( $step, '[WARNING] ' . __( 'Could not process new blog options! Proceeding...', LNG ) );
				$rez = false;
				sleep(3);
			}

			$step++;

			Logger::write_stat( $step, __( 'Creating new blog admins and users...', LNG ) );
			if( ! DB::populate_users( 0, $new_blog_id, $opts['populate_level'] ) ) {
				Logger::write_stat( $step, '[WARNING] ' . __( 'Could not process all users. Proceeding...', LNG ) );
				$rez = false;
				sleep(3);
			}

			$step++;

			if( $opts['cp_clear_woo'] ) {
				Logger::write_stat( $step, __( 'Cleaning WooCommerce Orders for new blog...', LNG ) );
				if( !DB::clean_up_woo_orders( $new_blog_id ) ) {
					Logger::write_stat( $step, '[WARNING] ' . __( 'Could not clean all WooCommerce orders. Proceeding...', LNG ) );
					$rez = false;
					sleep(3);
				}
				$step++;
			}


			Logger::write_stat( $step, __( 'Copying files...', LNG ) );
			if( ! Filer::make_copy( 0, $new_blog_id ) ){
				if( !$opts['cp_ignore_file_errors'] ) {
					Logger::write_stat( $step, '[FATAL_ERROR] ' . __( 'Could not copy all necessary files!', LNG ) . ' ' . __( 'Reverting changes...', LNG ) );
					DB::drop_blog( $new_blog_id );
					Filer::maintenance( false );
					return false;
				}else{
					$rez = false;
					Logger::write_stat( $step, '[WARNING] ' . __( 'Could not copy all necessary files!', LNG ) . ' ' . __( 'Ignored...', LNG ) );
					sleep(3);
				}
			}

			$step++;

		}

		if( $opts['maintenance_mode'] ) {
			if( Filer::maintenance( false ) )
				Logger::write_stat( $step, __( 'Maintenance mode disabled', LNG) );
			else{
				Logger::write_stat( $step, '[WARNING]' . __( 'Could not disable maintenance mode! Please report this to your system administrator!', LNG) );
				sleep(3);
				$rez = false;
			}
		}

		if( $rez )
			Logger::write_stat( $step, __( 'Operation completed successfully! Redirecting to admin...', LNG ) );
		else
			Logger::write_stat( $step, __( 'Operation completed successfully, but some warnings there, please, check.', LNG ) );

		return $rez;

	}

	/**
	 * Convert from single site to multisite using options from DB
	 *
	 * @return bool
	 */
	public static function from_multi_to_single(){

		$rez = true;

		$opts = DB::get_options();

		Logger::start_stat( 6 );

		if( empty( $opts['s_primary_domain'] ) || $opts['s_primary_domain'] < 0 ){
			Logger::write_stat( 0, __( 'Primary domain to set is not defined!', LNG) );
			return false;
		}

		if( $opts['maintenance_mode'] ) {
			if( Filer::maintenance( true ) )
				Logger::write_stat( 0, __( 'Maintenance mode enabled', LNG) );
			else{
				Logger::write_stat( 0, '[ERROR]' . __( 'Could not enable maintenance mode!', LNG) );
				return false;
			}
		}

		$step = 1;

		Logger::write_stat( $step, __( 'Checking files access...', LNG) );
		$fchk = Filer::check_files();
		while( !empty( $fchk ) ){
			Logger::write_stat( $step, '[WARNING] ' . sprintf(
					__('Write access to "%s" is denied. Trying to fix...', LNG ),
					$fchk
				)
			);
			$fchk = Filer::fix_file( $fchk );
			if( !empty( $fchk ) ) {
				if( !$opts['ignore_file_errors'] ) {
					Logger::write_stat( $step, '[FATAL_ERROR] ' . sprintf(
							__( 'Write access to "%s" is denied. Cannot fix it. Report this to your system administrator.', LNG ),
							$fchk
						)
					);

					return false;
				}else{
					Logger::write_stat( $step, '[WARNING] ' . sprintf(
							__( 'Write access to "%s" is denied. Cannot fix it. Ignored...', LNG ),
							$fchk
						)
					);
				}
			}
			$fchk = '';
			if( !$opts['ignore_file_errors'] ) $fchk = Filer::check_files();
		}

		$step++;

		Logger::write_stat( $step, __( 'Deactivating multi-site mode in wp-config.php and .htaccess files...', LNG ) );
		if( !Filer::disable_multisite() ){
			if( !$opts['s_ignore_file_errors'] ) {
				Logger::write_stat( $step, '[FATAL_ERROR] ' . __( 'Could not write to .htaccess and wp-config.php files!', LNG ) . ' ' . __( 'Reverting changes...', LNG ) );
				self::files_revert( $step );
				return false;
			}else{
				Logger::write_stat( $step, '[WARNING] ' . __( 'Could not change .htaccess and wp-config.php!', LNG ) . ' ' . __( 'Ignored...', LNG ) );
				sleep(3);
				$rez = false;
			}
		}

		$step++;

		Logger::write_stat( $step, __( 'Transforming blog files into single site...', LNG ) );
		if( ! Filer::blog_to_single( $opts['s_primary_domain'] ) ){
			if( ! $opts['s_ignore_file_errors'] ) {
				Logger::write_stat( $step, '[FATAL_ERROR] ' . __( 'Could not transform files into single site mode!', LNG ) . ' ' . __( 'Reverting changes...', LNG ) );
				self::files_revert( $step );
				return false;
			}else{
				Logger::write_stat( $step, '[WARNING] ' . __( 'Could not transform files into single site mode!', LNG ) . ' ' . __( 'Ignored...', LNG ) );
				sleep(3);
				$rez = false;
			}
		}

		$step++;

		Logger::write_stat( $step, __( 'Transforming DB into single site...', LNG ) );
		if( !DB::remove_multisite( $opts['s_primary_domain'], $opts['s_erase_all'] ) ){
			Logger::write_stat( $step, '[ERROR] ' . __( 'Could not process database requests!', LNG ) . ' ' . __( 'Reverting changes...', LNG ) );
			self::files_revert( $step );
			return false;
		}

		$step++;

		if( $opts['maintenance_mode'] ) {
			if( Filer::maintenance( false ) )
				Logger::write_stat( $step, __( 'Maintenance mode disabled', LNG) );
			else{
				Logger::write_stat( $step, '[WARNING]' . __( 'Could not disable maintenance mode! Please report this to your system administrator!', LNG) );
				sleep(3);
				$rez = false;
			}
		}

		$step++;

		Logger::write_stat( $step, __( 'Testing single site mode...', LNG ) );
		if( ! self::make_test( Rest::urls( 'single' ) ) ){
			Logger::write_stat( $step, '[WARNING] ' . __( 'Test for single site mode failed! Please, contact system administrator if anything seems not ok!', LNG ) );
			$rez = false;
		}

		if( $rez )
			Logger::write_stat( $step, __( 'Operation completed successfully! Redirecting to admin...', LNG ) );
		else
			Logger::write_stat( $step, __( 'Operation completed successfully, but some warnings there, please, check.', LNG ) );

		return $rez;
	}

	/**
	 * Revert changes on files only
	 *
	 * @param $step
	 */
	public static function files_revert( $step ){
		if( Filer::restore_core() && Filer::maintenance( false ) )
			Logger::write_stat( $step, '[ERROR] ' . __( 'Operation failed, but changes were reverted successfully', LNG ) );
		else
			Logger::write_stat( $step, '[ERROR] ' . __( 'Backup restore is strongly advised!', LNG ) );
	}

    /**
     * Launch pending operation
     *
     * @return bool
     */
	public static function launch(){
	    switch ( DB::get_option( 'pending_operation' ) ){
            case 'sstoms' : return self::from_single_to_multi();
            case 'mscopy' : return self::copy_on_multi();
            case 'mstoss' : return self::from_multi_to_single();
        }
        return false;
    }

    /**
     * Make revert changes on DB
     *
     * @param $step
     */
	private static function DB_revert_to_ss( $step ){
		if( DB::remove_multisite() && Filer::restore_core() && Filer::maintenance( false ) )
            Logger::write_stat( $step, '[ERROR] ' . __( 'Operation failed, but changes were reverted successfully', LNG ) );
		else
            Logger::write_stat( $step, '[ERROR] ' . __( 'Backup restore is strongly advised!', LNG ) );
	}

    /**
     * Test mode
     *
     * @param $url
     * @return bool
     */
	private static function make_test( $url ){
		Logger::write( 'Testing URL: ' . $url );
        $r = file_get_contents( $url, false );
        $response_header = ( empty( $http_response_header[0] ) ? '' : $http_response_header[0] );
		Logger::write( 'HEADER:' . $response_header . ' RESPONSE: ' . $r );
        if( empty( $response_header ) ) return false;
        return ( strpos( $response_header, '200' ) !== false );
	}

}