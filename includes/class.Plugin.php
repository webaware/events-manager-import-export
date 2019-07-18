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
		add_action('init', 'em_impexp_load_text_domain');
		add_action('admin_menu', [$this, 'addAdminMenu'], 20);
		add_filter('plugin_row_meta', [$this, 'pluginDetailsLinks'], 10, 2);

		// register import/export actions
		add_action('admin_post_em_impexp_export', [$this, 'exportEvents']);

		// handle automatic updates
		new Updater(EM_IMPEXP_PLUGIN_NAME, EM_IMPEXP_PLUGIN_FILE, [
			'slug'			=> 'events-manager-import-export',
			'plugin_title'	=> __('Events Manager Import Export', 'events-manager-import-export'),
			'update_url'	=> 'https://updates.webaware.net.au/events-manager-import-export/latest.json',
		]);
	}

	/**
	* add our menu item to Events Manager menu
	*/
	public function addAdminMenu() {
		if (defined('EM_POST_TYPE_EVENT')) {
			$label = _x('Import', 'admin menu', 'events-manager-import-export');
			add_submenu_page('edit.php?post_type='.EM_POST_TYPE_EVENT, $label, $label, 'activate_plugins', 'events-manager-import', [$this, 'importAdmin']);

			$label = _x('Export', 'admin menu', 'events-manager-import-export');
			add_submenu_page('edit.php?post_type='.EM_POST_TYPE_EVENT, $label, $label, 'activate_plugins', 'events-manager-export', [$this, 'exportAdmin']);
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

	/**
	* action hook for adding plugin details links
	*/
	public function pluginDetailsLinks($links, $file) {
		if ($file === EM_IMPEXP_PLUGIN_NAME) {
			$links[] = sprintf('<a href="https://translate.webaware.com.au/glotpress/projects/events-manager-import-export/" target="_blank" rel="noopener">%s</a>', esc_html_x('Translate', 'plugin details links', 'events-manager-import-export'));
		}

		return $links;
	}

}
