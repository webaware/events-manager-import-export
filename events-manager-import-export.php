<?php
/*
Plugin Name: Events Manager Import Export
Plugin URI: https://github.com/webaware/events-manager-import-export
Description: import and export function for Events Manager
Version: 0.0.9
Author: WebAware
Author URI: http://webaware.com.au/
*/

/*
copyright (c) 2012-2016 WebAware Pty Ltd (email : support@webaware.com.au)

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if (!defined('ABSPATH')) {
	exit;
}

define('EM_IMPEXP_PLUGIN_FILE', __FILE__);
define('EM_IMPEXP_PLUGIN_ROOT', dirname(__FILE__) . '/');
define('EM_IMPEXP_PLUGIN_NAME', basename(dirname(__FILE__)) . '/' . basename(__FILE__));
define('EM_IMPEXP_PLUGIN_VERSION', '0.0.9');

// instantiate the plug-in
require EM_IMPEXP_PLUGIN_ROOT . 'includes/class.EM_ImpExpPlugin.php';
EM_ImpExpPlugin::getInstance();
