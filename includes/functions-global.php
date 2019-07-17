<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
* maybe show notice of minimum PHP version failure
*/
function em_impexp_fail_php_version() {
	if (em_impexp_can_show_admin_notices()) {
		em_impexp_load_text_domain();
		include EM_IMPEXP_PLUGIN_ROOT . 'views/requires-php.php';
	}
}

/**
* test whether we can show admin-related notices
* @return bool
*/
function em_impexp_can_show_admin_notices() {
	global $pagenow;

	// only on specific pages
	if ($pagenow !== 'plugins.php') {
		return false;
	}

	// only bother admins / plugin installers / option setters with this stuff
	if (!current_user_can('activate_plugins') && !current_user_can('manage_options')) {
		return false;
	}

	return true;
}

/**
* load text translations
*/
function em_impexp_load_text_domain() {
	load_plugin_textdomain('events-manager-import-export', false, plugin_basename(EM_IMPEXP_PLUGIN_ROOT . 'languages'));
}

/**
* replace link placeholders with an external link
* @param string $template
* @param string $url
* @return string
*/
function em_impexp_external_link($template, $url) {
	$search = array(
		'{{a}}',
		'{{/a}}',
	);
	$replace = array(
		sprintf('<a rel="noopener" target="_blank" href="%s">', esc_url($url)),
		'</a>',
	);
	return str_replace($search, $replace, $template);
}
