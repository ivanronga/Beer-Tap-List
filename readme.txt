=== Beer Festival Tap List ===
Contributors: beerfestivaltaplist
Tags: beer, festival, tap list, events
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.13.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Real-time tap list management for beer festivals.

== Description ==

Beer Festival Tap List lets you manage a list of beers and assign them to
numbered taps, with a `[beer_tap_list]` shortcode that displays a
live-updating tap list on the front end.

* Beer catalog with style, brewer, location, IBU, ABV and a category.
* Categories and tap zones, so each tap keeps a fixed zone colour.
* Tap Management screen to assign beers to taps.
* Live, auto-refreshing tap board for screens and phones.
* Staff beer page reached from a QR code, to publish or remove a beer on a tap.
* QR code sheet for printing, plus CSV export and import of the beer list.
* Marketing popups: admin-managed image ads shown over the board on a global
  interval and duration, rotated by weight.

== Installation ==

1. Upload the plugin folder to `wp-content/plugins/` and activate it.
2. Set the number of taps and display mode under Beer Festival > Settings.
3. Add beers, then assign them to taps under Beer Festival > Tap Management.
4. Put the `[beer_tap_list]` shortcode on a page to show the board.

== Frequently Asked Questions ==

= Who can change taps? =

The tap assignment endpoint is intentionally open so staff can publish a beer
from a QR code without logging in. Run the site on a trusted network or add
your own protection if that is not acceptable.

= What happens when I delete the plugin? =

Its tables, options and temporary data are removed. Your Beer posts are kept.

== Changelog ==

= 2.13.0 =
* Theme-agnostic frontend: the board, staff beer page and popups look the same in any theme (verified in Hombre and Twenty Twenty-Four).
* Board styles are scoped under a single root element and no longer leak into, or inherit from, the active theme.
* The staff beer page loads only the plugin's own assets.
* Popups are self-contained and no longer depend on theme styles.

= 2.12.0 =
* Self-hosted the Figtree font; the plugin no longer requests anything from Google.
* Uninstall now removes the popups table, all settings and import leftovers (beers are kept).
* Beer pages are marked noindex.
* The activity log now records tap changes made through the REST API (QR publish, Tap Management Save).
* The board and popup assets load only on pages that show the shortcode.
* The beer meta box checks the user may edit the beer and unslashes input.
* Removed debug logging, dead code and duplicate hook registration.
* Added text domain loading and a languages folder.

= 2.11.x =
* Marketing popups: one global interval and duration, a master on/off switch, weighted rotation with up/down ordering, fade in and out.
* Mobile tap list: brewer and location columns size to their content.
* "No beer assigned" text is yellow; tap numbers are centred in their circles.

= 2.10.x =
* Added marketing popups (Media Library image ads, desktop side panel, mobile modal, close button).

= 2.9.x =
* Redesigned the staff beer page: tap picker, confirmation dialogs, loading indicator.

= 2.8.x =
* CSV export and import of beers, extra beer details on the printable QR page, "Delete all beers" action.
* Removed the rate limit on tap assignment; tap zone shown on the Tap Management page.

= 2.1.0 - 2.7.x =
* Beer categories, tap zones, QR code page with preview, single beer pages.
* Front-end tap list redesign for desktop and mobile.

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
