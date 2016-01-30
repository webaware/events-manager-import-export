<?php

if (!defined('ABSPATH')) {
	exit;
}

class EM_ImpExpImport {

	protected $plugin;
	protected $menuPage;

	/**
	* @param EM_ImpExpPlugin $plugin handle to the plugin object
	* @param string $menuPage slug for menu page
	*/
	public function __construct($plugin, $menuPage) {
		$this->plugin = $plugin;
		$this->menuPage = $menuPage;
	}

	/**
	* render the admin page
	*/
	public function render() {
		$action = self::getPostValue('action');
		$format = self::getPostValue('imp_format');

		if (self::isFormPost() && $action === 'em_impexp_import') {
			$this->importEvents($format);
		}

		$url = add_query_arg(array('post_type' => EM_POST_TYPE_EVENT, 'page' => $this->menuPage), admin_url('edit.php'));

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
					$errmsg = '# no upload file selected.';
					break;

				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					$errmsg = '# error uploading file - file too big.';
					break;

				default:
					$errmsg = '# error uploading file.';
					break;

			}
		}
		else {
			$errmsg = '# no upload file selected.';
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
			catch (Exception $e) {
				$errmsg = '# error importing events: ' . esc_html($e->getMessage());
			}
		}

		if (!empty($errmsg)) {
			$this->plugin->showError($errmsg);
		}
	}

	/**
	* import events from xCal upload
	* @param string $filepath
	*/
	protected function importEventsXCal($filepath) {
		global $wpdb;

		$xml = new XMLReader();
		if (!$xml->open($filepath, 'UTF-8')) {
			throw new EM_ImpExpImportException('error opening xCal file.');
		}

		$text = '';
		$haveRecord = false;
		$haveLocation = false;
		$records = 0;
		$attrs = array();
		$eventCategories = self::getEventCategories();

		while ($xml->read()) {
			switch ($xml->nodeType) {

				case XMLReader::ELEMENT:
					if ($xml->name === 'vevent') {
						$data = array(
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
						);
						$attrs = array();
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
									add_filter('em_locations_get_default_search', array(__CLASS__, 'filterLocationArgs'), 10, 2);
									add_filter('em_locations_build_sql_conditions', array(__CLASS__, 'filterLocationSQL'), 10, 2);

									$location = EM_Locations::get(array('location_name' => $data['x-location_name']));
									$location = count($location) > 0 ? $location[0] : false;

									remove_filter('em_locations_get_default_search', array(__CLASS__, 'filterLocationArgs'), 10, 2);
									remove_filter('em_locations_build_sql_conditions', array(__CLASS__, 'filterLocationSQL'), 10, 2);
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
								add_filter('em_events_get_default_search', array(__CLASS__, 'filterEventArgs'), 10, 2);
								add_filter('em_events_build_sql_conditions', array(__CLASS__, 'filterEventSQL'), 10, 2);

								$event = EM_Events::get(array('em_impexp_uid' => $data['uid']));
								$event = count($event) > 0 ? $event[0] : false;

								remove_filter('em_events_get_default_search', array(__CLASS__, 'filterEventArgs'), 10, 2);
								remove_filter('em_events_build_sql_conditions', array(__CLASS__, 'filterEventSQL'), 10, 2);
							}
							if (!$event) {
								// must create a new event
								$event = new EM_Event();
							}
							$event->location_id = $location ? $location->location_id : 0;
							$event->event_attributes['em_impexp_uid'] = $data['uid'];
							$event->event_attributes['em_impexp_url'] = $data['url'];
							$event->event_name = $data['summary'];
							$event->post_content = $data['x-post_content'];
							$event->post_excerpt = $data['x-post_excerpt'];
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
									//~ $event->freq = $data['freq'];
									//~ $event->byday = $data['byday'];
									//~ $event->interval = $data['interval'];
									//~ $event->until = $data['until'];
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
											$eventcats->categories[$cat->id] = $cat;
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

		$this->plugin->showMessage($records === 1 ? '1 events loaded' : "$records events loaded");
	}

	/**
	* import events from CSV upload
	* @param string $filepath
	*/
	protected function importEventsCSV($filepath) {
		global $wpdb;

		$fp = fopen($filepath, 'r');
		if ($fp === false) {
			throw new EM_ImpExpImportException('error opening CSV file');
		}

		// read first line of CSV to make sure it's the correct format -- fgetscsv is fine for this simple task!
		$header = fgetcsv($fp);
		if ($header === false) {
			throw new EM_ImpExpImportException('error reading import file or file is empty');
		}
		if (is_null($header)) {
			throw new EM_ImpExpImportException('import file handle is null');
		}
		if (!is_array($header)) {
			throw new EM_ImpExpImportException('import file did not scan as CSV');
		}
		if (!in_array('summary', $header)) {
			throw new EM_ImpExpImportException('import file does not contain a field "summary"');
		}

		$wpdb->query('start transaction');

		$records = 0;
		$rows = 0;
		$attrs = array();
		$eventCategories = self::getEventCategories();
		$eventCountries = self::getEventCountries();

		$csv = new parseCSV();
		$csv->fields = $header;

		while ($line = fgets($fp)) {
			$line = "\n$line\n";		// fix up line so that it can be parsed correctly

			$cols = $csv->parse_string($line);

			if ($cols) {
				$rows++;
				$cols = $cols[0];

				// collect standard event properties
				$data = array(
					'uid'                 => isset($cols['uid']) ? trim($cols['uid']) : '',
					'url'                 => isset($cols['url']) ? self::safeURL($cols['url']) : '',
					'summary'             => isset($cols['summary']) ? $cols['summary'] : '',
					'dtstart'             => isset($cols['dtstart']) ? $cols['dtstart'] : '',
					'dtend'               => isset($cols['dtend']) ? $cols['dtend'] : '',
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
				);

				if (isset($eventCountries[strtolower($data['location_country'])])) {
					$data['location_country'] = $eventCountries[strtolower($data['location_country'])];
				}

				// collect custom event attributes, being columns not found in standard event properties
				$attrs = array();
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
					add_filter('em_events_get_default_search', array(__CLASS__, 'filterEventArgs'), 10, 2);
					add_filter('em_events_build_sql_conditions', array(__CLASS__, 'filterEventSQL'), 10, 2);

					$event = EM_Events::get(array('em_impexp_uid' => $data['uid']));
					$event = count($event) > 0 ? $event[0] : false;

					remove_filter('em_events_get_default_search', array(__CLASS__, 'filterEventArgs'), 10, 2);
					remove_filter('em_events_build_sql_conditions', array(__CLASS__, 'filterEventSQL'), 10, 2);
				}
				if (!$event) {
					// must create a new event
					$event = new EM_Event();
				}
				$event->location_id = $location ? $location->location_id : 0;
				$event->event_attributes['em_impexp_uid'] = $data['uid'];
				$event->event_attributes['em_impexp_url'] = $data['url'];
				$event->event_name = $data['summary'];
				$event->post_content = $data['post_content'];
				$event->post_excerpt = $data['post_excerpt'];
				if (preg_match('@^\\d\\d/\\d\\d/\\d\\d\\d\\d$@', $data['dtstart'])) {
					$data['dtstart'] .= ' 00:00:00';
					$event->start = date_create_from_format('d/m/Y H:i:s', $data['dtstart'])->getTimestamp();
					$event->event_start_date = date('Y-m-d', $event->start);
					$event->event_start_time = date('H:i:s', $event->start);
				}
				if (preg_match('@^\\d\\d/\\d\\d/\\d\\d\\d\\d$@', $data['dtend'])) {
					$data['dtend'] .= ' 00:00:00';
					$event->end = date_create_from_format('d/m/Y H:i:s', $data['dtend'])->getTimestamp();
					$event->event_end_date = date('Y-m-d', $event->end);
					$event->event_end_time = date('H:i:s', $event->end);
				}
				else {
					$event->end = $event->start;
					$event->event_end_date = $event->event_start_date;
					$event->event_end_time = $event->event_start_time;
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
						//~ $event->freq = $data['freq'];
						//~ $event->byday = $data['byday'];
						//~ $event->interval = $data['interval'];
						//~ $event->until = $data['until'];
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
								$eventcats->categories[$cat->id] = $cat;
							}
						}
						$eventcats->save();
					}
				}

				$records++;
			}
		}

		$wpdb->query('commit');

		$this->plugin->showMessage($records === 1 ? '1 events loaded' : "$records events loaded");
	}

	/**
	* Is this web request a form post?
	* Checks to see whether the HTML input form was posted.
	* @return boolean
	*/
	protected static function isFormPost() {
		return ($_SERVER['REQUEST_METHOD'] === 'POST');
	}

	/**
	* Read a field from form post input.
	*
	* Guaranteed to return a string, trimmed of leading and trailing spaces, and with sloshes stripped out.
	*
	* @param string $fieldname name of the field in the form post
	* @return string
	*/
	protected static function getPostValue($fieldname) {
		return isset($_POST[$fieldname]) ? stripslashes(trim($_POST[$fieldname])) : '';
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
		$location = array_filter(array (
			$data['location_name'],
			$data['location_address'],
			$data['location_town'],
			$data['location_state'],
			$data['location_postcode'],
			$data['location_country'],
			$data['location_region'],
			$data['location_latitude'],
			$data['location_longitude'],
		), 'strlen');

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
		$location = array_filter(array (
			$data['location_name'],
			$data['location_address'],
			$data['location_town'],
			$data['location_state'],
			$data['location_postcode'],
			$data['location_country'],
			$data['location_region'],
			$data['location_latitude'],
			$data['location_longitude'],
		), 'strlen');

		// return the first element
		return array_shift($location[0]);
	}

	/**
	* get a location by name
	* @param string $location_name
	* @return EM_Location
	*/
	protected static function getLocationByName($location_name) {
		// add query filters to splice in our search term
		add_filter('em_locations_get_default_search', array(__CLASS__, 'filterLocationArgs'), 10, 2);
		add_filter('em_locations_build_sql_conditions', array(__CLASS__, 'filterLocationSQL'), 10, 2);

		$location = EM_Locations::get(array('location_name' => $location_name));
		$location = count($location) > 0 ? $location[0] : false;

		// remove our query filters
		remove_filter('em_locations_get_default_search', array(__CLASS__, 'filterLocationArgs'), 10, 2);
		remove_filter('em_locations_build_sql_conditions', array(__CLASS__, 'filterLocationSQL'), 10, 2);

		return $location;
	}

	/**
	* filter the search arguments for an events search, to restore the em_impexp_uid argument
	* @param array $filtered assoc. array of filtered arguments used for search
	* @param array $args assoc. array of original search arguments
	* @return array
	*/
	public static function filterEventArgs($filtered, $args) {
		if (isset($args['em_impexp_uid'])) {
			$filtered['em_impexp_uid'] = $args['em_impexp_uid'];
		}

		return $filtered;
	}

	/**
	* filter the SQL where clause conditions for an events search, to include em_impexp_uid
	* @param array $conditions where clause conditions
	* @param array $args assoc. array of search arguments
	* @return array
	*/
	public static function filterEventSQL($conditions, $args) {
		if (isset($args['em_impexp_uid'])) {
			$em_events_table = EM_EVENTS_TABLE;
			$uid = esc_sql($args['em_impexp_uid']);
			$conditions[] = "($em_events_table.event_attributes regexp '\"em_impexp_uid\";s:\[0-9\]+:\"$uid\"')";
		}

		return $conditions;
	}

	/**
	* filter the search arguments for a location search, to restore the location_name argument
	* @param array $filtered assoc. array of filtered arguments used for search
	* @param array $args assoc. array of original search arguments
	* @return array
	*/
	public static function filterLocationArgs($filtered, $args) {
		if (isset($args['location_name'])) {
			$filtered['location_name'] = $args['location_name'];
		}

		return $filtered;
	}

	/**
	* filter the SQL where clause conditions for a location search, to include location_name
	* @param array $conditions where clause conditions
	* @param array $args assoc. array of search arguments
	* @return array
	*/
	public static function filterLocationSQL($conditions, $args) {
		global $wpdb;

		if (isset($args['location_name'])) {
			$conditions[] = $wpdb->prepare("location_name = %s", $args['location_name']);
		}

		return $conditions;
	}

	/**
	* get a list of event categories, keyed by category name => category
	* @return array
	*/
	public static function getEventCategories() {
		$cats = EM_Categories::get();
		$eventCats = array();

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
		$map = array();
		foreach ($countries as $code => $name) {
			$map[strtolower($name)] = $code;
		}

		return $map;
	}

}
