<?php

namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

if( class_exists( __NAMESPACE__ . '\Rest' ) ) return;

/**
 * Rest extension for testing purposes
 */
final class Rest extends \WP_REST_Controller{

	/**
	 * Rest url prefix
	 */
    const PREFIX = '/sstoms';

	/**
	 * Preventing caching our end points
	 */
    const stamp = '/(?P<timestamp>[\d]+)';

	/**
	 * Rest endpoints
	 */
	const end_points = [
        'stat'          . self::stamp => [ 'GET', [ __CLASS__, 'stat'           ] ],
        'log'           . self::stamp => [ 'GET', [ __CLASS__, 'log'            ] ],
        'check'         . self::stamp => [ 'GET', [ __CLASS__, 'check'          ] ],
        'network'       . self::stamp => [ 'GET', [ __CLASS__, 'check_network'  ] ],
        'single'        . self::stamp => [ 'GET', [ __CLASS__, 'check_single'   ] ]
    ];

	/**
	 * Initialize
	 *
	 * @return Rest
	 */
	public static function init(){
		return new self();
	}

	/**
     * Adds endpoints
     */
    public function __construct(){
        add_action( 'rest_api_init', [ __CLASS__, 'register_all_routes_no_auth' ] );
    }


	/**
	 * Make proper Rest URL
	 *
	 * @param string $end_point
	 *
	 * @return string
	 */
    public static function urls( $end_point = 'check' ){
    	if( empty( self::end_points[ $end_point . self::stamp ] ) ) $end_point = 'check';
	    return rtrim( get_rest_url(), '/' ) . self::PREFIX . '/' . trim( $end_point, '/' ) . '/' . time();
    }


    /**
     * Register routes without authentication
     */
    public static function register_all_routes_no_auth() {
        $namespace = self::PREFIX;
        foreach( self::end_points as $end_point => $calldata )
            register_rest_route( $namespace, '/' . $end_point, array(
                    array(
                        'methods'             => $calldata[0],
                        'callback'            => $calldata[1]
                    )
                )
            );
    }


    /**
	 * Echo current log
	 *
	 * @return \WP_REST_Response
	 */
    public static function log(){
    	return new \WP_REST_Response( Logger::read(), 200 );
    }

	/**
	 * Echo current stat
	 *
	 * @return \WP_REST_Response
	 */
    public static function stat(){
    	return new \WP_REST_Response( Logger::read_stat(), 200 );
    }

    /**
     * Check end point to work
     *
     * @return \WP_REST_Response
     */
    public static function check(){
        return new \WP_REST_Response( 'OK', 200 );
    }

    /**
     * Check network mode
     *
     * @return \WP_REST_Response
     */
    public static function check_network(){
        if( defined( 'MULTISITE' ) && MULTISITE )
            return new \WP_REST_Response( time(), 200 );
        return new \WP_REST_Response( 0, 404 );
    }

    /**
     * Check single mode
     *
     * @return \WP_REST_Response
     */
    public static function check_single(){
        if( ! defined( 'MULTISITE' ) || ! MULTISITE )
            return new \WP_REST_Response( time(), 200 );
        return new \WP_REST_Response( 0, 404 );
    }

}