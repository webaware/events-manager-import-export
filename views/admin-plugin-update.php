<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<tr class="active plugin-update-tr" data-slug="<?= esc_attr($plugin_slug); ?>" data-plugin="<?= $plugin_name; ?>">
	<td colspan="<?= $wp_list_table->get_column_count(); ?>" class="plugin-update colspanchange">
		<div class="update-message notice inline notice-warning notice-alt">
			<p><?php
				printf(
					/* translators: 1: plugin name, 2: details URL, 3: additional link attributes, 4: version number, 5: update URL, 6: additional link attributes */
					translate( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a> or <a href="%5$s" %6$s>update now</a>.' ),
					$plugin_name,
					esc_url( $changelog_link ),
					sprintf( 'class="thickbox open-plugin-details-modal" aria-label="%s"',
						/* translators: 1: plugin name, 2: version number */
						esc_attr( sprintf( translate( 'View %1$s version %2$s details' ), $plugin_name, $new_version ) )
					),
					$new_version,
					esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $this->name, 'upgrade-plugin_' . $this->name ) ),
					sprintf( 'class="update-link" aria-label="%s"',
						/* translators: %s: plugin name */
						esc_attr( sprintf( translate( 'Update %s now' ), $plugin_name ) )
					)
				);

				do_action( "in_plugin_update_message-{$file}", $plugin, $info );
				?></p>
		</div>
	</td>
</tr>
