<?php
/**
 * Directory archive: template loader, main query, assets and FacetWP
 * integration (listing + map view + proximity).
 *
 * Used for the CPT archive (/directory/) and the directory_cat /
 * directory_tag term archives. FacetWP filters the main archive query,
 * so no custom WP_Query is needed here.
 *
 * Facets are created in Settings > FacetWP > Facets (see docs/facetwp-setup.md).
 * Their names are configurable through the `directory_facet_names` filter.
 */
defined( 'ABSPATH' ) || exit;

/** Listings per page in list view. */
const DIRECTORY_PER_PAGE = 10;

/** Is the current request (or the given query) a Directory archive? */
function directory_is_archive( $query = null ) {
    $query = $query ? $query : $GLOBALS['wp_query'];
    if ( ! $query instanceof WP_Query ) {
        return false;
    }
    return $query->is_post_type_archive( 'directory' ) || $query->is_tax( array( 'directory_cat', 'directory_tag' ) );
}

/** FacetWP facet names used by the archive template and JS. */
function directory_facet_names() {
    return apply_filters( 'directory_facet_names', array(
        'search'    => 'directory_search',
        'proximity' => 'directory_proximity',
        'map'       => 'directory_map',
        'category'  => 'directory_category',
        'services'  => 'directory_services',
        'sort'      => 'directory_sort',
        'pager'     => 'directory_pager',
    ) );
}

/** Render a facet by its role key ('map', 'pager', ...). Empty when FacetWP is inactive. */
function directory_facet( $role ) {
    $names = directory_facet_names();
    if ( empty( $names[ $role ] ) || ! function_exists( 'facetwp_display' ) ) {
        return '';
    }
    return facetwp_display( 'facet', $names[ $role ] );
}

/* -------------------------------------------------------------------------
 * Template + main query
 * ---------------------------------------------------------------------- */

add_filter( 'template_include', function ( $template ) {
    if ( directory_is_archive() ) {
        return TCK_DIRECTORY_PATH . 'templates/archive-directory.php';
    }
    return $template;
} );

add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! directory_is_archive( $query ) ) {
        return;
    }
    $query->set( 'post_type', 'directory' );
    $query->set( 'posts_per_page', DIRECTORY_PER_PAGE );
    // "Latest" by default; ID as tie-breaker so paging never repeats a listing.
    $query->set( 'orderby', array( 'date' => 'DESC', 'ID' => 'DESC' ) );
} );

/**
 * When a location is entered in the Proximity facet and the visitor has not
 * picked a sort option, order results nearest first. FacetWP already puts the
 * matching IDs into post__in ordered by distance.
 * https://facetwp.com/help-center/facets/facet-types/proximity/
 */
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! function_exists( 'FWP' ) || empty( FWP()->facet->facets ) ) {
        return;
    }
    $is_listing = true === $query->get( 'facetwp' ) || ( $query->is_main_query() && directory_is_archive( $query ) );
    if ( ! $is_listing ) {
        return;
    }

    $names      = directory_facet_names();
    $prefix     = FWP()->helper->get_setting( 'prefix' );
    $using_sort = ! empty( FWP()->facet->http_params['get'][ $prefix . $names['sort'] ] );
    if ( $using_sort ) {
        return;
    }

    foreach ( FWP()->facet->facets as $facet ) {
        if ( 'proximity' === $facet['type'] && ! empty( $facet['selected_values'] ) ) {
            $query->set( 'orderby', array( 'post__in' => 'ASC' ) );
            return;
        }
    }
}, 1000 );

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', function () {
    if ( ! directory_is_archive() ) {
        return;
    }
    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1' );
    wp_enqueue_style( 'directory-archive', TCK_DIRECTORY_URL . 'assets/archive.css', array(), filemtime( TCK_DIRECTORY_PATH . 'assets/archive.css' ) );
    wp_enqueue_script( 'directory-archive', TCK_DIRECTORY_URL . 'assets/archive.js', array(), filemtime( TCK_DIRECTORY_PATH . 'assets/archive.js' ), true );
} );

/* -------------------------------------------------------------------------
 * Card data (shared by the listing card and the map info window)
 * ---------------------------------------------------------------------- */

/** Format a distance in miles like the single page does: "<0.1", "2.3", "14". */
function directory_format_miles( $miles ) {
    $miles = (float) $miles;
    if ( $miles < 0.1 ) {
        return '<0.1 miles';
    }
    return ( $miles < 10 ? number_format_i18n( $miles, 1 ) : number_format_i18n( round( $miles ) ) ) . ' miles';
}

/** Avatar <img> (or placeholder icon) at the given square size. */
function directory_avatar_html( $post_id, $size = 56 ) {
    $url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
    if ( $url ) {
        return sprintf(
            '<img src="%s" alt="%s" width="%d" height="%d" loading="lazy">',
            esc_url( $url ),
            esc_attr( get_the_title( $post_id ) ),
            (int) $size,
            (int) $size
        );
    }
    return '<span class="dir-avatar-empty" aria-hidden="true"><i class="fa-regular fa-user"></i></span>';
}

/**
 * Everything a card needs, in one place.
 *
 * `distance` is the FacetWP Proximity distance (miles) when a location is
 * entered, otherwise null; the archive JS can then fill it client side.
 */
function directory_card_data( $post_id ) {
    $address = get_post_meta( $post_id, '_directory_address', true );
    $short   = get_post_meta( $post_id, '_directory_short_desc', true );
    if ( ! $short ) {
        $short = wp_trim_words( (string) get_post_meta( $post_id, '_directory_full_desc', true ), 30, '…' );
    }
    $email = get_post_meta( $post_id, '_directory_email', true );
    $phone = get_post_meta( $post_id, '_directory_phone', true );

    $distance = function_exists( 'facetwp_get_distance' ) ? facetwp_get_distance( $post_id ) : false;

    // "Message Seller": email first, phone as a fallback.
    $message_url = '';
    if ( $email ) {
        $message_url = 'mailto:' . $email . '?subject=' . rawurlencode( get_the_title( $post_id ) );
    } elseif ( $phone ) {
        $message_url = 'tel:+1' . preg_replace( '/\D/', '', $phone );
    }

    return array(
        'id'          => $post_id,
        'name'        => get_the_title( $post_id ),
        'url'         => get_permalink( $post_id ),
        'category'    => directory_primary_category( $post_id ),
        'city'        => directory_city_state( $address, $post_id ),
        'latlng'      => get_post_meta( $post_id, '_directory_latlng', true ),
        'short'       => $short,
        'distance'    => false !== $distance && null !== $distance ? (float) $distance : null,
        'message_url' => $message_url,
        // Reviews do not exist yet: return array( 'avg' => 4.5, 'count' => 83 ) from this filter once they do.
        'rating'      => apply_filters( 'directory_listing_rating', null, $post_id ),
    );
}

/** Rating line ("★ 4.5 · 83 Reviews"), empty until review data exists. */
function directory_rating_html( $rating ) {
    if ( empty( $rating['count'] ) ) {
        return '';
    }
    return sprintf(
        '<div class="dir-rating"><i class="fa-solid fa-star" aria-hidden="true"></i> %s <span class="dir-dot"></span> <span class="dir-rating__count">%s</span></div>',
        esc_html( number_format_i18n( (float) $rating['avg'], 1 ) ),
        esc_html( sprintf( _n( '%s Review', '%s Reviews', (int) $rating['count'] ), number_format_i18n( (int) $rating['count'] ) ) )
    );
}

/** "2.3 miles · Wellington, FL" line. The distance slot stays in the DOM so JS can fill it. */
function directory_location_html( $card ) {
    $has = null !== $card['distance'];
    $html  = '<div class="dir-loc">';
    $html .= '<i class="fa-solid fa-location-dot" aria-hidden="true"></i>';
    $html .= '<span class="dir-loc__distance"' . ( $has ? ' data-server' : ' hidden' ) . '>';
    $html .= '<strong data-distance>' . ( $has ? esc_html( directory_format_miles( $card['distance'] ) ) : '' ) . '</strong>';
    $html .= $card['city'] ? '<span class="dir-dot"></span>' : '';
    $html .= '</span>';
    $html .= esc_html( $card['city'] );
    $html .= '</div>';
    return $html;
}

/* -------------------------------------------------------------------------
 * FacetWP map: marker info window (FacetWP v4.6+)
 * https://facetwp.com/help-center/facets/facet-types/map/advanced-map-customizations/customize-marker-info-windows/
 * ---------------------------------------------------------------------- */

add_filter( 'facetwp_map_marker_content', function ( $content, $post_id, $facet_name ) {
    $names = directory_facet_names();
    if ( $facet_name !== $names['map'] || 'directory' !== get_post_type( $post_id ) ) {
        return $content;
    }

    $card = directory_card_data( $post_id );
    ob_start();
    ?>
    <div class="dir-iw">
        <div class="dir-iw__head">
            <div class="dir-iw__avatar"><?php echo directory_avatar_html( $post_id, 48 ); // phpcs:ignore ?></div>
            <div class="dir-iw__info">
                <div class="dir-iw__title">
                    <strong><?php echo esc_html( $card['name'] ); ?></strong>
                    <?php if ( $card['category'] ) : ?>
                        <span class="dir-dot"></span><span class="dir-pill"><?php echo esc_html( $card['category']->name ); ?></span>
                    <?php endif; ?>
                </div>
                <?php echo directory_rating_html( $card['rating'] ); // phpcs:ignore ?>
                <?php echo directory_location_html( $card ); // phpcs:ignore ?>
            </div>
        </div>
        <a class="dir-btn dir-btn--block" href="<?php echo esc_url( $card['url'] ); ?>">View Profile</a>
    </div>
    <?php
    return ob_get_clean();
}, 10, 3 );

/** Info window sizing + accessible label. */
add_filter( 'facetwp_map_marker_args', function ( $args, $post_id ) {
    if ( 'directory' !== get_post_type( $post_id ) ) {
        return $args;
    }
    $args['infoWindowOptions'] = array(
        'minWidth'  => 280,
        'maxWidth'  => 340,
        'ariaLabel' => get_the_title( $post_id ),
    );
    return $args;
}, 10, 2 );

/* -------------------------------------------------------------------------
 * Geo meta for FacetWP
 *
 * The form stores "lat,lng" in _directory_latlng. FacetWP Map + Proximity
 * read two numeric custom fields, so we mirror them into directory_lat /
 * directory_lng (no underscore: visible in FacetWP's "Data source" list).
 * ---------------------------------------------------------------------- */

/** Write / clear directory_lat + directory_lng from a "lat,lng" string. */
function directory_sync_geo_meta( $post_id, $latlng = null ) {
    if ( null === $latlng ) {
        $latlng = get_post_meta( $post_id, '_directory_latlng', true );
    }
    $parts = array_map( 'trim', explode( ',', (string) $latlng ) );
    $valid = 2 === count( $parts ) && is_numeric( $parts[0] ) && is_numeric( $parts[1] )
        && abs( (float) $parts[0] ) <= 90 && abs( (float) $parts[1] ) <= 180;

    if ( $valid ) {
        update_post_meta( $post_id, 'directory_lat', (float) $parts[0] );
        update_post_meta( $post_id, 'directory_lng', (float) $parts[1] );
    } else {
        delete_post_meta( $post_id, 'directory_lat' );
        delete_post_meta( $post_id, 'directory_lng' );
    }
}

// Keep the mirror in sync whenever _directory_latlng changes (form, admin, REST).
$directory_on_latlng = function ( $meta_id, $post_id, $meta_key, $value ) {
    if ( '_directory_latlng' === $meta_key && 'directory' === get_post_type( $post_id ) ) {
        directory_sync_geo_meta( $post_id, $value );
    }
};
add_action( 'added_post_meta', $directory_on_latlng, 10, 4 );
add_action( 'updated_post_meta', $directory_on_latlng, 10, 4 );
add_action( 'deleted_post_meta', function ( $meta_ids, $post_id, $meta_key ) {
    if ( '_directory_latlng' === $meta_key ) {
        directory_sync_geo_meta( $post_id, '' );
    }
}, 10, 3 );

// One-time backfill for listings saved before the mirror existed.
add_action( 'admin_init', function () {
    if ( get_option( 'directory_geo_meta_v1' ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $ids = get_posts( array(
        'post_type'      => 'directory',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_key'       => '_directory_latlng', // phpcs:ignore
    ) );
    foreach ( $ids as $id ) {
        directory_sync_geo_meta( $id );
    }
    update_option( 'directory_geo_meta_v1', 1, false );
} );

/** Re-index one listing in FacetWP (after all meta + terms are saved). */
function directory_facetwp_reindex( $post_id ) {
    if ( function_exists( 'FWP' ) && isset( FWP()->indexer ) ) {
        FWP()->indexer->index( $post_id );
    }
}
