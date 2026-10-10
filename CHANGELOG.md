# Changelog

## 2.1.0 – 2026-10-10

Upgrading from 2.0 is automatic: upload the new theme zip, choose **Replace current with uploaded**,
then open any admin page. Rig Link is unchanged (2.0).

### Added
- **Propagation page** (`[hamshack_propagation]`):
  - live **MUF map** from ionosondes (KC2G / GIRO) with *open / marginal / closed* chips for each HF band,
  - **best times from my QTH** – continent × UTC-hour heat map from your own log, per band,
  - **space weather** – NOAA planetary K index, 3-day chart, aurora alert at Kp ≥ 5,
  - **weather at the shack** – Open-Meteo, thunderstorm and gust warnings, Blitzortung link.
- **Automatic contest calendar** – daily import of the next 8 days from the WA7BNM Contest Calendar,
  include/exclude keywords, *I'm taking part* switch (row action and side box); imported contests stay
  hidden from your calendar until you enter them; old ones are cleaned up and deleted ones never return.
- **Live contest scoreboard** (`[hamshack_contest]`, also on the home page) – QSOs, rate/h, last hour,
  DXCC, CQ zones, bands and last 10 QSOs, refreshed every minute.
- **Notifications** to Discord, Telegram and a generic JSON web hook (Zapier / Make / IFTTT): on air,
  new post, new DXCC, spotted on the DX cluster, geomagnetic storm, thunderstorm, sked request.
  Test button, background sending, throttling, no `@everyone` pings.
- **Club Log** – Most Wanted ranks and *NEW ONE* / *new band* marks on DX-cluster spots, an
  *Only new for me* filter, and optional real-time upload of new QSOs (paused for 24 h after a login error).
- **NEW ONE / new grid badges** in the public logbook.
- **QSL card wall** (`[hamshack_qsl_wall]`) – front/back pictures of received cards on each QSO,
  flip on hover, lightbox, thumbnails in the log; adding a card marks the QSO as QSL received.
- **Sked booking** (`[hamshack_sked]`) – public request form, admin list with *Accept* (creates an
  event, e-mails a Google Calendar link and an .ics file) and *Decline*.
- **Repeater map** (`[hamshack_repeaters]`) – ukrepeater.net in the UK, your own list anywhere.
- **Web SDR** (`[hamshack_sdr]`) and **APRS position** (`[hamshack_aprs]`) sections on the Live page.
- **Installable app (PWA)** – manifest, icons, service worker with offline copies of recently visited
  pages and an offline page; shortcuts to Live, Logbook and Calendar.
- **Light / dark switch** in the top bar (remembered per browser; header, footer and maps stay dark).
- **Speed** – WebP for new uploads, deferred theme scripts, emoji script removed (each can be switched off).
- **Ham Shack → Integrations** settings screen for all of the above; secrets are masked.
- Polish translation for all new texts.

### Changed
- Public forms (contact, OQRS, newsletter, sked) use a signed time stamp instead of a nonce for
  visitors, so they keep working behind page caches.
- ADIF export code refactored into `hamshack_adif_record()` (used by the Club Log upload).
- Date and time inputs are styled like the other fields.

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
