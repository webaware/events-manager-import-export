<?php
namespace webaware\em_import_export;

use Exception;

if (!defined('ABSPATH')) {
	exit;
}

// custom event attributes used by import/export
const EVENT_ATTR_UID		= '_em_impexp_uid';
const EVENT_ATTR_URL		= '_em_impexp_url';

/**
* custom exception class
*/
class ImportException extends Exception {}

/**
* display a message in the admin
* @param string $msg
*/
function show_admin_message($msg) {
	printf('<div class="notice updated"><p><strong>%s</strong></p></div>', esc_html($msg));
}

/**
* display an error message in the admin
* @param string $msg
*/
function show_admin_error($msg) {
	printf('<div class="notice notice-error"><p><strong>%s</strong></p></div>', esc_html($msg));
}

/**
* encapsulate text in quotes if unsuitable for CSV without quotes
* NB: Events Espresso is highly dodgy and doesn't handle apostrophes, so must convert!
* @param string $text
* @return string
*/
function text_to_csv($text) {
	$len = strlen($text);

	if ($len > 0 && $len !== strcspn($text, "\"',;$\\\r\n0123456789")) {
		return '"' . strtr(str_replace('"', '""', $text), "'", '`') . '"';
	}

	return $text;
}

/**
* generate unique ID for event
* @param EM_Event $EM_Event
* @return string
*/
function get_em_unique_id($EM_Event) {
	return "events-manager-{$EM_Event->event_id}@" . parse_url(get_option('home'), PHP_URL_HOST);
}

/**
* add our custom event attributes to Event Manager's known attributes
* @param array $attrs
* @param array $matches
* @param bool $is_for_location
* @return array
*/
function add_custom_event_attributes($attrs, $matches, $is_for_location) {
	if (!$is_for_location) {
		$attrs['names'][] = EVENT_ATTR_UID;
		$attrs['names'][] = EVENT_ATTR_URL;

		$attrs['values'][EVENT_ATTR_UID] = [];
		$attrs['values'][EVENT_ATTR_URL] = [];
	}

	return $attrs;
}
