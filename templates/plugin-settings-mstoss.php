<?php

namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Template: settings multisite to single site service
 *
 * @global $options
 * @global $domains
 */

?>

<h1 class="screen-reader-text"><?php _e('Transform to single site', LNG ) ?></h1>

<h2><?php _e( 'General options', LNG ) ?></h2>

<table class="form-table">

    <tbody>

    <tr valign="top">
        <th class="titledesc" scope="row">
            <label><?php _e( 'Transform into', LNG ) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p class="hint"><?php _e('Please, select blog, which will become the primary one', LNG ) ?></p>
            <select name="s_primary_domain" class="enhanced-select" style="width: 60%">
                <option disabled <?php echo ( $options['s_primary_domain'] ? '' : 'selected' ) ?> value ="-1">...</option>
                <?php
                    if( !empty( $domains ) )
                        foreach ( $domains as $domain )
                            echo '<option value="' . $domain['blog_id'] . '" '
                                    . ( $options['s_primary_domain'] === $domain['blog_id'] ? 'selected' :'' )
                                 .'>[' . $domain['blog_id'] . '] ' . $domain['domain'] . $domain['path']
                                 . ' (' . ( $domain['public'] ? __( 'active', LNG ) : __( 'inactive', LNG ) ) .')</option>';
                ?>
            </select>
        </td>
    </tr>

    <tr valign="top">
        <th class="titledesc" scope="row">
            <label><?php _e('Transformation way', LNG) ?></label>
        </th>
        <td class="forminp forminp-text">
            <p>
                <label><input type="radio" name="s_erase_all" value="1" <?php echo ( $options['s_erase_all'] ? 'checked' : '' ) ?> />
                    <?php _e( 'Erase all other domains and its data', LNG ) ?>
                </label>
            </p>
            <p>
                <label><input type="radio" name="s_erase_all" value="0" <?php echo ( $options['s_erase_all'] ? '' : 'checked' ) ?>/>
                    <?php _e( 'Only transform (keep data)', LNG ) ?>
                </label>
            </p>
            <p>
                <label title="<?php _e( '.htaccess and wp-config.php, erasing all files errors will be ignored', LNG ) ?>">
                    <input type="checkbox" value="1" name="s_ignore_file_errors" <?php echo ( $options['s_ignore_file_errors'] ? 'checked' : '' ) ?>/>
			        <?php _e( 'Ignore errors on files', LNG ) ?>
                </label>
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
                    <input type="checkbox" value="1" name="s_clear_transients" <?php echo ( $options['s_clear_transients'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Clear all transients', LNG ) ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" value="1" name="s_clear_cache" <?php echo ( $options['s_clear_cache'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Clear cache', LNG ) ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" value="1" name="s_remove_debug" <?php echo ( $options['s_remove_debug'] ? 'checked' : '' ) ?>/>
			        <?php _e( 'Remove WP debug.log file', LNG ) ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" value="1" name="s_clear_cron" <?php echo ( $options['s_clear_cron'] ? 'checked' : '' ) ?>/>
                    <?php _e( 'Clear cron queue', LNG ) ?>
                </label>
            </p>
        </td>
    </tr>

    </tbody>

</table>

<input type="hidden" name="pending_operation" value="mstoss" />