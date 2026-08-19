# Beer Tap List

A WordPress plugin for real-time tap list management at beer festivals. Manage a catalog of beers, assign them to numbered taps, and display a live-updating tap list on the front end.

## Features

- **Beer catalog** — a custom post type for beers (style, brewer, location, IBU, ABV).
- **Tap Management screen** — assign beers to taps from an admin dashboard, with Select2-powered search.
- **Live front-end display** — the `[beer_tap_list]` shortcode renders a tap grid that auto-refreshes via the WordPress REST API, with a "NEW!" badge for recently tapped beers.
- **Concurrent-edit notifications** — when multiple admins manage taps at once, toast notifications flag who changed what.
- **REST API** — `beer-festival-tap-list/v1` namespace (`GET/POST /taps`, `GET /activity`).

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Copy this folder into `wp-content/plugins/`.
2. Activate **Beer Festival Tap List** from the WordPress Plugins screen.
3. Configure tap count, refresh interval, and display mode under **Beer Festival → Settings**.
4. Add beers under **Beer Festival → Add New Beer**, then assign them to taps under **Beer Festival → Tap Management**.
5. Place the `[beer_tap_list]` shortcode on any page to display the live tap list.

See `readme.txt` for the WordPress.org-style changelog.
