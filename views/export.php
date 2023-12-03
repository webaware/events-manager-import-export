<?php
// event export form

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class='wrap'>
	<h1><?= esc_html__('Export Events', 'events-manager-import-export'); ?></h1>

	<form action="<?= esc_url(admin_url('admin-post.php')); ?>" method="POST" id="em-impexp-export-frm">
		<input type="hidden" name="action" value="em_impexp_export" />

		<table class="form-table">

			<tr>
				<th scope="col"><?= esc_html_x('Export format', 'export', 'events-manager-import-export'); ?></th>
				<td>
					<ul>
						<li>
							<input type="radio" name="exp_format" id="exp_format_xcal" value="xCal" checked="checked" />
							<label for="exp_format_xcal"><?= esc_html_x('xCal / Events Manager', 'file format', 'events-manager-import-export'); ?></label>
						</li>
						<li>
							<input type="radio" name="exp_format" id="exp_format_ical" value="iCal" />
							<label for="exp_format_ical"><?= esc_html_x('iCal / ics', 'file format', 'events-manager-import-export'); ?></label>
						</li>
						<li>
							<input type="radio" name="exp_format" id="exp_format_ee" value="Event Espresso" />
							<label for="exp_format_ee"><?= esc_html_x('Event Espresso', 'file format', 'events-manager-import-export'); ?></label>
						</li>
                                                <li>
                                                        <input type="radio" name="exp_format" id="exp_format_csv" value="CSV" />
                                                        <label for="exp_format_csv"><?= esc_html_x('CSV', 'file format', 'events-manager-import-export'); ?></label>
                                                </li>
					</ul>
				</td>
			</tr>

			<tr>
				<th>&nbsp;</th>
				<td>
					<input type="submit" class="button-primary" value="<?= esc_html_x('Export', 'export', 'events-manager-import-export'); ?>" />
				</td>
			</tr>

		</table>
	</form>

</div>

