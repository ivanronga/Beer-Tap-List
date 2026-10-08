# Beer Tap List

A WordPress plugin for real-time tap list management at beer festivals. Manage a catalog of beers, assign them to numbered taps, and display a live-updating tap list on the front end.

## Features

- **Beer catalog** — a custom post type for beers (style, brewer, location, IBU, ABV, category).
- **Categories and tap zones** — free-form categories; each tap has a fixed zone that sets its colour on the board.
- **Tap Management screen** — assign beers to taps from an admin dashboard, with Select2-powered search and the tap's zone shown next to its number.
- **Live front-end display** — the `[beer_tap_list]` shortcode renders a tap grid that auto-refreshes via the WordPress REST API, with a "NEW!" badge for recently tapped beers. Optional fullscreen iframe mode for a venue screen.
- **Staff beer page** — each beer has a QR-code URL that opens a mobile page to publish the beer to a tap or remove it, with confirmation dialogs.
- **QR codes** — an admin page to preview, download and print QR codes with the beer's details.
- **CSV import / export** — export all beers, edit them in a spreadsheet and re-import with column mapping.
- **Marketing popups** — image ads shown over the board. Interval and duration are global, ads rotate by weight, with a master on/off switch. Desktop shows a side panel, mobile a centred modal, both with a close button.
- **Concurrent-edit notifications** — when several admins manage taps at once, toast notifications flag who changed what.
- **REST API** — `beer-festival-tap-list/v1` namespace (`GET /taps`, `POST /taps/assign`, `POST /taps/category`, `GET /activity`).

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Copy this folder into `wp-content/plugins/`.
2. Activate **Beer Festival Tap List** from the WordPress Plugins screen.
3. Configure tap count, refresh interval, and display mode under **Beer Festival → Settings**.
4. Add beers under **Beer Festival → Add New Beer**, then assign them to taps under **Beer Festival → Tap Management**.
5. Place the `[beer_tap_list]` shortcode on any page to display the live tap list.

## Deployment notes

- **Open tap endpoint.** `POST /taps/assign` is intentionally unauthenticated so staff can publish a beer from a QR code without logging in. Anyone who can reach the site can change taps; use it on a trusted network or add your own protection. Beer pages are `noindex`.
- **Theme.** The board and the staff page are designed for the Hombre theme. The board relies on the theme reloading the page periodically; popup timing survives that by keeping its schedule in `localStorage`.
- **Uninstall.** Deleting the plugin removes its tables, options and temporary data. Beer posts are kept.
- **Fonts.** Figtree is bundled in `includes/public/fonts/` (SIL Open Font License); nothing is loaded from Google by the plugin.

## Releasing and updates

The plugin updates itself from this repository: WordPress checks GitHub for the newest `vX.Y.Z` **tag** and offers it as a normal plugin update (one click, or auto-update). Nothing is built or uploaded per release.

To publish a version:

1. Bump the version in `beer-festival-tap-list.php` (the `Version:` header **and** `BEER_FESTIVAL_VERSION`), then `readme.txt` (`Stable tag` and a changelog entry).
2. Commit, merge to `main`, then `git tag -a vX.Y.Z -m "..."` and `git push origin main vX.Y.Z`.
3. The site shows the update within about 3 hours, or immediately via **Plugins → Check for updates**.

Notes:

- Only plain `vX.Y.Z` tags count; the highest version wins.
- GitHub's tag archive is used as the package. `.gitattributes` `export-ignore` keeps dev-only files out of it, and the updater renames its `Beer-Tap-List-X.Y.Z/` folder to `beer-festival-tap-list/` during the upgrade.
- A folder containing `.git` (a development checkout) is never offered updates, because an update replaces the whole folder. Add `define('BFTL_FORCE_UPDATES', true);` to `wp-config.php` to override.
- The first version that contains the updater (2.19.0) has to be installed by zip once; later versions arrive through WordPress.

See `readme.txt` for the changelog.
