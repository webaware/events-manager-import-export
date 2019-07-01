# Events Manager Import Export

Basic import and export function for [Events Manager](https://wordpress.org/plugins/events-manager/).

## Example Import CSV

A CSV imported into Wordpress should contain the following columns:

-    uid - a unique number representing this event
-    summary - title of the event
-    dtstart - start date and time of the event (in format matching the `dtformat` column)
-    dtend - end date and time of the event (in format matching the `dtformat` column)
-    dtformat - format of the `dtstart` and `dtend` columns in the [PHP date() format](http://php.net/manual/en/function.date.php)
-    categories - a comma-separated list of categories that the event should be added to
-    post_content - description of the event
-    location_name - name of the location (optional)
-    location_address - street address of the location
-    location_town - city or town portion of the location address
-    location_state - state portion of the address
-    location_postcode - zipcode portion of the address
-    location_country - country portion of the address
-    location_latitude - latitude for the location (optional)
-    location_longitude - longitude for the location (optional)

Below is an example row:

```
"uid","summary","dtstart","dtend","dtformat","categories","post_content","location_name","location_address","location_town","location_state","location_postcode","location_country","location_latitude","location_longitude"
"233","My Example Event","2016-06-16 11:00:00","2016-06-16 21:00:00","Y-m-d H:i:s","My Category 1,My Category 2","This is a description of the event.","The White House","1600 Pennsylvania Avenue","Washington","DC","20500","US","38.89761","-77.03673"
```

## Note from author

Although I never officially released this plugin, it seems to have leaked out and become a part of quite a few websites. I figure that means I ought to get it up and onto [GitHub](https://github.com/webaware/events-manager-import-export) where people can find it and report bugs.

I probably won't be releasing this on WordPress.org because there's already a pretty good plugin for synchronising events between websites:

* [Events Manager ESS](https://wordpress.org/plugins/events-manager-ess/) -- recommended for sychronising events between websites

However, for those who want to continue using this plugin, please feel free to lodge issues and create pull requests. I can't promise that I'll address them in a timely fashion, but I'll try my best.

[Donations are always welcome](http://shop.webaware.com.au/donations/?donation_for=Events+Manager+Import+Export) :smiley:
