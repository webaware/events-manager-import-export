<?php
namespace webaware\em_import_export;

if (!defined('ABSPATH')) {
	exit;
}

/**
* plugin controller
*/
class Plugin {

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
	* hide constructor
	*/
	private function __construct() {}

	/**
	* initialise plugin
	*/
	public function pluginStart() {
		add_action('admin_menu', [$this, 'addAdminMenu'], 20);

		// register import/export actions
		add_action('admin_post_em_impexp_export', [$this, 'exportEvents']);

		// handle automatic updates
		new Updater();
	}

	/**
	* add our menu item to Events Manager menu
	*/
	public function addAdminMenu() {
		if (defined('EM_POST_TYPE_EVENT')) {
			add_submenu_page('edit.php?post_type='.EM_POST_TYPE_EVENT, 'Import', 'Import', 'activate_plugins', 'events-manager-import', [$this, 'importAdmin']);
			add_submenu_page('edit.php?post_type='.EM_POST_TYPE_EVENT, 'Export', 'Export', 'activate_plugins', 'events-manager-export', [$this, 'exportAdmin']);
		}
	}

	/**
	* handle menu item for import
	*/
	public function importAdmin() {
		$admin_url = add_query_arg(['post_type' => EM_POST_TYPE_EVENT, 'page' => 'events-manager-import'], admin_url('edit.php'));

		$admin = new Importer();
		$admin->render($admin_url);
	}

	/**
	* handle menu item for export
	*/
	public function exportAdmin() {
		$admin = new Exporter();
		$admin->render();
	}

	/**
	* export data
	*/
	public function exportEvents() {
		$admin = new Exporter();
		$admin->export();
	}

}
