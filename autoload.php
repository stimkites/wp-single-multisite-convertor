<?php

namespace Wetail\SSMS;

/**
 * PSR-4 compliant autoloader
 *
 * @param $path
 */

/**
 * @Stim edition
 *
 * Allows to load all necessary classes in non-"namespace" dependent way
 *
 * uses args:
 *  - dir : initial directory for lookup, by default set to current
 *  - path : relative path to folder containing sub-folders with all classes to load (e.g. "includes")
 *  - prefix : class name prefix to lookup for (e.g. "class")
 *  - separator : symbol separating class prefix from class name, default "-"
 */

if ( class_exists( __NAMESPACE__ . '\__Autoloader' ) ) return __Autoloader::init();

final class __Autoloader{

    /**
     * Initialize autoloader
     *
     * @param array $args
     *
     * @return self
     */
    public static function init( $args = [] ){
        return new __Autoloader( $args );
    }

    /**
     * List of classes to load from
     * @var array
     */
    protected $list = [];

    /**
     * Dynamically assigned args from calling side
     *
     * @var array
     */
    private $args = [];

    /**
     * Default paths and prefix
     */
    const defaults = [
        'prefix'    => 'class',
        'dir'       => __DIR__,
        'path'      => 'includes',
        'separator' => '-'
    ];

    /**
     * __Autoloader constructor.
     * @param array $args
     */
    public function __construct( $args = [] ){

        $this->args = array_merge( self::defaults, $args );
        $mask = rtrim( $this->args['dir'], '/' ) . '/'
                . trim( $this->args['path'], '/' ) . '/'
                . trim( $this->args['prefix'], $this->args['separator'] )
                . $this->args['separator']
                . '*.php';

        $this->list = $this->index( self::scan( $mask ) );

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once \ABSPATH . 'wp-admin/includes/plugin.php';
        }

        spl_autoload_register( [ $this, 'load' ] );

    }

    /**
     * Recursively scan for all classes
     *
     * @param $mask
     * @param int $flags
     * @return array
     */
    private static function scan( $mask, $flags = 0 ){
        $files = glob( $mask, $flags );
        foreach ( glob( dirname( $mask ) . '/*', \GLOB_ONLYDIR | \GLOB_NOSORT ) as $dir ) {
            $files = array_merge( $files, self::scan( $dir . '/' . basename( $mask ), $flags ) );
        }
        return $files;
    }

    /**
     * Make indexed list of class files
     *
     * @param $files
     * @return array
     */
    private function index( $files ){
        $r = [];
        $prefix = trim( $this->args['prefix'], $this->args['separator'] ) . $this->args['separator'];
        foreach( $files as $file ){
            $parts = explode( '/', $file );
            $last_part = end( $parts );
            $index = substr( $last_part, strpos( $last_part, $prefix ) + strlen( $prefix ) );
            $index = substr( $index, 0, strlen( $index ) - 4 );
            $r[ $index ] = $file;
        }
        return $r;
    }

	/**
	 * Length of our namespace
	 *
	 * @var int
	 */
    private static $nsp_length = 0;

	/**
	 * This will prevent looping into our files not from our namespace
	 *
	 * @param string $class_name
	 *
	 * @return string
	 */
    private static function identify( $class_name ){
	    if( ! self::$nsp_length )
	    	self::$nsp_length = strlen( __NAMESPACE__ );
	    return ( __NAMESPACE__ === substr( $class_name, 0, self::$nsp_length ) );
    }

    /**
     * Find the file to load
     *
     * @param string $class_name
     * @return string
     * @throws \Exception
     */
    private function find_file( $class_name ){
    	if( ! self::identify( $class_name ) ) return '';
        $parts = explode( '\\', $class_name );
        $index = strtolower( str_replace( '_', $this->args['separator'],  ltrim( end( $parts ), '_' ) ) );
        if( empty( $this->list[ $index ] ) || !file_exists( $this->list[ $index ] ) )
            throw new \Exception( 'File for class <b>"' . $class_name
                . '"</b> with index <i>"' . $index
                . '"</i> not found!', 0 );

        return $this->list[ $index ];
    }

    /**
     * Load corresponding class
     *
     * @param $class
     */
    public function load( $class ){
        try {
            $file = $this->find_file( $class );
        }catch( \Exception $e ){
            die( __NAMESPACE__ . ' AUTOLOADER EXCEPTION:<br/>' . $e->getMessage() );
        }finally{
            if ( empty( $file ) ) return;
            require_once $file;
        }
    }

}