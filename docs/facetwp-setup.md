# Directory archive – FacetWP setup

The archive template (`templates/archive-directory.php`) runs on `/directory/`
and on the `directory_cat` / `directory_tag` term pages. FacetWP filters the
main archive query; no shortcode or custom query is needed.

Docs: https://facetwp.com/help-center/

## Requirements

- FacetWP **v4.6+** (built-in Map + Proximity, `facetwp_map_marker_content` hook).
- Do **not** install the legacy "Map facet" add-on.
- Settings > FacetWP > Settings: Google Maps API key set, "Test Google APIs" all
  green (Maps JavaScript API, Geocoding API, Places API (New)), and
  "Use Legacy Proximity facet" **off**.
- The theme's footer calls `wp_footer()`.
- HTTPS (needed for "Locate me").

## Location data

The form stores `"lat,lng"` in `_directory_latlng`. `inc/archive.php` mirrors it
into two numeric fields that FacetWP reads:

| Meta key        | Value            |
|-----------------|------------------|
| `directory_lat` | latitude (float) |
| `directory_lng` | longitude (float)|

Existing listings are backfilled once on the next admin page load
(option `directory_geo_meta_v1`). After saving from the frontend form the
listing is re-indexed with `FWP()->indexer->index( $post_id )`.

## Facets (registered in code)

All 7 facets are registered in `inc/facets.php` with the `facetwp_facets` hook.
They appear locked at the bottom of Settings > FacetWP > Facets; nothing to
create by hand. After pulling the code: **Re-index**.

- If you created any of these facets manually before, **delete them** (a saved
  facet with the same name overrides the code version).
- Change settings in `directory_facet_definitions()`, or with the
  `directory_facet_definitions` filter, then Re-index.
- Map button labels: `directory_map_button_labels()` (applied from JS).

| Name | Type | Data source | Key settings |
|---|---|---|---|
| `directory_search` | Search | – | WP default engine, auto refresh off |
| `directory_proximity` | Proximity | `directory_lat` / `directory_lng` | miles, dropdown radius 5–250, default 25 |
| `directory_category` | Dropdown | `tax/directory_cat` | hierarchical, "Category" label |
| `directory_services` | fSelect | `tax/directory_tag` | multi-select, OR |
| `directory_sort` | Sort | – | Latest (default), Name A–Z, Oldest, Nearest |
| `directory_map` | Map | `directory_lat` / `directory_lng` | all results, clustering, zoom min 3 |
| `directory_pager` | Pager | – | page numbers |

Notes:
- When a location is entered and no sort is chosen, results are ordered nearest
  first automatically (`pre_get_posts` in `inc/archive.php`).
- The Proximity radius dropdown is hidden in the search bar and mirrored as the
  "Distance" pill; FacetWP still reads the original select.
- Rating is TEMPORARILY hard-coded to "4.5 · 85 Reviews" in
  `directory_listing_rating()` (inc/archive.php). Replace it with real data
  through the `directory_listing_rating` filter (return null to hide it).
- Map popup: `templates/parts/map-popup.php`, used both by the
  `facetwp_map_marker_content` hook and the map facet's "Info window content".
- Map marker: `assets/icons/marker.svg` via `markerHtml`; navy by default,
  #B98F5C on hover and while its popup is open (CSS + `src/archive.js`).
- Map styling: FacetWP's map no longer accepts JSON styles. Create a Map ID with
  a style in Google Cloud Console and set it in the `directory_map` facet.

## List / split-map behaviour (`src/archive.js`)

Modelled on https://facetwp.com/demo/state-parks/.

| Action | Result |
|---|---|
| Default | Full-width cards + pager; small map preview with **View Map**. |
| **View Map** | Split view: cards + pager on the left (normal page scroll), map on the right, sticky so it stays in view. Map filtering turns **on**: panning / zooming filters the cards. |
| **Close Map** (same toolbar button) or Esc | Back to full-width cards; map filtering turns **off** and the listing refreshes if the viewport had filtered it. |
| New location in split view | Filtering pauses for that request so the map can zoom to the location, then turns back on. |
| **Search this area** with a location entered | The map area replaces the location: the Proximity location is cleared for that search (both together would AND to "no results"). |
| URL with map bounds (shared link) | Page opens in split view; if it also holds a location, the location is dropped. |
| `TCK_DIRECTORY_MAP_FILTERING = false` (directory.php) | Split view still works, but the map never filters the cards and its "Search this area" button is hidden. Default `true`. Also: `directory_map_filtering` filter. |
| Below 992px | No room for two columns: the map shows above the cards. |

Layout knobs (CSS custom properties on `.directory-archive`):
`--dir-map-share` (map column width, default 0.53), `--dir-split-gap`,
`--dir-offset-top` (fixed site header height in px, e.g. `80px`: the sticky
map sits below it), `--dir-sticky-gap` (space above/below the sticky map).
The sticky map needs no `overflow: hidden` on the theme wrappers around it.

The map stays rendered (invisible) in list view, in the same box it uses in
split view, so it is already fitted to the markers when opened.

## Build

```
npx sass --no-source-map --style=compressed src/archive.scss assets/archive.css
npx esbuild src/archive.js --bundle --minify --format=iife --target=es2020 --outfile=assets/archive.js
```
