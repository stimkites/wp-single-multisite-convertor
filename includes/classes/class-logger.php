<?php

namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

if( class_exists( __NAMESPACE__ . '\Logger' ) ) return;

/**
 * Basic Log writer
 */
final class Logger {

    /**
     * Path and url to current log files
     */
    const LOG_PATH   = PATH . '/logs/';
    const LOG_URL    = URL  . '/logs/';

    /**
     * Log file maximum size (3 Mb)
     */
    const MAXSIZE = 3145728;

    /**
     * Log file name, url and log time
     */ 
    public static 
	    $file_name  = '',
	    $time       = '',
		$url        = '';

	/**
	 * Status log filename and url
	 */
    const
	    stat     = self::LOG_PATH . 'stat.html',
		stat_url = self::LOG_URL  . 'stat.html';

    /**
     * Amount of log files kept on the server (amount of days)
     */
    const log_days = 7;

    /**
     * Initialization
     *
     * Making log file name and checking if file size exceeds the maximum size
     *
     * @param string $prefix
     */
    public static function init( $prefix = '_log' ){
    	$prefix = ( $prefix ? '_' . trim( $prefix, '_' ) : '' );
        self::check_logs_num( $prefix );
	    $add_suffix = '_';
        self::$file_name = self::LOG_PATH . strftime( "%d_%m_%Y" ) . $prefix . $add_suffix . '.txt';
        while( file_exists( self::$file_name )
                    && ( (int)filesize( self::$file_name ) >= (int)self::MAXSIZE ) )
	                    (
	                    	( $add_suffix .= $add_suffix )
	                        &&
	                        ( self::$file_name = self::LOG_PATH . strftime("%d-%m-%Y") . $prefix . $add_suffix . '.txt' )
	                    );
	    self::$time = strftime( "%d_%m_%Y %H:%M:%S" );
	    self::$url = self::LOG_URL . self::$file_name;
    }

	/**
	 * Start operation in stat file
	 *
	 * @param $total_steps_expected
	 */
	public static function start_stat( $total_steps_expected ){
		file_put_contents(
			self::stat,
			json_encode( [
				'total'     => $total_steps_expected,
				'current'   => __( 'Initializing...', LNG ),
				'errors'    => [],
				'step'      => 1
			] )
		);
		flush();
	}

	/**
	 * Write status data
	 *
	 * @param int $step
	 * @param string $msg
	 * @param bool $sleep
	 *
	 * @return string
	 */
	public static function write_stat( $step = 0, $msg, $sleep = true ){
		$stat = json_decode( @file_get_contents( self::stat ), true );
		if( ! $stat ) return '';
		if( ! $step )
			$step = $stat['step'];
		if( empty( $stat['log'] ) )
		    $stat['log'] = [];
		@file_put_contents(
			self::stat,
			json_encode([
				'total'     => $stat['total'],
				'log'       => array_merge( [ $msg ], $stat['log'] ),
				'current'   => $msg,
				'step'      => $step
			])
		);
		flush();
		if( $sleep ) sleep( 1 );
		return self::write( $msg );
	}

	/**
	 * Read current status log
	 *
	 * @return bool|string
	 */
	public static function read_stat(){
		return @file_get_contents( self::stat );
	}

    /**
     * Prevent from trashing the server with too many log files
     *
     * @param string $prefix
     */
    private static function check_logs_num( $prefix = '' ){
        $mask = self::LOG_PATH . '*' . $prefix . '*.txt';
        $all_files = [];
        foreach ( glob( $mask, 0 ) as $file )
        	$all_files[ (int)strtotime( "midnight", filemtime( $file ) ) ] = $file;
        ksort( $all_files );
        $count = count( $all_files ) - self::log_days;
        while( $count-- > 0 )
        	if( $first = array_shift( $all_files ) )
        		unlink( $first );
    }

	/**
	 * Defines if the message is critical for duplicating it to Woo logs
	 *
	 * @param $error
	 *
	 * @return bool
	 */
    private static function is_critical( $error ) {
    	foreach( [ 'error', 'ERROR', 'EXCEPTION' ] as $critical )
    	    if( false !== strpos( $error, $critical ) ) return true;
    	return false;
    }

    /**
     * Writer
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public static function write( $data ){
	    $sdata = print_r( $data, true );
    	if( defined( 'UNITTESTS' ) && UNITTESTS ) echo $sdata . PHP_EOL;
        @file_put_contents(
        	self::$file_name,
	        '[' . self::$time .
                ' MEM: ' . round( memory_get_usage( true ) / 1024 / 1024 ) .' Mb ] ' .
	            $sdata . "\r\n\r\n",
	        FILE_APPEND
        );
        if( function_exists( 'wc_get_logger' ) && self::is_critical( $sdata ) ) {
	        $logger = wc_get_logger();
	        $context = array( 'source' => 'single_multi' );
	        $logger->debug( $data, $context );
        }
        return $data;
    }

	/**
	 * Read current log
	 *
	 * @return array
	 */
	public static function read(){
		$lines = 0;
		$r = [];
		$handle = fopen( self::$file_name, "r" );
		if ( ! $handle ) return [ '[ERROR] Log file could not be found!' ];
		while ( ( $line = fgets( $handle ) ) !== false )
			if( ! empty( $line ) && $line != "\r\n" )
				$r[ ++$lines ] = $line;
		fclose($handle);
		return $r;
	}

    /**
     * Clean
     */
    public static function clear_log(){
        unlink( self::$file_name );
    }

	/**
	 * Check access to logs directory
	 *
	 * @return bool
	 */
    public static function check_access(){
    	if( is_writeable( self::LOG_PATH ) ) return true;
    	return chmod( self::LOG_PATH, 0777 );
    }

}