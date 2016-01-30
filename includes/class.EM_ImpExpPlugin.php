<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
* custom exception class
*/
class EM_ImpExpImportException extends Exception {}

/**
* plugin controller
*/
class EM_ImpExpPlugin {

	/**
	* static method for getting the instance of this singleton object
	* @return self
	*/
	public static function getInstance() {
		static $instance = NULL;

		if (is_null($instance)) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	* private constructor; can only instantiate via getInstance() class method
	*/
	private function __construct() {
		add_action('init', array($this, 'init'));
		add_action('admin_menu', array($this, 'addAdminMenu'), 20);

		// register import/export actions
		add_action('admin_post_em_impexp_export', array($this, 'exportEvents'));
	}

	/**
	* initialise plug-in
	*/
	public function init() {
	}

	/**
	* add our menu item to Events Manager menu
	*/
	public function addAdminMenu() {
		if (defined('EM_POST_TYPE_EVENT')) {
			add_submenu_page('edit.php?post_type='.EM_POST_TYPE_EVENT, 'Import', 'Import', 'activate_plugins', 'events-manager-import', array($this, 'importAdmin'));
			add_submenu_page('edit.php?post_type='.EM_POST_TYPE_EVENT, 'Export', 'Export', 'activate_plugins', 'events-manager-export', array($this, 'exportAdmin'));
		}
	}

	/**
	* handle menu item for import
	*/
	public function importAdmin() {
		require EM_IMPEXP_PLUGIN_ROOT . 'includes/class.EM_ImpExpImport.php';
		require EM_IMPEXP_PLUGIN_ROOT . 'lib/parsecsv/parsecsv.lib.php';
		$admin = new EM_ImpExpImport($this, 'events-manager-import');
		$admin->render();
	}

	/**
	* handle menu item for export
	*/
	public function exportAdmin() {
		require EM_IMPEXP_PLUGIN_ROOT . 'includes/class.EM_ImpExpExport.php';
		$admin = new EM_ImpExpExport($this);
		$admin->render();
	}

	/**
	* export data
	*/
	public function exportEvents() {
		require EM_IMPEXP_PLUGIN_ROOT . 'includes/class.EM_ImpExpExport.php';
		$admin = new EM_ImpExpExport($this);
		$admin->export();
	}

	/**
	* display a message (already HTML-conformant)
	* @param string $msg HTML-encoded message to display inside a paragraph
	*/
	public static function showMessage($msg) {
		echo "<div class='updated fade'><p><strong>$msg</strong></p></div>\n";
	}

	/**
	* display an error message (already HTML-conformant)
	* @param string $msg HTML-encoded message to display inside a paragraph
	*/
	public static function showError($msg) {
		echo "<div class='error'><p><strong>$msg</strong></p></div>\n";
	}

}
