=== Beer Festival Tap List ===
Contributors: beerfestivaltaplist
Tags: beer, festival, tap list, events
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Real-time tap list management for beer festivals.

== Description ==

Beer Festival Tap List lets you manage a list of beers and assign them to
numbered taps, with a `[beer_tap_list]` shortcode that displays a
live-updating tap list on the front end.

== Changelog ==

= 2.0.0 =
* Fixed a fatal error on plugin uninstall caused by a missing uninstall.php.
* Fixed the tap list stylesheet not loading on the default (non-iframe) shortcode display.
* Fixed an XSS vulnerability in the Tap Management admin screen (unescaped beer details output).
* Removed dead code: duplicate empty class-public.php, unused assets directory, unused beer_style taxonomy references, unused "Layout" setting.
* Completed and wired up the concurrent-edit activity notification feature in Tap Management.
* Vendored SweetAlert2 locally instead of loading it from a CDN.
* Replaced admin-ajax.php handlers with WP REST API endpoints under beer-festival-tap-list/v1.

= 1.0.0 =
* Initial release.
