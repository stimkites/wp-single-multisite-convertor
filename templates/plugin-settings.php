<?php

namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Template: global plugin settings
 */

$options = DB::get_options();

$multi = defined( 'MULTISITE' ) && MULTISITE;

$tab = ( isset( $_REQUEST['tab'] ) ? $_REQUEST['tab'] : $multi ? 'mscopy' : 'sstoms' );

//prevent hackathon
if( $tab !== 'sstoms' && !$multi ) $tab = 'sstoms';

$domains = [];

if( $multi )
    $domains = DB::get_blogs();

?>

<p class="clear"></p>

<div class="wrap wt-ssms-wrap">

    <hr class="wp-header-end">

    <img src="<?php echo URL ?>/assets/img/network_ss_to_ms_logo.png" class="logo" />

    <h1 class="inline-header">
        <?php _e( 'WordPress Single Site to Multi Site converter',LNG); ?>
    </h1>

    <p class="clear"></p>

    <nav class="nav-tab-wrapper">
        <span class="nav-tab <?php echo ( $tab === 'sstoms' ? 'nav-tab-active' : '' ) . ( $multi ? ' disabled' : '' ) ?>"
            <?php echo ( $multi ? 'title="' . __( 'Available in single site mode only', LNG ) .'"' : '' ) ?>
            data-tab="sstoms">
            <?php _e( 'Single to Multi', LNG ) ?>
        </span>
        <span class="nav-tab <?php echo ( $tab === 'mscopy' ? 'nav-tab-active' : '' ) . ( $multi ? '' : ' disabled' ) ?>"
	        <?php echo ( !$multi ? 'title="' . __( 'Available in multi-site mode only', LNG ) .'"' : '' ) ?>
              data-tab="mscopy">
            <?php _e( 'Multi new Copy', LNG ) ?>
        </span>
        <span class="nav-tab <?php echo ( $tab === 'mstoss' ? 'nav-tab-active' : '' ) . ( $multi ? '' : ' disabled' ) ?>"
	        <?php echo ( !$multi ? 'title="' . __( 'Available in multi-site mode only', LNG ) .'"' : '' ) ?>
              data-tab="mstoss">
            <?php _e( 'Multi to Single', LNG ) ?>
        </span>
    </nav>

    <p class="clear"></p>

    <form name="mainform" method="post" action=" " enctype="multipart/form-data">

        <?php if(!$multi) :?>
        <section class="settings-section<?php echo ( $tab === 'sstoms' ? ' active' : '' )?>" id="wttab-sstoms">
            <?php include "plugin-settings-sstoms.php" ?>
        </section>
        <?php endif; ?>

        <?php if($multi) : ?>
        <section class="settings-section<?php echo ( $tab === 'mscopy' ? ' active' : '' )?>" id="wttab-mscopy">
            <?php include "plugin-settings-mscopy.php" ?>
        </section>

        <section class="settings-section<?php echo ( $tab === 'mstoss' ? ' active' : '' )?>" id="wttab-mstoss">
            <?php include "plugin-settings-mstoss.php" ?>
        </section>
        <?php endif; ?>

        <hr/>

        <p><label><input type="checkbox" name="maintenance_mode"
                         value="1" <?php echo ( empty( $options['maintenance_mode'] ) ? '' : 'checked' ) ?>/>
                <?php _e( 'Enable maintenance mode during operation', LNG ) ?>.
            </label>
            (<span class="hint"><?php _e( 'Use this to prevent visitors from viewing errors while accessing the pages '
                                        .'during operations to database' , LNG ) ?></span>)
        </p>

        <p>
            <button class="button button-primary" id="save_n_go"
                    data-nogo="<?php _e( 'Save and go', LNG ) ?>"
                    data-go="<?php _e( 'Launch', LNG) ?>"><?php _e( 'Save and go', LNG ) ?></button>
            <button class="button button-secondary" id="save_only"><?php _e( 'Save only', LNG ) ?></button>
            <span class="icon icon-cross"></span>
            <span class="icon icon-tick"></span>
            <span id="save_result"></span>
        </p>

        <div id="warning">
            <span class="warning-logo"></span>
		    <?php _e( 'Warning! The steps that are going to be done completely rely on a "MAX_EXECUTION_TIME", if you expect this instance to be heavy, better do the operations manually! Also the changes you are about to make will take a while therefore it is better to choose the most downtime for this operation.', LNG ) ?>
        </div>

    </form>

    <div class="clear"></div>

</div>