<?php
/**
 * FacetWP facets used by the Directory archive, registered in code.
 * https://facetwp.com/help-center/developers/hooks/advanced-hooks/facetwp_facets/
 *
 * They show up locked at the bottom of Settings > FacetWP > Facets.
 * Notes:
 *  - A facet saved in the database with the same name wins over the code
 *    version. Delete any manually created duplicates first.
 *  - After changing settings here, run a full Re-index.
 *  - Names come from directory_facet_names() (inc/archive.php) so the
 *    template, JS and these definitions always match.
 *
 * Setting keys follow FacetWP's own export format (Settings > FacetWP >
 * Settings > Import/Export). To double-check a key on your FacetWP version,
 * build that facet once in the admin, export it, and compare.
 */
defined( 'ABSPATH' ) || exit;

/** Facet definitions keyed by role (search, proximity, map, ...). */
function directory_facet_definitions() {
    $n = directory_facet_names();

    $defs = array(

        'search' => array(
            'label'            => 'Find your provider',
            'name'             => $n['search'],
            'type'             => 'search',
            'search_engine'    => '',      // WP default search
            'enable_relevance' => 'yes',
            'placeholder'      => 'Find your provider',
            'auto_refresh'     => 'no',    // the "Search" button refreshes
        ),

        'proximity' => array(
            'label'          => 'Location',
            'name'           => $n['proximity'],
            'type'           => 'proximity',
            'source'         => 'cf/directory_lat',
            'source_other'   => 'cf/directory_lng',
            'unit'           => 'mi',
            'radius_ui'      => 'dropdown', // mirrored by the "Distance" pill
            'radius_options' => '5, 10, 25, 50, 100, 250',
            'radius_min'     => '1',
            'radius_max'     => '250',
            'radius_default' => '25',
            'placeholder'    => 'Choose Location',
        ),

        'category' => array(
            'label'           => 'Category',
            'name'            => $n['category'],
            'type'            => 'dropdown',
            'source'          => 'tax/directory_cat',
            'label_any'       => 'Category',
            'parent_term'     => '',
            'modifier_type'   => 'off',
            'modifier_values' => '',
            'hierarchical'    => 'yes',
            'orderby'         => 'display_value',
            'count'           => '-1',     // all terms
        ),

        'services' => array(
            'label'           => 'Speciality',
            'name'            => $n['services'],
            'type'            => 'fselect',
            'source'          => 'tax/directory_tag',
            'label_any'       => 'Speciality',
            'parent_term'     => '',
            'modifier_type'   => 'off',
            'modifier_values' => '',
            'hierarchical'    => 'no',
            'multiple'        => 'yes',
            'ghosts'          => 'no',
            'preserve_ghosts' => 'no',
            'operator'        => 'or',     // any of the selected services
            'orderby'         => 'display_value',
            'count'           => '-1',
        ),

        'sort' => array(
            'label'         => 'Sort by',
            'name'          => $n['sort'],
            'type'          => 'sort',
            'default_label' => 'Latest',   // = main query order (date DESC)
            'sort_options'  => array(
                array(
                    'label'   => 'Name (A–Z)',
                    'name'    => 'name_asc',
                    'orderby' => array( array( 'key' => 'title', 'order' => 'ASC', 'type' => 'CHAR' ) ),
                ),
                array(
                    'label'   => 'Oldest',
                    'name'    => 'oldest',
                    'orderby' => array( array( 'key' => 'date', 'order' => 'ASC', 'type' => 'CHAR' ) ),
                ),
                array(
                    // Only meaningful with a location: FacetWP orders post__in by distance.
                    'label'   => 'Nearest',
                    'name'    => 'nearest',
                    'orderby' => array( array( 'key' => 'post__in', 'order' => 'ASC', 'type' => 'CHAR' ) ),
                ),
            ),
        ),

        'map' => array(
            'label'          => 'Map',
            'name'           => $n['map'],
            'type'           => 'map',
            'source'         => 'cf/directory_lat',
            'source_other'   => 'cf/directory_lng',
            'map_design'     => 'default', // style via Map ID (Google Cloud), not JSON
            'cluster'        => 'yes',
            'ajax_markers'   => 'no',
            'limit'          => 'all',     // "Show all results", not just the current page
            'map_width'      => '100%',
            'map_height'     => '',        // height comes from archive.css
            'min_zoom'       => '3',
            'max_zoom'       => '20',
            'default_lat'    => '39.8283', // USA center, used when there are no markers
            'default_lng'    => '-98.5795',
            'default_zoom'   => '4',
            'marker_content' => '',        // built by the facetwp_map_marker_content hook
        ),

        'pager' => array(
            'label'      => 'Pager',
            'name'       => $n['pager'],
            'type'       => 'pager',
            'pager_type' => 'numbers',
            'inner_size' => '2',
            'dots_label' => '…',
            'prev_label' => '‹',
            'next_label' => '›',
        ),
    );

    return apply_filters( 'directory_facet_definitions', $defs );
}

add_filter( 'facetwp_facets', function ( $facets ) {
    $existing = wp_list_pluck( $facets, 'name' );

    foreach ( directory_facet_definitions() as $facet ) {
        // A saved (unlocked or hand-made) facet with this name takes priority.
        if ( in_array( $facet['name'], $existing, true ) ) {
            continue;
        }
        $facets[] = $facet;
    }
    return $facets;
} );

/**
 * Labels for FacetWP's map filtering button. Applied from JS
 * (FWP_JSON.map.filterText / resetText) so they work regardless of the
 * setting key names in the installed FacetWP version.
 */
function directory_map_button_labels() {
    return apply_filters( 'directory_map_button_labels', array(
        'filter' => 'Search this area',
        'reset'  => 'Reset map area',
    ) );
}
