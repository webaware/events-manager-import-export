<?php
// event import form

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class='wrap'>
	<h1><?= esc_html__('Import Events', 'events-manager-import-export'); ?></h1>

	<p><?= esc_html__('Import a file of events exported from Events Manager Import/Export plugin on another website.', 'events-manager-import-export'); ?></p>
	<form action="<?= esc_url($admin_url); ?>" method="post" enctype="multipart/form-data">
	<table class="form-table">

		<tr>
			<th scope="col"><?= esc_html_x('File format', 'import', 'events-manager-import-export'); ?></th>
			<td>
				<ul>
					<li>
						<input type="radio" name="imp_format" id="imp_format_xcal" value="xCal" checked="checked" />
						<label for="imp_format_xcal"><?= esc_html_x('xCal / Events Manager', 'file format', 'events-manager-import-export'); ?></label>
					</li>
					<li>
						<input type="radio" name="imp_format" id="imp_format_csv" value="csv" />
						<label for="imp_format_csv"><?= esc_html_x('CSV in Events Manager Import/Export format', 'file format', 'events-manager-import-export'); ?></label>
					</li>
				</ul>
			</td>
		</tr>

		<tr valign='top'>
			<th scope="col"><label for="import_file"><?= esc_html_x('File to import', 'import', 'events-manager-import-export'); ?></label></th>
			<td>
				<input type="file" class="regular-text" name="import_file" id="import_file" />
			</td>
		</tr>

		<tr>
			<th>&nbsp;</th>
			<td>
				<input type="submit" class="button-primary" value="<?= esc_html_x('Upload', 'import', 'events-manager-import-export'); ?>" />
				<input type="hidden" name="action" value="em_impexp_import" />
			</td>
		</tr>

	</table>
	</form>
</div>

