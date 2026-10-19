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

## Facets (Settings > FacetWP > Facets)

Names must match exactly (or change them with the `directory_facet_names` filter).

| Name | Type | Data source | Settings |
|---|---|---|---|
| `directory_search` | Search | – | Placeholder "Find your provider"; Auto refresh **off** (the Search button refreshes) |
| `directory_proximity` | Proximity | `directory_lat`, Longitude: `directory_lng` | Unit: **Miles**; Radius UI: **Dropdown**; Radius options `5, 10, 25, 50, 100, 250`; Default radius `25`; Placeholder "Choose Location" |
| `directory_category` | Dropdown | `tax/directory_cat` | Label any; "Default label" `Category`; Hierarchical: Yes |
| `directory_services` | fSelect | `tax/directory_tag` | Multi-select: Yes; "Default label" `Speciality` |
| `directory_sort` | Sort | – | Default label `Latest`; options: `Name (A–Z)` → Title ASC; `Nearest` (name `nearest`) → `post__in` ASC |
| `directory_map` | Map | `directory_lat`, Longitude: `directory_lng` | Marker limit: **Show all results**; Marker clustering: On; Width `100%`; Height: leave empty (CSS sets it); Zoom min `3`; Enable filtering button label `Search this area`; Reset button label `Reset map area` |
| `directory_pager` | Pager | – | Pager type: **Page numbers**; Prev/Next on |

Then **Re-index**.

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
