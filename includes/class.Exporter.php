<?php
namespace webaware\em_import_export;

use DateTimeZone;
use EM_Events;
use XMLWriter;

if (!defined('ABSPATH')) {
	exit;
}

class Exporter {

	/**
	* render the admin page
	*/
	public function render() {
		include EM_IMPEXP_PLUGIN_ROOT . 'views/export.php';
	}

	/**
	* export the data in selected format
	*/
	public function export() {
		$EM_Events = EM_Events::get(array('scope' => 'all'));

		$format = isset($_POST['exp_format']) ? wp_unslash($_POST['exp_format']) : '';

		switch ($format) {

			case 'xCal':
				$this->exportXCal($EM_Events);
				break;

			case 'iCal':
				$this->exportICal($EM_Events);
				break;

			case 'Event Espresso':
				$this->exportEventEspresso($EM_Events);
				break;

		}
	}

	/**
	* export data in xCal format
	* @param EM_Events $EM_Events
	*/
	protected function exportXCal($EM_Events) {
		if (isset($_REQUEST['plaintext']) && $_REQUEST['plaintext'] === '1') {
			header('Content-Type: text/plain; charset=utf-8');
		}
		else {
			header('Content-Type: text/xml; charset=utf-8');
			header('Content-Disposition: attachment; filename="events.xcs"');
		}

		nocache_headers();

		$UTC = new DateTimeZone('UTC');

		$xml = new XMLWriter();
		$xml->openURI('php://output');			// write directly to PHP output
		$xml->startDocument('1.0', 'UTF-8');

		// output header
		$xml->startElement('iCalendar');
		$xml->startElement('vcalendar');

		// output stream properties
		$xml->startElement('properties');
		$xml->writeElement('version', '2.0');
		$xml->writeElement('method', 'PUBLISH');
		$xml->writeElement('calscale', 'GREGORIAN');
		$xml->writeElement('prodid', '-//Events Manager//1.0//EN');
		$xml->endElement();		// properties

		// loop over events to export
		$xml->startElement('components');
		foreach ($EM_Events as $EM_Event) {

			// start event
			$xml->startElement('vevent');

			// manufacture a unique ID
			$xml->writeElement('uid', get_em_unique_id($EM_Event));

			// add link to event post
			$xml->writeElement('url', $EM_Event->output('#_EVENTURL'));

			// use the event name as the summary
			$xml->writeElement('summary', $EM_Event->event_name);

			// output some custom properties
			$xml->writeElement('x-post_content', $EM_Event->post_content);
			if (!empty($EM_Event->post_excerpt)) {
				$xml->writeElement('x-post_excerpt', $EM_Event->post_excerpt);
			}
			$xml->writeElement('x-event_spaces', $EM_Event->event_spaces);

			// get the start, end and last modified dates/times
			$time_start = clone $EM_Event->start();
			$time_start->setTimezone($UTC);
			$xml->writeElement('dtstart', $time_start->format('Y-m-d\TH:i:s\Z'));
			$time_end = clone $EM_Event->end();
			$time_end->setTimezone($UTC);
			$xml->writeElement('dtend', $time_end->format('Y-m-d\TH:i:s\Z'));
			$xml->writeElement('dtstamp', date('Y-m-d\TH:i:s\Z', strtotime($EM_Event->post_modified_gmt)));

			// get categories as comma-separated list
			$cats = $EM_Event->get_categories()->categories;
			if (count($cats) > 0) {
				$categories = [];
				foreach ($cats as $cat) {
					$categories[] = $cat->output('#_CATEGORYNAME');
				}
				$xml->writeElement('categories', implode(',', $categories));
			}

			// if event has a location, get the location name
			$location = $EM_Event->get_location();
			if (is_object($location)) {

				$xml->startElement('location');

				if (!empty($location->location_name)) {
					$xml->writeElement('x-location_name', $location->location_name);
				}

				if (!empty($location->location_address)) {
					$xml->writeElement('x-location_address', $location->location_address);
				}

				if (!empty($location->location_town)) {
					$xml->writeElement('x-location_town', $location->location_town);
				}

				if (!empty($location->location_state)) {
					$xml->writeElement('x-location_state', $location->location_state);
				}

				if (!empty($location->location_postcode)) {
					$xml->writeElement('x-location_postcode', $location->location_postcode);
				}

				if (!empty($location->location_country)) {
					$xml->writeElement('x-location_country', $location->get_country());
				}

				$xml->endElement();		// location

				if (!empty($location->location_region)) {
					$xml->writeElement('x-location_region', $location->location_region);
				}

				if (!empty($location->location_latitude)) {
					$xml->writeElement('x-location_latitude', $location->location_latitude);
				}

				if (!empty($location->location_longitude)) {
					$xml->writeElement('x-location_longitude', $location->location_longitude);
				}
			}

			// if event has attributes, save them as a collection
			if (count($EM_Event->event_attributes) > 0) {
				$xml->startElement('x-event_attributes');

				foreach ($EM_Event->event_attributes as $name => $value) {
					$xml->startElement('x-event_attribute');
					$xml->writeAttribute('name', $name);
					$xml->text($value);
					$xml->endElement();		// x-event_attribute
				}

				$xml->endElement();		// x-event_attributes
			}

			// get recurring events in iCalendar format
			$recurrence = '';
			if (!$EM_Event->is_individual()) {
				$recurrence = $EM_Event->get_event_recurrence();
				if (!empty($recurrence)) {
					$xml->startElement('rrule');
					$xml->startElement('recur');

					$days = ['SU','MO','TU','WE','TH','FR','SA'];
					$until = date('Y-m-d\TH:i:s\Z', $recurrence->end - $gmtOffset);

					switch ($recurrence->freq) {
						case 'daily':
							$xml->writeElement('freq', 'DAILY');
							$xml->writeElement('interval', $recurrence->interval);
							$xml->writeElement('until', $until);
							break;

						case 'weekly':
							$bydays = explode(',', $recurrence->byday);
							$BYDAY = [];
							foreach ($bydays as $day) {
								$BYDAY[] = $days[$day];
							}
							$BYDAY = implode(',', $BYDAY);
							$xml->writeElement('freq', 'WEEKLY');
							$xml->writeElement('byday', $BYDAY);
							$xml->writeElement('interval', $recurrence->interval);
							$xml->writeElement('until', $until);
							break;

						case 'monthly':
							$xml->writeElement('freq', 'MONTHLY');
							$xml->writeElement('byday', $recurrence->byweekno . $days[$recurrence->byday]);
							$xml->writeElement('interval', $recurrence->interval);
							$xml->writeElement('until', $until);
							break;
					}

					$xml->endElement();		// recur
					$xml->endElement();		// rrule
				}
			}

			$xml->endElement();		// vevent
		}
		$xml->endElement();		// components

		// close calendar and end
		$xml->endElement();		// vcalendar
		$xml->endElement();		// iCalendar
		$xml->flush();
		exit;
	}

	/**
	* export data in standardised iCal format
	* @param EM_Events $EM_Events
	*/
	protected function exportICal($EM_Events) {
		if (isset($_REQUEST['plaintext']) && $_REQUEST['plaintext'] === '1') {
			header('Content-Type: text/plain; charset=utf-8');
		}
		else {
			header('Content-Type: text/calendar; charset=utf-8');
			header('Content-Disposition: attachment; filename="events.ics"');
		}

		nocache_headers();

		// character conversion arrays
		$charFrom = ['\\', ';', ',', "\n", "\t"];
		$charTo = ['\\\\', '\;', '\,', '\\n', '\\t'];

		// output header
		echo "BEGIN:VCALENDAR\n";
		echo "VERSION:2.0\n";
		echo "METHOD:PUBLISH\n";
		echo "CALSCALE:GREGORIAN\n";
		echo "PRODID:-//Events Manager//1.0//EN\n";

		// loop over events to export
		foreach ($EM_Events as $EM_Event) {

			// build array of iCalendar lines, and start event
			$ics = [];
			echo "BEGIN:VEVENT\n";

			// manufacture a unique ID
			$ics[] = 'UID:' . get_em_unique_id($EM_Event);

			// add link to event post
			$ics[] = 'URL:' . $EM_Event->output('#_EVENTURL');

			// use the event name as the summary
			$ics[] = 'SUMMARY:' . str_replace($charFrom, $charTo, $EM_Event->event_name);

			// if event has a location, get the location name
			$location = $EM_Event->get_location();
			if (is_object($location)) {
				if (empty($location->location_name)) {

					$parts = [
						$location->location_address,
						$location->location_town,
						$location->location_state,
						$location->location_postcode,
						$location->get_country(),
					];
					$address = implode(', ', array_filter($parts, 'strlen'));

					$ics[] = 'LOCATION:' . str_replace($charFrom, $charTo, $address);
				}
				else {
					$ics[] = 'LOCATION:' . str_replace($charFrom, $charTo, $location->location_name);
				}
			}

			// get the start, end and last modified dates/times
			// TODO: verify that gmt_offset works when switching between real time and Daylight Stupid Time
			$gmtOffset = HOUR_IN_SECONDS * get_option('gmt_offset');
			$ics[] = 'DTSTART:' . date('Ymd\THis\Z', $EM_Event->start - $gmtOffset);
			$ics[] = 'DTEND:' . date('Ymd\THis\Z', $EM_Event->end - $gmtOffset);
			$ics[] = 'DTSTAMP:' . date('Ymd\THis\Z', strtotime($EM_Event->post_modified_gmt));

			// get categories as comma-separated list
			$cats = $EM_Event->get_categories()->categories;
			if (count($cats) > 0) {
				$categories = [];
				foreach ($cats as $cat) {
					$categories[] = str_replace($charFrom, $charTo, $cat->output('#_CATEGORYNAME'));
				}
				$ics[] = 'CATEGORIES:' . implode(',', $categories);
			}

			// get recurring events in iCalendar format
			$recurrence = '';
			if (!$EM_Event->is_individual()) {
				$recurrence = $EM_Event->get_event_recurrence();
				if (!empty($recurrence)) {
					$days = ['SU','MO','TU','WE','TH','FR','SA'];
					$until = date('Ymd\THis\Z', $recurrence->end - $gmtOffset);

					switch ($recurrence->freq) {
						case 'daily':
							$ics[] = "RRULE:FREQ=DAILY;INTERVAL={$recurrence->interval};UNTIL=$until";
							break;

						case 'weekly':
							$bydays = explode(',', $recurrence->byday);
							$BYDAY = [];
							foreach ($bydays as $day) {
								$BYDAY[] = $days[$day];
							}
							$BYDAY = implode(',', $BYDAY);
							$ics[] = "RRULE:FREQ=WEEKLY;BYDAY=$BYDAY;INTERVAL={$recurrence->interval};UNTIL=$until";
							break;

						case 'monthly':
							$ics[] = "RRULE:FREQ=MONTHLY;BYDAY={$recurrence->byweekno}{$days[$recurrence->byday]};INTERVAL={$recurrence->interval};UNTIL=$until";
							break;
					}
				}
			}

			// output lines wrapped to 75 characters per RFC-5545
			foreach ($ics as $line) {
				if (strpos($line, ' ') === FALSE) {
					// cut unspaced string
					echo wordwrap("$line\n", 75, "\n\t", TRUE);
				}
				else {
					// preserve space where word was wrapped
					echo wordwrap("$line\n", 75, "\n\t ", TRUE);
				}
			}

			// close event
			echo "END:VEVENT\n";
		}

		// close calendar
		echo "END:VCALENDAR\n";
		exit;
	}

	/**
	* export data in Event Espresso format
	* @param EM_Events $EM_Events
	*/
	public function exportEventEspresso($EM_Events) {
		if (isset($_REQUEST['plaintext']) && $_REQUEST['plaintext'] === '1') {
			header('Content-Type: text/plain; charset=utf-8');
		}
		else {
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="events.csv"');
		}

		nocache_headers();

		// send header row
		echo "0,event_name,event_desc,address,city,state,country,zip,phone,display_desc,event_identifier,start_date,end_date,start_time,end_time,reg_limit,event_cost,allow_multiple,additional_limit,send_mail,is_active,conf_mail,registration_start,registration_end\r\n";

		// loop over events to export
		foreach ($EM_Events as $EM_Event) {
			echo '1,';

			echo text_to_csv($EM_Event->event_name), ',';							// event_name
			echo text_to_csv(preg_replace('/\s+/', ' ', strip_tags($EM_Event->post_content))), ',';				// event_desc

			$location = $EM_Event->get_location();
			if (is_object($location)) {
				echo text_to_csv($location->location_address), ',';					// address
				echo text_to_csv($location->location_town), ',';						// city
				echo text_to_csv($location->location_state), ',';					// state
				echo text_to_csv($location->get_country()), ',';						// country
				echo text_to_csv($location->location_postcode), ',';					// zip
			}
			else {
				echo ',,,,,';
			}

			echo ',';																	// phone

			echo 'Y,';																	// display_desc
			echo text_to_csv(get_em_unique_id($EM_Event)), ',';						// event_identifier

			$gmtOffset = HOUR_IN_SECONDS * get_option('gmt_offset');
			echo text_to_csv(date('Y-m-d', $EM_Event->start - $gmtOffset)), ',';		// start_date
			echo text_to_csv(date('Y-m-d', $EM_Event->end - $gmtOffset)), ',';		// end_date
			echo text_to_csv(date('H:i:s', $EM_Event->start - $gmtOffset)), ',';		// start_time
			echo text_to_csv(date('H:i:s', $EM_Event->end - $gmtOffset)), ',';		// end_time

			echo '100,';	// reg_limit
			echo '0,';	// event_cost
			echo 'Y,';	// allow_multiple
			echo '0,';	// additional_limit
			echo 'N,';	// send_mail
			echo 'Y,';	// is_active
			echo ',';	// conf_mail
			echo text_to_csv(date('Y-m-d', $EM_Event->start - $gmtOffset)), ',';	// registration_start
			echo text_to_csv($EM_Event->event_rsvp_date), "\r\n";	// registration_end
		}

		exit;
	}
	
        /**
        * export data in Event CSV format
        * @param EM_Events $EM_Events
        */
        public function exportCSV($EM_Events) {
                if (isset($_REQUEST['plaintext']) && $_REQUEST['plaintext'] === '1') {
                        header('Content-Type: text/plain; charset=utf-8');
                }
                else {
                        header('Content-Type: text/csv; charset=utf-8');
                        header('Content-Disposition: attachment; filename="events.csv"');
                }

                nocache_headers();

                // character conversion arrays
                $charFrom = ['\\', ';', ',', "\n", "\t"];
                $charTo = ['\\\\', '\;', '\,', '\\n', '\\t'];
                // send header row

                echo "uid,summary,dtstart,dtend,dtformat,categories,post_content,location_name,location_address,location_town,location_state,location_postcode,location_country,location_latitude,location_longitude\r\n";

                foreach ($EM_Events as $EM_Event) {
                        echo $EM_Event->event_id, ',';                         // event_identifier

                        echo text_to_csv($EM_Event->event_name), ',';          // event_summary
                        $gmtOffset = HOUR_IN_SECONDS * get_option('gmt_offset');
                        echo text_to_csv(date('Y-m-d', $EM_Event->start - $gmtOffset)), ',';            // start_date
                        echo text_to_csv(date('Y-m-d', $EM_Event->end - $gmtOffset)), ',';              // end_date
                        echo text_to_csv("Y-m-d"), ',';         // dtformat
                        // get categories as comma-separated list
                        $cats = $EM_Event->get_categories()->categories;
                        if (count($cats) > 0) {
                                $categories = [];
                                foreach ($cats as $cat) {
                                        $categories[] = str_replace($charFrom, $charTo, $cat->output('#_CATEGORYNAME'));
                                }
                                echo text_to_csv(implode(',', $categories)), ',';
                        }
                        else {
                                echo ',';
                    }
                        echo text_to_csv(preg_replace('/\s+/', ' ', strip_tags($EM_Event->post_content))), ',';                         // event_desc

                        $location = $EM_Event->get_location();
                        if (is_object($location)) {
                                echo text_to_csv($location->location_name), ',';// address
                                echo text_to_csv($location->location_address), ',';                                     // address
                                echo text_to_csv($location->location_town), ',';// city
                                echo text_to_csv($location->location_state), ',';                                       // state
                                echo text_to_csv($location->location_postcode), ',';                                    // zip
                                echo text_to_csv($location->get_country()), ',';// country
                                echo text_to_csv($location->latitude), ',';    // country
                                echo text_to_csv($location->longitude), ',';   // country
                        }
                        else {
                                echo ',,,,,,,';
                        }

                        echo "\r\n";

                }

                exit;
        }
}
