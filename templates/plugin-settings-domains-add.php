<?php

namespace Wetail\SSMS;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 *
 * Template: table to add domains
 *
 * @global $cdomain
 * @global $path
 *
 */

?>
<tr valign="top">
	<th class="titledesc" scope="row">
		<label><?php _e( 'Add new blogs', LNG ) ?></label>
	</th>
	<td class="forminp forminp-text">

		<table class="new-domains widefat">
			<thead>
			<tr>
				<th><p>#</p></th>
				<th>
					<p><?php _e('Domain', LNG) ?></p>
					<p class="hint"><?php _e('It is allowed to add any domains/subdomains/paths here', LNG) ?></p>
				</th>
				<th></th>
			</tr>
			</thead>
			<tbody>
			<tr class="nd-tpl">
				<td class="autonum"></td>
				<td><p><input type="text" name="domains[]" disabled value="<?php echo $cdomain.$path ?>" /></p></td>
				<td><button class="delete-domain" title="<?php _e('Remove this domain', LNG) ?>"></button></td>
			</tr>
			<?php
			if( !empty( $options['domains'] ) )
				foreach( $options['domains'] as $domain )
					echo   '<tr>
							<td class="autonum"></td>
							<td><p><input type="text" name="domains[]" value="'.$domain.'" /></p></td>
							<td><button class="delete-domain" title="' . __('Remove this domain', LNG) . '"></button></td>
							</tr>';
			?>
			</tbody>
			<tfoot>
			<tr>
				<td colspan="100%"><button class="button button-secondary add-domain"><?php _e('Add', LNG) ?></button></td>
			</tr>
			</tfoot>
		</table>

        <p class="hint"><?php _e( 'Please, note, new domain or sub-domain names supposed to be active and accessible', LNG ) ?></p>

	</td>
</tr>