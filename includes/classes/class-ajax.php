<?php

namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Class _Ajax
 *
 * Handling ajax requests
 */
if( class_exists( __NAMESPACE__ . '\Ajax' ) ) return;

final class Ajax {

    /**
     * Initialization
     */
    public static function init(){
        add_action( 'wp_ajax_' . AJAX_H, [ __CLASS__, 'handle_requests' ] );
    }

	/**
	 * Authenticate
	 */
    private static function auth(){
        if( !wp_verify_nonce( $_POST['nonce'], LNG ) )
            self::response( [ 'error' => __('Nonce check failure', LNG) ] );
    }

	/**
	 * Send JSON response
	 *
	 * @param $data
	 */
    private static function response( $data ){
        die( json_encode( $data ) );
    }

	/**
	 * Validate domains to add
	 *
	 * @param $domains
	 */
    private static function validate_domains( $domains ){
    	if( empty( $domains ) )
    		self::response( [ 'error'=> __( 'Domains list cannot be empty!', LNG ) ] );
    	$current_domains = DB::get_blogs();
	    $cur_domains = [];
    	if( empty( $current_domains ) )
    		$cur_domains = [ trim( preg_replace( [ '/http:\/\//', '/https:\/\//', '/www\./' ], [ '', '', '' ], site_url() ), '/' ) ];
    	else
    		foreach ($current_domains as $domain)
    		    $cur_domains[] = rtrim( $domain['domain'] . $domain['path'], '/' );
    	foreach( $domains as $domain ) {
		    preg_match( '/^[a-zA-Z0-9-._\/]*/', $domain, $match );
		    if ( $match[0] !== $domain || in_array( rtrim( $domain , '/' ), $cur_domains ) ) {
			    self::response( [ 'error' => __( 'Invalid domain', LNG ) . ': ' . $domain ] );
		    }
	    }
    }

    /**
     * Handle all incoming AJAX requests
     */
    public static function handle_requests(){

        //check nonce
        self::auth();

        //switch between actions
        switch($_POST['do']){

	        case 'save_options':
	        	if( empty( $_POST['data'] ) ) self::response( [ 'error' => __( 'No data to save', LNG ) ] );
	        	$options = [];
	        	foreach( $_POST['data'] as $data )
	        		if( 'domains[]' === $data['name'] )
	        			$options['domains'][] = $data['value'];
	        	    else
	        		    $options[ $data['name'] ] = $data['value'];
	            if( ( $options['pending_operation'] === 'sstoms' && $options['complete_copy'] )
	                || $options['pending_operation'] === 'mscopy' )
	            	    self::validate_domains( $options['domains'] );
	            if( defined( 'MULTISITE' ) && MULTISITE ){
	            	Filer::set_ms_mode( $options['cp_subdomain'] );
	            }
	        	self::response( [
	        		'error' => (
	        			DB::set_options( $options ) ? '' : __( 'Error on DB request', LNG )
			        )
		        ] );
            break;

            case 'launch':
	            //Filer::make_copy( 0, 2 );
                //return self::response( [ 'error'=>'', 'result'=> 'tools.php?page=' . Admin::setting ] ); //testing
                //return self::response( [ 'error'=>'', 'result'=>Runner::fake() ] ); //testing
				if( ! self::enough_exec_time() )
					self::response( [ 'error'=>__( '[FAIL] Execution time on the server is less than 10 minutes! Cannot proceed...', LNG ) ] );
                if( ! Runner::launch() )
                    self::response( [ 'error'=>__( 'Error in processing operation request! Refer to log!', LNG ) ] );
                else
                    self::response( [ 'error'=>'', 'result'=>admin_url() ] );
            break;

        }

        self::response(['error' => sprintf( __( 'No ajax action found for the request "%s"', LNG ), $_POST['do'] ) ]);

    }
	
	/**
	 * Checks if at least 10 minutes execution time is allowed
	 *
	 * @return bool
	 */
    private static function enough_exec_time(){
    	return ( 600 < ini_get('max_execution_time') || set_time_limit( 600 ) );
    }

}