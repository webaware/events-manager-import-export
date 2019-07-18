# Events Manager Import Export

## Changelog

See GitHub for [the complete changelog](https://github.com/webaware/events-manager-import-export/blob/master/changelog.md).

### 0.0.12, soon...

* changed: requires PHP 5.6+ (recommend PHP 7.2+)
* changed: Google Maps lookup is removed for now, will return soon as an Ajax-driven process post-import
* changed: parsecsv upgraded to v1.0.0
* merged: [@asmartin pull request](https://github.com/webaware/events-manager-import-export/pull/1); thanks!
  - post_id is now required in the database, so add it
  - verify new location matches existing by comparing postcodes
  - added support for custom datetime formats in the import CSV using the dtformat column
  - added support for specifying start and end times (rather than hardcoding 00:00:00)
  - added example CSV to the README

### 0.0.11, 2016-02-09

* fix bug with geolocation overwriting location object for new locations
* refactor .xcs location creation to work like .csv location creation

### 0.0.10, 2016-01-31

* attempt to set location coordinates on import

### 0.0.9, 2016-01-30

* the plugin can receive update notifications and auto-update from within WordPress

### 0.0.8, 2016-01-30

* fix category imports for current Events Manager
* fix WordPress 4.4 import error for missing post_excerpt
