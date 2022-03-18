<?php

namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Template: settings single site to multisite service
 *
 * @global $options
 */

$cdomain = site_url();

DB::prepare_domain( $cdomain, $path )

?>

<h1 class="screen-reader-text"><?php _e('Transform to multisite', LNG ) ?></h1>

<h2><?php _e( 'General options', LNG ) ?></h2>

<table class="form-table">

    <tbody>

    <tr valign="top">
        <th class="titledesc" scope="row">
            <label><?php _e('Transformation way', LNG) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label><input type="radio" name="complete_copy" value="1" <?php echo ( $options['complete_copy'] ? 'checked' : '' ) ?> />
                    <?php _e( 'Create complete copy for current domain', LNG ) ?>
                </label>
            </p>
            <p>
                <label><input type="radio" name="complete_copy" value="0" <?php echo ( $options['complete_copy'] ? '' : 'checked' ) ?>/>
                    <?php _e( 'Transform only', LNG ) ?>
                </label>
            </p>
            <p>
                <label title="<?php _e( '.htaccess and wp-config.php, copying all files errors will be ignored', LNG ) ?>">
                    <input type="checkbox" value="1" name="ignore_file_errors" <?php echo ( $options['ignore_file_errors'] ? 'checked' : '' ) ?>/>
			        <?php _e( 'Ignore errors on files', LNG ) ?>
                </label>
            </p>
        </td>
    </tr>

    <tr valign="top">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Naming domains', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label><input type="radio" name="subdomain" value="0" <?php echo ( $options['subdomain'] ? '' : 'checked' ) ?>/>
                    <?php _e('Subpath', LNG) ?>
                </label>
            </p>
            <p>
                <label><input type="radio" name="subdomain" value="1" <?php echo ( $options['subdomain'] ? 'checked' : '' ) ?>/>
                    <?php _e('Subdomain', LNG) ?>
                </label>
            </p>
            <p class="hint">
                <?php _e( 'This affects only WP way to add domains and may be changed later using "Multi new Copy" tab', LNG ) ?>
            </p>
        </td>
    </tr>

    </tbody>
</table>

<h2><?php _e( 'Before transformation', LNG ) ?></h2>

<table class="form-table">

    <tbody>

    <tr valign="top">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Clean up', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label>
                    <input type="checkbox" value="1" name="clear_transients" <?php echo ( $options['clear_transients'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Clear all transients', LNG ) ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" value="1" name="clear_cache" <?php echo ( $options['clear_cache'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Clear cache', LNG ) ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" value="1" name="remove_debug" <?php echo ( $options['remove_debug'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Remove WP debug.log file', LNG ) ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" value="1" name="clear_cron" <?php echo ( $options['clear_cron'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Clear cron queue', LNG ) ?>
                </label>
            </p>
        </td>
    </tr>

    </tbody>
</table>

<h2><?php _e( 'After transformation', LNG ) ?></h2>

<table class="form-table">

    <tbody>

    <tr valign="top" class="complete-copy-option">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Clean up Woo', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label>
                    <input type="checkbox" value="1" name="clear_woo" <?php echo ( $options['clear_woo'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Clear all WooCommerce orders on new domains', LNG ) ?>
                </label>
            </p>
        </td>
    </tr>

    <tr valign="top">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Super admins', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label>
                    <input type="radio" value="1" name="add_all_to_super" <?php echo ( $options['add_all_to_super'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Add all current admins to super admins', LNG ) ?>
                </label>
                <br/>
                <label>
                    <input type="radio" value="0" name="add_all_to_super" <?php echo ( $options['add_all_to_super'] ? '' : 'checked' ) ?>/>
                    <?php _e( 'Add only me', LNG ) ?>
                </label>
            </p>
        </td>
    </tr>

    <tr valign="top" class="complete-copy-option">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Users', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label>
                    <input type="radio" value="all" name="populate_level" <?php echo ( $options['populate_level'] === 'all' ? 'checked' : '' ) ?>/>
                    <?php _e( 'Add all current users to new blogs', LNG ) ?>
                </label>
                <br/>
                <label>
                    <input type="radio" value="admins" name="populate_level" <?php echo ( $options['populate_level'] === 'admins' ? 'checked' : '' ) ?>/>
                    <?php _e( 'Add all current admins to new blogs', LNG ) ?>
                </label>
                <br/>
                <label>
                    <input type="radio" value="me" name="populate_level" <?php echo ( $options['populate_level'] === 'me' ? 'checked' : '' ) ?>/>
                    <?php _e( 'Add only me', LNG ) ?>
                </label>
            </p>
        </td>
    </tr>

    <tr valign="top" class="complete-copy-option">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Plugins', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label>
                    <input type="checkbox" value="1" name="network_plugins" <?php echo ( $options['network_plugins'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Enable all currently active plugins in network mode', LNG ) ?>
                </label>
            </p>
        </td>
    </tr>

    </tbody>
</table>

<div class="complete-copy-option">

    <h2><?php _e( 'New domains to add', LNG ) ?></h2>

    <table class="form-table">

        <tbody>

        <tr valign="top">
            <th class="titledesc" scope="row">
                <label><?php _e( 'Current domain', LNG ) ?></label>
            </th>
            <td class="forminp forminp-text">
                <p>
                    <span class="current-domain"><?php echo $cdomain . $path ?></span>
                </p>
            </td>
        </tr>

        <?php include "plugin-settings-domains-add.php" ?>


        </tbody>

    </table>

</div>

<input type="hidden" name="pending_operation" value="sstoms" />