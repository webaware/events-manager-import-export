<?php
namespace webaware\em_import_export;

if (!defined('ABSPATH')) {
	exit;
}

/**
* kick start the plugin
*/
add_action('plugins_loaded', function() {
	require EM_IMPEXP_PLUGIN_ROOT . 'includes/functions.php';
	require EM_IMPEXP_PLUGIN_ROOT . 'includes/class.EM_ImpExpPlugin.php';
	Plugin::getInstance()->pluginStart();
}, 5);
