<?php
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="notice notice-error is-dismissible">
	<p><?php echo em_impexp_external_link(
			sprintf(esc_html__('Events Manager Import Export requires PHP %1$s or higher; your website has PHP %2$s which is {{a}}old, obsolete, and unsupported{{/a}}.', 'events-manager-import-export'),
			esc_html(EM_IMPEXP_MIN_PHP), esc_html(PHP_VERSION)),
			'https://www.php.net/supported-versions.php'
		); ?></p>
	<p><?php printf(__('Please upgrade your website hosting. At least PHP %s is recommended.', 'events-manager-import-export'), '7.2'); ?></p>
</div>
