<?php

namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Template: settings for creating a complete copy for new blog service
 *
 * @global $options
 * @global $domains
 */

$cdomain = site_url();

DB::prepare_domain( $cdomain, $path );

?>

<h1 class="screen-reader-text"><?php _e('Copy on multisite', LNG ) ?></h1>

<h2><?php _e( 'General options', LNG ) ?></h2>

<table class="form-table">

    <tbody>

    <tr valign="top">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Copy from', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p><?php
                    echo sprintf(
                        __( 'Note: to create new empty (blank) blog use %s', LNG ),
                        '<i><a href="/wp-admin/network/site-new.php" target="_self" title="' . __( 'Create new blank blog', LNG) . '">'.
                        __( 'regular WordPress service here', LNG ) .
                        '</a></i>'
                        );
                ?>
            </p>
            <p>
                <select name="cp_primary_domain" class="enhanced-select" style="width: 60%">
                    <option disabled <?php echo ( $options['cp_primary_domain'] ? '' : 'selected' ) ?> value ="-1">...</option>
                    <?php
                    if( !empty( $domains ) )
                        foreach ( $domains as $domain )
                            echo '<option value="' . $domain['blog_id'] . '" '
                                 . ( $options['cp_primary_domain'] === $domain['blog_id'] ? 'selected' :'' )
                                 .'>[' . $domain['blog_id'] . '] ' . $domain['domain'] . $domain['path']
                                 . ' (' . ( $domain['public'] ? __( 'active', LNG ) : __( 'inactive', LNG ) ) .')</option>';
                    ?>
                </select>
            </p>
        </td>
    </tr>

    <tr valign="top">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Naming domains', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label><input type="radio" name="cp_subdomain" value="0" <?php echo ( $options['cp_subdomain'] ? '' : 'checked' ) ?>/>
                    <?php _e('Subpath', LNG) ?>
                </label>
            </p>
            <p>
                <label><input type="radio" name="cp_subdomain" value="1" <?php echo ( $options['cp_subdomain'] ? 'checked' : '' ) ?>/>
                    <?php _e('Subdomain', LNG) ?>
                </label>
            </p>
            <p class="hint"><?php
		        _e(  'If needed it is possible to change preferred domain naming method (subdomain or subpath)'
		             .' here - simply save options without launching the copy process', LNG )
		        ?>
            </p>
        </td>
    </tr>

    <tr valign="top">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Files', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label title="<?php _e( '.htaccess and wp-config.php, copying all files errors will be ignored', LNG ) ?>">
                    <input type="checkbox" value="1" name="cp_ignore_file_errors" <?php echo ( $options['cp_ignore_file_errors'] ? 'checked' : '' ) ?>/>
			        <?php _e( 'Ignore errors on files', LNG ) ?>
                </label>
            </p>
        </td>
    </tr>

    </tbody>
</table>

<h2><?php _e( 'Before copy', LNG ) ?></h2>

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
                    <input type="checkbox" value="1" name="clear_cron" <?php echo ( $options['clear_cron'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Clear cron queue', LNG ) ?>
                </label>
            </p>
        </td>
    </tr>

    </tbody>
</table>

<h2><?php _e( 'After copy', LNG ) ?></h2>

<table class="form-table">

    <tbody>

    <tr valign="top" class="complete-copy-option">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Clean up Woo', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label>
                    <input type="checkbox" value="1" name="cp_clear_woo" <?php echo ( $options['cp_clear_woo'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Clear all WooCommerce orders on new domains', LNG ) ?>
                </label>
            </p>
        </td>
    </tr>

    <tr valign="top">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Users', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label>
                    <input type="radio" value="all" name="cp_populate_level" <?php echo ( $options['cp_populate_level'] === 'all' ? 'checked' : '' ) ?>/>
                    <?php _e( 'Add all users from selected to new blogs', LNG ) ?>
                </label>
                <br/>
                <label>
                    <input type="radio" value="admins" name="cp_populate_level" <?php echo ( $options['cp_populate_level'] === 'admins' ? 'checked' : '' ) ?>/>
                    <?php _e( 'Add all admins only from selected to new blogs', LNG ) ?>
                </label>
                <br/>
                <label>
                    <input type="radio" value="me" name="cp_populate_level" <?php echo ( $options['cp_populate_level'] === 'me' ? 'checked' : '' ) ?>/>
                    <?php _e( 'Add only me', LNG ) ?>
                </label>
            </p>
        </td>
    </tr>

    </tbody>
</table>


<h2><?php _e( 'New domains to add', LNG ) ?></h2>

<table class="form-table">

    <tbody>

    <tr valign="top">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Selected domain', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <span class="current-domain copy-domain">...</span>
            </p>
        </td>
    </tr>

    <?php include "plugin-settings-domains-add.php" ?>

    </tbody>

</table>

<input type="hidden" name="pending_operation" value="mscopy" />