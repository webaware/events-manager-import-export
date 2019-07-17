<?php
// event export form

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class='wrap'>
	<h2>Export Events</h2>

	<form action="<?= esc_url(admin_url('admin-post.php')); ?>" method="POST" id="em-impexp-export-frm">
		<input type="hidden" name="action" value="em_impexp_export" />

		<table class="form-table">

			<tr>
				<th>Export format:</th>
				<td>
					<label><input type="radio" name="exp_format" id="exp_format_xcal" value="xCal" checked="checked" /> xCal / Events Manager</label><br />
					<label><input type="radio" name="exp_format" id="exp_format_ical" value="iCal" /> iCal / ics</label><br />
					<label><input type="radio" name="exp_format" id="exp_format_ee" value="Event Espresso" /> Event Espresso</label>
				</td>
			</tr>

			<tr>
				<th>&nbsp;</th>
				<td>
					<input type="submit" class="button-primary" value="export" />
				</td>
			</tr>

		</table>
	</form>

</div>

