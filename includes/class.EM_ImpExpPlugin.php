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

	const TRANSIENT_UPDATE_INFO		= 'em_import_export_update_info';
	const URL_UPDATE_INFO			= 'https://raw.githubusercontent.com/webaware/events-manager-import-export/master/latest.json';

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

		// check for plugin updates
		add_filter('pre_set_site_transient_update_plugins', array($this, 'checkPluginUpdates'));
		add_filter('plugins_api', array($this, 'getPluginInfo'), 10, 3);
		add_action('plugins_loaded', array($this, 'clearPluginInfo'));

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
	* check for plugin updates, every so often
	* @param object $plugins
	* @return object
	*/
	public function checkPluginUpdates($plugins) {
		if (empty($plugins->last_checked)) {
			return $plugins;
		}

		$current = $this->getPluginData();
		$latest = $this->getLatestVersionInfo();

		if ($latest && version_compare($current['Version'], $latest->version, '<')) {
			$update = new stdClass;
			$update->id				= '0';
			$update->url			= $latest->homepage;
			$update->slug			= $latest->slug;
			$update->new_version	= $latest->version;
			$update->package		= $latest->download_link;

			$plugins->response[EM_IMPEXP_PLUGIN_NAME] = $update;
		}

		return $plugins;
	}

	/**
	* return plugin info for update pages, plugins list
	* @param boolean $false
	* @param array $action
	* @param object $args
	* @return bool|object
	*/
	public function getPluginInfo($false, $action, $args) {
		if (isset($args->slug) && $args->slug === basename(EM_IMPEXP_PLUGIN_NAME, '.php')) {
			return $this->getLatestVersionInfo();
		}

		return $false;
	}

	/**
	* if user asks to force an update check, clear our cached plugin info
	*/
	public function clearPluginInfo() {
		global $pagenow;

		if (!empty($_GET['force-check']) && !empty($pagenow) && $pagenow === 'update-core.php') {
			delete_site_transient(self::TRANSIENT_UPDATE_INFO);
		}
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
