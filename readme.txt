=== Barefoot Engine ===
Contributors: braudypedrosa
Tags: vacation-rental, integrations
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Barefoot API full vacation rental integration.

== Description ==
Barefoot Engine connects WordPress with the Barefoot API for vacation rental workflows.

== Installation ==
1. Upload the plugin zip file through WordPress admin.
2. Activate Barefoot Engine.

== Changelog ==
= 1.0.6 =
* Updated `view_item` tracking to use the property's current daily rate when available.
* Preloaded the booking calendar's first visible price labels from synced rates so prices render immediately while live availability refreshes.
* Fixed date-only property searches so they filter against live availability.

= 1.0.5 =
* Added configurable GA4/GTM booking event tracking for property views, checkout starts, and completed bookings.
* Reused an existing matching Google tag when present and only embedded the configured tag when needed.
* Added a General Tracking settings screen for the booking analytics destination.

= 1.0.4 =
* Updated the default Bedrooms search dropdown to offer Studio, 1, 2, and 3 bedroom options.

= 1.0.3.1 =
* Fixed the listings Clear button so it restores all properties and removes search parameters from the URL.

= 1.0.2 =
* Updated the default search widget with a View dropdown for Golf Course and Poolview searches.
* Added amenity-based view matching so those search options can return matching properties.

= 1.0.0 =
* Production-ready Barefoot property sync, listings, booking widget, checkout, and booking confirmation flows.
* Featured properties slider and Elementor widget support.
* AJAX listings search improvements, native sticky/infinite map behavior, and stay-total pricing updates.

= 0.1.0 =
* Initial scaffold.
