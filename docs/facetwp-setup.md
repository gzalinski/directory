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
- "Rating" is not wired: there is no review data yet. Return
  `array( 'avg' => 4.5, 'count' => 83 )` from the `directory_listing_rating`
  filter to show it on cards and map popups.
- Map styling: FacetWP's map no longer accepts JSON styles. Create a Map ID with
  a style in Google Cloud Console and set it in the `directory_map` facet.

## List / map behaviour (`src/archive.js`)

| Action | Result |
|---|---|
| **View Map** | Cards, pager and sort are hidden; the full Map facet is shown; map filtering is turned **on** (the viewport filters results, count shown bottom-left). |
| **Close Map** (top right) or Esc | Back to cards + pager; map filtering turned **off**; the listing refreshes if the viewport had filtered it. |
| New location in map view | Filtering is paused for that request so the map can zoom to the new location, then turned back on. |
| URL with map bounds (shared link) | Page opens directly in map view. |

The Map facet stays rendered (invisible, full size) in list view, because FacetWP
requires it on the page and so the map is already fitted when opened.

## Build

```
npx sass --no-source-map --style=compressed src/archive.scss assets/archive.css
npx esbuild src/archive.js --bundle --minify --format=iife --target=es2020 --outfile=assets/archive.js
```
