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
	require EM_IMPEXP_PLUGIN_ROOT . 'includes/class.Plugin.php';
	Plugin::getInstance()->pluginStart();
}, 5);

/**
* autoload classes as/when needed
* @param string $class_name name of class to attempt to load
*/
spl_autoload_register(function($class_name) {
	static $classMap = [
		'Exporter'							=> 'includes/class.Exporter.php',
		'Importer'							=> 'includes/class.Importer.php',
		'Updater'							=> 'includes/class.Updater.php',
	];
	static $parsecsvMap = [
		'Csv'								=> 'lib/parsecsv/src/Csv.php',
		'enums\\AbstractEnum'				=> 'lib/parsecsv/src/enums/AbstractEnum.php',
		'enums\\DatatypeEnum'				=> 'lib/parsecsv/src/enums/DatatypeEnum.php',
		'enums\\FileProcessingModeEnum'		=> 'lib/parsecsv/src/enums/FileProcessingModeEnum.php',
		'enums\\SortEnum'					=> 'lib/parsecsv/src/enums/SortEnum.php',
		'extensions\\DatatypeTrait'			=> 'lib/parsecsv/src/extensions/DatatypeTrait.php',
	];

	if (strpos($class_name, __NAMESPACE__) === 0) {
		$class_name = substr($class_name, strlen(__NAMESPACE__) + 1);

		if (isset($classMap[$class_name])) {
			require EM_IMPEXP_PLUGIN_ROOT . $classMap[$class_name];
		}
	}
	elseif (strpos($class_name, 'ParseCsv') === 0) {
		$class_name = substr($class_name, strlen('ParseCsv') + 1);

		if (isset($parsecsvMap[$class_name])) {
			require EM_IMPEXP_PLUGIN_ROOT . $parsecsvMap[$class_name];
		}
	}
});
