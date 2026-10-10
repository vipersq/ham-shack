# Changelog

## 2.0.0 – 2026-10-10

A big feature release. Upgrading from 1.x is automatic: upload the new theme zip, choose
**Replace current with uploaded**, then open any admin page.

### Added
- **QSO map** (`[hamshack_qso_map]`) – the whole logbook on a world map: great-circle paths,
  band/mode filters, grid-square view, day/night overlay, callsign search, longest contacts,
  detailed coastline when zoomed in.
- **Stats & awards** (`[hamshack_awards]`) – DXCC, WAC, WAZ, ITU, WAS, British Isles, grid squares
  and VUCC progress (worked / confirmed), DXCC × band table, charts per month, band, mode,
  hour of day, country and year.
- **Automatic QSO enrichment** – DXCC entity, continent, CQ/ITU zone, position and distance for
  every QSO (locator → QRZ lat/lon → AD1C country file). Existing QSOs are back-filled in the
  background; the country file updates itself monthly.
- **eQSL cards** – visitors download a personal QSL card (PNG) for each of their QSOs.
- **OQRS** – online requests for paper QSL cards (bureau / direct / swap) with an admin list,
  e-mail notifications and *Mark card as sent* (updates QSL sent, notifies the requester,
  deletes the postal address).
- **Live page** (`[hamshack_live]`):
  - live **FT8/FT4 decodes** from WSJT-X with a map of stations heard and “calling me” highlight,
  - **greyline** map with UTC/local clocks, sunrise and sunset,
  - **PSK Reporter** – who heard me in the last 6 hours,
  - **DX cluster** with band and CW/SSB/digital filters; own spots highlighted and remembered.
- **QSOs logged in WSJT-X** go straight into the logbook (QRZ sync skips the duplicates).
- **Satellites** (`[hamshack_satellites]`) – 48-hour pass predictions over your station, live
  positions, footprints and a sky plot; CelesTrak with AMSAT fallback, SGP4 in the browser.
- **Activations** – POTA / SOTA / WWFF / IOTA post type with map, photos and automatic QSO counts
  from `MY_*_REF`.
- **Gallery** (`[hamshack_gallery]`) – photos marked in the Media Library, albums, bulk action,
  lightbox (also for images in posts).
- **Learn** – Morse trainer (Koch lessons, callsign copy with adaptive speed, text to Morse,
  chart) and a 40-question Foundation-style quiz.
- **Newsletter** – double opt-in, box under posts, widget and `[hamshack_subscribe]`; new posts are
  e-mailed in batches after a short delay; per-post opt-out; CSV export; one-click unsubscribe.
- **SEO** – meta description, Open Graph / Twitter cards, JSON-LD; auto-disabled when an SEO
  plugin is active; private post types kept out of the sitemap.
- **Backup & export** – ZIP with ADIF log, settings, events, activations, QSL requests and
  subscribers (no secrets); restore settings from JSON.
- **Polish translation** (`languages/pl_PL`) and **Polylang** support: EN | PL switcher and
  translatable station texts.
- New **Radio** menu with Live, QSO Map, Stats & Awards, Satellites, Activations, Gallery, Learn;
  new home-page section with the greyline map and live FT8; new quick-link tiles.
- Station settings: exact **latitude / longitude** for maps, distances and satellite passes;
  option to hide the live section on the home page.

### Rig Link 2.0
- Listens to **WSJT-X** (UDP 2237, localhost only; multicast supported for JTAlert/GridTracker).
- Sends decodes in batches and QSOs logged in WSJT-X; falls back to the WSJT-X dial frequency
  when HRD is not running.
- `1-TEST.bat` checks HRD and WSJT-X separately; `HrdEnabled=0` for WSJT-X-only stations.

### Changed
- ADIF export now includes DXCC, continent, zones, state and `MY_POTA/SOTA/WWFF_REF`.
- Contact form returns to the page it was sent from.

### Security
- QSL-request and subscriber screens require the administrator capability.
- Password-protected posts never leak into meta descriptions.
- JSON-LD is HTML-safe; deleted QSOs disappear from the public map and stats at once.

## 1.0.0 – 2026-09-25

First public release: setup wizard with callsign-based logo and QSL card, on-air status,
events calendar with iCal, QSO logbook with ADIF import/export, QRZ.com logbook sync,
live rig frequency from Ham Radio Deluxe (Rig Link), QRZ.com biography generator,
N0NBH band conditions, station page, contact form and widgets.
