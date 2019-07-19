<?php
namespace webaware\em_import_export;

use EM_Categories;
use EM_Category;
use EM_Event;
use EM_Events;
use EM_Location;
use Exception;
use XMLReader;

use ParseCsv\Csv;

if (!defined('ABSPATH')) {
	exit;
}

class Importer {

	/**
	* render the admin page
	*/
	public function render($admin_url) {
		if (!empty($_POST['action']) && $_POST['action'] === 'em_impexp_import') {
			$format = empty($_POST['imp_format']) ? '' : wp_unslash($_POST['imp_format']);
			$this->importEvents($format);
		}

		include EM_IMPEXP_PLUGIN_ROOT . 'views/import.php';
	}

	/**
	* import events based on specified format
	* @param string $format which format used
	*/
	protected function importEvents($format) {
		// test the file upload
		if (isset($_FILES['import_file'])) {
			switch ($_FILES['import_file']['error']) {

				case UPLOAD_ERR_OK:
					$filepath = $_FILES['import_file']['tmp_name'];
					break;

				case UPLOAD_ERR_NO_FILE:
					$errmsg = _x('No upload file selected.', 'error', 'events-manager-import-export');
					break;

				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					$errmsg = _x('Error uploading file - file too big.', 'error', 'events-manager-import-export');
					break;

				default:
					$errmsg = _x('Error uploading file.', 'error', 'events-manager-import-export');
					break;

			}
		}
		else {
			$errmsg = _x('No upload file selected.', 'error', 'events-manager-import-export');
		}

		if (empty($errmsg)) {
			try {
				set_time_limit(600);

				switch ($format) {

					case 'xCal':
						$this->importEventsXCal($filepath);
						break;

					case 'csv':
						$this->importEventsCSV($filepath);
						break;

				}
			}
			catch (ImportException $e) {
				$errmsg = sprintf(_x('Error importing events: %s', 'error', 'events-manager-import-export'), $e->getMessage());
			}
		}

		if (!empty($errmsg)) {
			show_admin_error($errmsg);
		}
	}

	/**
	* import events from xCal upload
	* @param string $filepath
	* @throws ImportException
	*/
	protected function importEventsXCal($filepath) {
		global $wpdb;

		$xml = new XMLReader();
		if (!$xml->open($filepath, 'UTF-8')) {
			throw new ImportException(_x("Can't open xCal file.", 'error', 'events-manager-import-export'));
		}

		$text = '';
		$haveRecord = false;
		$haveLocation = false;
		$records = 0;
		$attrs = [];
		$eventCategories = self::getEventCategories();
		$eventCountries = self::getEventCountries();

		while ($xml->read()) {
			switch ($xml->nodeType) {

				case XMLReader::ELEMENT:
					if ($xml->name === 'vevent') {
						$data = [
							'uid' => '',
							'url' => '',
							'summary' => '',
							'dtstart' => '',
							'dtend' => '',
							'categories' => '',
							'freq' => '',
							'byday' => '',
							'interval' => '',
							'until' => '',
							'x-post_content' => '',
							'x-post_excerpt' => '',
							'x-event_spaces' => '',
							'x-location_name' => '',
							'x-location_address' => '',
							'x-location_town' => '',
							'x-location_state' => '',
							'x-location_postcode' => '',
							'x-location_country' => '',
							'x-location_region' => '',
							'x-location_latitude' => '',
							'x-location_longitude' => '',
						];
						$attrs = [];
						$haveRecord = true;
						$haveLocation = false;
					}
					$text = '';
					break;

				case XMLReader::END_ELEMENT:
					if ($xml->name === 'vevent') {
						// end of vevent element, save record
						if ($haveRecord) {

							// if we have location, try to either retrieve it by name, or create a new location object
							$location = false;
							if ($haveLocation) {
								if ($data['x-location_name']) {
									// try to find location by name
									$location = $this->getLocationByName($data['x-location_name']);
								}
								if (!$location) {
									// must create a new location object
									$location = new EM_Location();
									$location->location_name      = $data['x-location_name'];
									$location->location_address   = $data['x-location_address'];
									$location->location_town      = $data['x-location_town'];
									$location->location_state     = $data['x-location_state'];
									$location->location_postcode  = $data['x-location_postcode'];
									$location->location_country   = $data['x-location_country'];
									$location->location_region    = $data['x-location_region'];
									$location->location_latitude  = $data['x-location_latitude'];
									$location->location_longitude = $data['x-location_longitude'];
									$location->save();
								}
							}

							// try to find existing event with matching unique ID first, so can update it
							$event = false;
							if ($data['uid']) {
								$event = EM_Events::get([EVENT_ATTR_UID => $data['uid']]);
								$event = empty($event[0]) ? false : $event[0];
							}
							if (!$event) {
								// must create a new event
								$event = new EM_Event();
							}
							$event->post_id = $data['uid'];	// post_id is now NOT NULL
							$event->location_id = $location ? $location->location_id : 0;
							$event->event_attributes[EVENT_ATTR_UID] = $data['uid'];
							$event->event_attributes[EVENT_ATTR_URL] = $data['url'];
							$event->event_name = $data['summary'];
							$event->post_content = apply_filters('em_impexp_import_content', $data['x-post_content'], $data, 'xCal');
							$event->post_excerpt = apply_filters('em_impexp_import_excerpt', $data['x-post_excerpt'], $data, 'xCal');
							if ($data['dtstart']) {
								$event->start = strtotime($data['dtstart']);
								$event->event_start_date = date('Y-m-d', $event->start);
								$event->event_start_time = date('H:i:s', $event->start);
							}
							if ($data['dtend']) {
								$event->end = strtotime($data['dtend']);
								$event->event_end_date = date('Y-m-d', $event->end);
								$event->event_end_time = date('H:i:s', $event->end);
							}
							$event->event_date_modified = current_time('mysql');
							$event->event_all_day = ($event->event_start_time === '00:00:00' && $event->event_end_time === '00:00:00') ? 1 : 0;

							foreach ($attrs as $attrName => $value) {
								$event->event_attributes[$attrName] = $value;
							}

							// TODO: recurring events
							switch ($data['freq']) {
								case 'DAILY':
									break;

								case 'WEEKLY':
									// phpcs:disable Squiz.PHP.CommentedOutCode.Found
									//~ $event->freq = $data['freq'];
									//~ $event->byday = $data['byday'];
									//~ $event->interval = $data['interval'];
									//~ $event->until = $data['until'];
									// phpcs:enable
									break;

								case 'MONTHLY':
									break;

							}

							if ($event) {
								$event->save();
								$event->save_meta();

								if ($data['categories']) {
									$categories = explode(',', $data['categories']);
									$eventcats = $event->get_categories();
									foreach ($categories as $category) {
										$category = trim($category);
										if (isset($eventCategories[$category])) {
											$cat = $eventCategories[$category];
										}
										else {
											$cat = wp_insert_term($category, 'event-categories');
											if (is_array($cat)) {
												$cat = new EM_Category($cat['term_id']);
												$eventCategories[$category] = $cat;
											}
										}

										if ($cat) {
											$eventcats->terms[$cat->id] = $cat;
										}
									}
									$eventcats->save();
								}
							}

							$records++;
						}
						$haveRecord = false;
						$haveLocation = false;
					}
					elseif ($haveRecord) {
						// still inside a vevent element, record field value and move on
						$name = $xml->name;

						switch ($name) {
							case 'x-event_attribute':
								// add to attributes array
								$attrs[$xml->getAttribute('name')] = $text;
								break;

							case 'x-location_country':
								if ($text !== '') {
									// convert to location code
									if (isset($eventCountries[strtolower($text)])) {
										$data[$name] = $eventCountries[strtolower($text)];
									}
									else {
										$data[$name] = $text;
									}
									$haveLocation = true;
								}
								break;

							default:
								if (array_key_exists($name, $data) && $text !== '') {
									// add to fields array
									$data[$name] = $text;

									// flag location fields that have data
									if (strpos($name, 'x-location') !== false) {
										$haveLocation = true;
									}
								}
								break;
						}
					}
					$text = '';
					break;

				case XMLReader::TEXT:
				case XMLReader::CDATA:
					// record value (or part value) of text or cdata node
					$text .= (string) $xml->value;
					break;

				default:
					break;

			}
		}

		$this->showCount($records);
	}

	/**
	* import events from CSV upload
	* @param string $filepath
	* @throws ImportException
	*/
	protected function importEventsCSV($filepath) {
		global $wpdb;

		$fp = fopen($filepath, 'r');
		if ($fp === false) {
			throw new ImportException(_x("Can't open CSV file.", 'error', 'events-manager-import-export'));
		}

		// read first line of CSV to make sure it's the correct format -- fgetscsv is fine for this simple task!
		$header = fgetcsv($fp);
		if ($header === false) {
			throw new ImportException(_x('error reading import file or file is empty', 'error', 'events-manager-import-export'));
		}
		if (is_null($header)) {
			throw new ImportException(_x('import file handle is null', 'error', 'events-manager-import-export'));
		}
		if (!is_array($header)) {
			throw new ImportException(_x('import file did not scan as CSV', 'error', 'events-manager-import-export'));
		}
		if (!in_array('summary', $header)) {
			throw new ImportException(_x('import file does not contain a field "summary"', 'error', 'events-manager-import-export'));
		}

		$wpdb->query('start transaction');

		$records = 0;
		$rows = 0;
		$attrs = [];
		$eventCategories = self::getEventCategories();
		$eventCountries = self::getEventCountries();

		$csv = new Csv();
		$csv->fields = $header;

		while ($line = fgets($fp)) {
			$line = "\n$line\n";		// fix up line so that it can be parsed correctly

			$cols = $csv->parse_string($line);

			if ($cols) {
				$rows++;
				$cols = $cols[0];

				// collect standard event properties
				$data = [
					'uid'                 => isset($cols['uid']) ? trim($cols['uid']) : '',
					'url'                 => isset($cols['url']) ? self::safeURL($cols['url']) : '',
					'summary'             => isset($cols['summary']) ? $cols['summary'] : '',
					'dtstart'             => isset($cols['dtstart']) ? $cols['dtstart'] : '',
					'dtend'               => isset($cols['dtend']) ? $cols['dtend'] : '',
					'dtformat'            => isset($cols['dtformat']) ? $cols['dtformat'] : '',
					'categories'          => isset($cols['categories']) ? $cols['categories'] : '',
					'freq'                => isset($cols['freq']) ? $cols['freq'] : '',
					'byday'               => isset($cols['byday']) ? $cols['byday'] : '',
					'interval'            => isset($cols['interval']) ? $cols['interval'] : '',
					'until'               => isset($cols['until']) ? $cols['until'] : '',
					'post_content'        => isset($cols['post_content']) ? $cols['post_content'] : '',
					'post_excerpt'        => isset($cols['post_excerpt']) ? $cols['post_excerpt'] : '',
					'event_spaces'        => isset($cols['event_spaces']) ? $cols['event_spaces'] : '',
					'location_name'       => isset($cols['location_name']) ? $cols['location_name'] : '',
					'location_address'    => isset($cols['location_address']) ? $cols['location_address'] : '',
					'location_town'       => isset($cols['location_town']) ? $cols['location_town'] : '',
					'location_state'      => isset($cols['location_state']) ? $cols['location_state'] : '',
					'location_postcode'   => isset($cols['location_postcode']) ? $cols['location_postcode'] : '',
					'location_country'    => isset($cols['location_country']) ? $cols['location_country'] : '',
					'location_region'     => isset($cols['location_region']) ? $cols['location_region'] : '',
					'location_latitude'   => isset($cols['location_latitude']) ? $cols['location_latitude'] : '',
					'location_longitude'  => isset($cols['location_longitude']) ? $cols['location_longitude'] : '',
				];

				if (isset($eventCountries[strtolower($data['location_country'])])) {
					$data['location_country'] = $eventCountries[strtolower($data['location_country'])];
				}

				// collect custom event attributes, being columns not found in standard event properties
				$attrs = [];
				foreach ($cols as $key => $value) {
					if (strlen($value) > 0 && !isset($data[$key])) {
						$attrs[$key] = $value;
					}
				}

				// if we have location, try to either retrieve it by name, or create a new location object
				$location = false;
				if (self::hasLocation($data)) {
					if ($data['location_name']) {
						// try to find location by name
						$location = $this->getLocationByName($data['location_name']);
					}
					// make sure the existing location is the same one by comparing postcodes
					if ($data['location_postcode'] && $location->location_postcode != $data['location_postcode']) {
						// this location has the same location_name as the one we want to create, but
						// is actually a different location (e.g. City Hall in City A vs City Hall in City B)
						$location = false;
					}
					if (!$location) {
						// must create a new location object
						$location = new EM_Location();
						$location->location_name      = empty($data['location_name']) ? self::fudgeLocationName($data) : $data['location_name'];
						$location->location_address   = empty($data['location_address']) ? $data['location_name'] : $data['location_address'];
						$location->location_town      = $data['location_town'];
						$location->location_state     = $data['location_state'];
						$location->location_postcode  = $data['location_postcode'];
						$location->location_country   = $data['location_country'];
						$location->location_region    = $data['location_region'];
						$location->location_latitude  = $data['location_latitude'];
						$location->location_longitude = $data['location_longitude'];
						$location->save();
					}
				}

				// try to find existing event with matching unique ID first, so can update it
				$event = false;
				if ($data['uid']) {
					$event = EM_Events::get([EVENT_ATTR_UID => $data['uid']]);
					$event = count($event) > 0 ? $event[0] : false;
				}
				if (!$event) {
					// must create a new event
					$event = new EM_Event();
				}
				$event->post_id = $data['uid']; // post_id is now NOT NULL
				$event->location_id = $location ? $location->location_id : 0;
				$event->event_attributes[EVENT_ATTR_UID] = $data['uid'];
				$event->event_attributes[EVENT_ATTR_URL] = $data['url'];
				$event->event_name = $data['summary'];
				$event->post_content = apply_filters('em_impexp_import_content', $data['post_content'], $data, 'csv');
				$event->post_excerpt = apply_filters('em_impexp_import_excerpt', $data['post_excerpt'], $data, 'csv');
				$dtformat = 'd/m/Y H:i:s';
				if (isset($data['dtformat']) && !empty($data['dtformat'])) {
					$dtformat = $data['dtformat'];
				}

				# parse start time
				$sevent = date_create_from_format($dtformat, $data['dtstart']);
				if ($sevent === FALSE) {
					throw new ImportException(
						/* translators: %1$s = event summary; %2$s = date format; %3$s = start date */
						sprintf(_x('invalid start date for %1$s: dtformat is %2$s and start date is %3$s', 'error', 'events-manager-import-export'),
							$data['summary'], $dtformat, $data['dtstart'])
					);
				}
				$event->start = $sevent->getTimestamp();
				$event->event_start_date = date('Y-m-d', $event->start);
				$event->event_start_time = date('H:i:s', $event->start);

				# parse end time
				$eevent = date_create_from_format($dtformat, $data['dtend']);
				if ($eevent === FALSE) {
					throw new ImportException(
						/* translators: %1$s = event summary; %2$s = date format; %3$s = end date */
						sprintf(_x('invalid start date for %1$s: dtformat is %2$s and start date is %3$s', 'error', 'events-manager-import-export'),
							$data['summary'], $dtformat, $data['dtend'])
					);
				}
				$event->end = $eevent->getTimestamp();
				$event->event_end_date = date('Y-m-d', $event->end);
				$event->event_end_time = date('H:i:s', $event->end);

				$event->event_date_modified = current_time('mysql');
				$event->event_all_day = ($event->event_start_time === '00:00:00' && $event->event_end_time === '00:00:00') ? 1 : 0;

				foreach ($attrs as $attrName => $value) {
					$event->event_attributes[$attrName] = $value;
				}

				// TODO: recurring events
				switch ($data['freq']) {

					case 'DAILY':
						break;

					case 'WEEKLY':
						// phpcs:disable Squiz.PHP.CommentedOutCode.Found
						//~ $event->freq = $data['freq'];
						//~ $event->byday = $data['byday'];
						//~ $event->interval = $data['interval'];
						//~ $event->until = $data['until'];
						// phpcs:enable
						break;

					case 'MONTHLY':
						break;

				}

				if ($event) {
					$event->save();
					$event->save_meta();

					if ($data['categories']) {
						$categories = explode(',', $data['categories']);
						$eventcats = $event->get_categories();
						foreach ($categories as $category) {
							$category = trim($category);
							if (isset($eventCategories[$category])) {
								$cat = $eventCategories[$category];
							}
							else {
								$cat = wp_insert_term($category, 'event-categories');
								if (is_array($cat)) {
									$cat = new EM_Category($cat['term_id']);
									$eventCategories[$category] = $cat;
								}
							}

							if ($cat) {
								$eventcats->terms[$cat->id] = $cat;
							}
						}
						$eventcats->save();
					}
				}

				$records++;
			}
		}

		$wpdb->query('commit');

		$this->showCount($records);
	}

	/**
	* show count of events loaded
	* @param int $records
	*/
	protected function showCount($records) {
		show_admin_message(sprintf(_nx('%s event loaded', '%s events loaded', $records, 'import', 'events-manager-import-export'), number_format_i18n($records)));
	}

	/**
	* ensure that URL has a protocol, give it http: if it doesn't
	* @param string $url
	* @return string
	*/
	protected static function safeURL($url) {
		if (!preg_match('#^https?://#i', $url)) {
			$url = 'http://' . $url;
		}

		return $url;
	}

	/**
	* check CSV data to see if location is given
	* @param array $data columns from a CSV row
	* @return bool
	*/
	protected static function hasLocation($data) {
		// get location fields that have a value
		$location = array_filter([
			$data['location_name'],
			$data['location_address'],
			$data['location_town'],
			$data['location_state'],
			$data['location_postcode'],
			$data['location_country'],
			$data['location_region'],
			$data['location_latitude'],
			$data['location_longitude'],
		], 'strlen');

		// if any were found, return true
		return count($location) > 0;
	}

	/**
	* find first non-empty location element for location name
	* @param array $data columns from a CSV row
	* @return string
	*/
	protected static function fudgeLocationName($data) {
		// get location fields that have a value
		$location = array_filter([
			$data['location_name'],
			$data['location_address'],
			$data['location_town'],
			$data['location_state'],
			$data['location_postcode'],
			$data['location_country'],
			$data['location_region'],
			$data['location_latitude'],
			$data['location_longitude'],
		], 'strlen');

		// return the first element
		return array_shift($location);
	}

	/**
	* get a location by name
	* @param string $location_name
	* @return EM_Location
	*/
	protected static function getLocationByName($location_name) {
		static $locations = false;

		if ($locations === false) {
			global $wpdb;
			$sql = '
				select location_name, location_id
				from ' . EM_LOCATIONS_TABLE . '
				where location_status = 1
			';
			$rows = $wpdb->get_results($sql);

			$locations = [];
			foreach ($rows as $row) {
				$locations[strtolower($row->location_name)] = (int) $row->location_id;
			}
		}

		$location_name = strtolower($location_name);

		$location = empty($locations[$location_name]) ? false : new EM_Location($locations[$location_name]);

		return $location;
	}

	/**
	* get a list of event categories, keyed by category name => category
	* @return array
	*/
	public static function getEventCategories() {
		$cats = EM_Categories::get();
		$eventCats = [];

		foreach ($cats as $cat) {
			$eventCats[$cat->name] = $cat;
		}

		return $eventCats;
	}

	/**
	* get a list of countries, keyed by lowercase name => code
	* @return array
	*/
	public static function getEventCountries() {
		$countries = em_get_countries();
		$map = [];
		foreach ($countries as $code => $name) {
			$map[strtolower($name)] = $code;
		}

		return $map;
	}

}
