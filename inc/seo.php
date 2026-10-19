<?php
/**
 * SEO for Directory: <title>, meta description, robots, Open Graph / Twitter
 * cards and JSON-LD (LocalBusiness + BreadcrumbList on single listings,
 * CollectionPage/ItemList on archives and category pages).
 *
 * Skipped when a dedicated SEO plugin is active (Yoast, Rank Math, AIOSEO,
 * SEOPress) so the tags are not duplicated. Canonical is left to WordPress core.
 */
defined( 'ABSPATH' ) || exit;

function directory_seo_plugin_active() {
    return (bool) apply_filters(
        'directory_seo_plugin_active',
        defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' )
    );
}

/** Is the current request a Directory page we handle (single / archive / taxonomy)? */
function directory_seo_context() {
    if ( is_singular( 'directory' ) ) {
        return 'single';
    }
    if ( is_post_type_archive( 'directory' ) ) {
        return 'archive';
    }
    if ( is_tax( array( 'directory_cat', 'directory_tag' ) ) ) {
        return 'term';
    }
    return '';
}

/** Trim to a sentence-friendly length for meta descriptions. */
function directory_seo_trim( $text, $max = 160 ) {
    $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
    if ( mb_strlen( $text ) <= $max ) {
        return $text;
    }
    return rtrim( mb_substr( $text, 0, $max - 1 ), " ,.;:-" ) . '…';
}

/** Title / description / image / url for the current Directory page. */
function directory_seo_data() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }

    $site    = get_bloginfo( 'name' );
    $context = directory_seo_context();

    $data    = array(
        'context'     => $context,
        'title'       => '',
        'description' => '',
        'image'       => '',
        'url'         => '',
        'type'        => 'website',
    );

    if ( 'single' === $context ) {
        $id      = get_queried_object_id();
        $name    = get_the_title( $id );
        $cat     = directory_primary_category( $id );
        $address = get_post_meta( $id, '_directory_address', true );
        $city    = directory_city_state( $address, $id );
        $short   = get_post_meta( $id, '_directory_short_desc', true );
        $full    = get_post_meta( $id, '_directory_full_desc', true );

        $title_parts = array_filter( array( $name, $cat ? $cat->name : '', $city ) );
        $desc        = $short ? $short : $full;
        if ( ! $desc ) {
            $desc = trim( $name . ( $cat ? ' – ' . $cat->name : '' ) . ( $city ? ' in ' . $city : '' ) );
        }

        $data['title']       = implode( ' | ', $title_parts ) . ' | ' . $site;
        $data['description'] = directory_seo_trim( $desc );
        $data['image']       = (string) get_the_post_thumbnail_url( $id, 'full' );
        $data['url']         = get_permalink( $id );
        $data['type']        = 'profile';
    } elseif ( 'term' === $context ) {
        $term = get_queried_object();
        $desc = term_description( $term );
        $data['title']       = $term->name . ' – Directory | ' . $site;
        $data['description'] = directory_seo_trim( $desc ? $desc : sprintf( 'Browse %s providers in the %s directory.', $term->name, $site ) );
        $data['url']         = get_term_link( $term );
    } elseif ( 'archive' === $context ) {
        $data['title']       = 'Provider Directory | ' . $site;
        $data['description'] = directory_seo_trim( sprintf( 'Find trusted providers and services in the %s directory.', $site ) );
        $data['url']         = get_post_type_archive_link( 'directory' );
    }

    if ( ! $data['image'] ) {
        $icon = get_site_icon_url( 512 );
        $data['image'] = $icon ? $icon : '';
    }

    $cache = apply_filters( 'directory_seo_data', $data, $context );
    return $cache;
}

/* <title> */
add_filter( 'pre_get_document_title', function ( $title ) {
    if ( directory_seo_plugin_active() || ! directory_seo_context() ) {
        return $title;
    }
    $d = directory_seo_data();
    return $d['title'] ? $d['title'] : $title;
}, 20 );

/* Robots: keep empty/thin listings out of the index. */
add_filter( 'wp_robots', function ( $robots ) {
    if ( directory_seo_plugin_active() || 'single' !== directory_seo_context() ) {
        return $robots;
    }
    $id = get_queried_object_id();
    if ( ! get_post_meta( $id, '_directory_short_desc', true ) && ! get_post_meta( $id, '_directory_full_desc', true ) ) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
    }
    return $robots;
} );

/* Meta description + Open Graph + Twitter. */
add_action( 'wp_head', function () {
    if ( directory_seo_plugin_active() || ! directory_seo_context() ) {
        return;
    }
    $d = directory_seo_data();

    if ( $d['description'] ) {
        echo '<meta name="description" content="' . esc_attr( $d['description'] ) . '">' . "\n";
    }

    $og = array(
        'og:locale'      => get_locale(),
        'og:type'        => $d['type'],
        'og:site_name'   => get_bloginfo( 'name' ),
        'og:title'       => $d['title'],
        'og:description' => $d['description'],
        'og:url'         => $d['url'],
        'og:image'       => $d['image'],
    );
    foreach ( $og as $prop => $val ) {
        if ( $val ) {
            echo '<meta property="' . esc_attr( $prop ) . '" content="' . esc_attr( $val ) . '">' . "\n";
        }
    }

    echo '<meta name="twitter:card" content="' . ( $d['image'] ? 'summary_large_image' : 'summary' ) . '">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr( $d['title'] ) . '">' . "\n";
    if ( $d['description'] ) {
        echo '<meta name="twitter:description" content="' . esc_attr( $d['description'] ) . '">' . "\n";
    }
    if ( $d['image'] ) {
        echo '<meta name="twitter:image" content="' . esc_url( $d['image'] ) . '">' . "\n";
    }
}, 5 );

/** Parse "lat,lng" meta into floats or null. */
function directory_seo_geo( $latlng ) {
    if ( ! $latlng || ! preg_match( '/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $latlng, $m ) ) {
        return null;
    }
    return array( (float) $m[1], (float) $m[2] );
}

/** Split a US-style address into PostalAddress parts (best effort). */
function directory_seo_postal_address( $address ) {
    $parts = array_values( array_filter( array_map( 'trim', explode( ',', (string) $address ) ) ) );
    $out   = array( '@type' => 'PostalAddress' );
    $n     = count( $parts );

    // Street, City, ST 12345[, USA]
    for ( $i = $n - 1; $i >= 1; $i-- ) {
        if ( preg_match( '/^([A-Z]{2})(?:\s+(\d{5}(?:-\d{4})?))?$/', $parts[ $i ], $m ) ) {
            $out['addressLocality'] = $parts[ $i - 1 ];
            $out['addressRegion']   = $m[1];
            if ( ! empty( $m[2] ) ) {
                $out['postalCode'] = $m[2];
            }
            if ( $i - 2 >= 0 ) {
                $out['streetAddress'] = implode( ', ', array_slice( $parts, 0, $i - 1 ) );
            }
            $out['addressCountry'] = isset( $parts[ $i + 1 ] ) && ! preg_match( '/^(USA|United States)$/i', $parts[ $i + 1 ] ) ? $parts[ $i + 1 ] : 'US';
            return $out;
        }
    }

    $out['streetAddress'] = (string) $address;
    return $out;
}

/** JSON-LD graph for a single listing. */
function directory_seo_single_schema( $id ) {
    $d       = directory_seo_data();
    $name    = get_the_title( $id );
    $cat     = directory_primary_category( $id );
    $address = get_post_meta( $id, '_directory_address', true );
    $phone   = get_post_meta( $id, '_directory_phone', true );
    $email   = get_post_meta( $id, '_directory_email', true );
    $full    = get_post_meta( $id, '_directory_full_desc', true );
    $geo     = directory_seo_geo( get_post_meta( $id, '_directory_latlng', true ) );
    $lines   = array_values( array_filter( (array) get_post_meta( $id, '_directory_services', true ) ) );
    $url     = get_permalink( $id );

    $biz = array(
        '@type'       => apply_filters( 'directory_schema_type', 'LocalBusiness', $id, $cat ),
        '@id'         => $url . '#business',
        'name'        => $name,
        'url'         => $url,
        'description' => $full ? wp_strip_all_tags( $full ) : $d['description'],
    );

    if ( $d['image'] ) {
        $biz['image'] = $d['image'];
        $biz['logo']  = $d['image'];
    }
    if ( $phone ) {
        $biz['telephone'] = '+1' . preg_replace( '/\D/', '', $phone );
    }
    if ( $email ) {
        $biz['email'] = $email;
    }
    if ( $address ) {
        $biz['address'] = directory_seo_postal_address( $address );
    }
    if ( $geo ) {
        $biz['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => $geo[0], 'longitude' => $geo[1] );
        $biz['hasMap'] = 'https://www.google.com/maps/search/?api=1&query=' . $geo[0] . ',' . $geo[1];
    }
    if ( $cat ) {
        $biz['additionalType'] = get_term_link( $cat );
        $biz['knowsAbout']     = $cat->name;
    }
    if ( $lines ) {
        $biz['hasOfferCatalog'] = array(
            '@type'           => 'OfferCatalog',
            'name'            => 'Services',
            'itemListElement' => array_map( fn( $l ) => array(
                '@type'       => 'Offer',
                'itemOffered' => array( '@type' => 'Service', 'name' => $l ),
            ), $lines ),
        );
    }
    // Rating / reviews are intentionally omitted until real review data exists.

    // Breadcrumbs: Home > Directory > [parent >] category > listing.
    $crumbs = array(
        array( 'Home', home_url( '/' ) ),
        array( 'Directory', get_post_type_archive_link( 'directory' ) ),
    );
    if ( $cat ) {
        foreach ( array_reverse( get_ancestors( $cat->term_id, 'directory_cat', 'taxonomy' ) ) as $anc_id ) {
            $anc = get_term( $anc_id, 'directory_cat' );
            if ( $anc && ! is_wp_error( $anc ) ) {
                $crumbs[] = array( $anc->name, get_term_link( $anc ) );
            }
        }
        $crumbs[] = array( $cat->name, get_term_link( $cat ) );
    }
    $crumbs[] = array( $name, $url );

    $items = array();
    foreach ( $crumbs as $i => $c ) {
        $items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] );
    }

    return array(
        $biz,
        array(
            '@type'           => 'WebPage',
            '@id'             => $url . '#webpage',
            'url'             => $url,
            'name'            => $d['title'],
            'description'     => $d['description'],
            'about'           => array( '@id' => $url . '#business' ),
            'isPartOf'        => array( '@type' => 'WebSite', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
            'datePublished'   => get_the_date( 'c', $id ),
            'dateModified'    => get_the_modified_date( 'c', $id ),
            'breadcrumb'      => array( '@id' => $url . '#breadcrumb' ),
        ),
        array( '@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $items ),
    );
}

/** JSON-LD for archive / term pages: CollectionPage + ItemList of the listings shown. */
function directory_seo_list_schema() {
    global $wp_query;
    $d     = directory_seo_data();
    $items = array();
    $pos   = 1;
    foreach ( (array) $wp_query->posts as $p ) {
        $items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'url' => get_permalink( $p ), 'name' => get_the_title( $p ) );
    }
    return array( array(
        '@type'           => 'CollectionPage',
        'url'             => $d['url'],
        'name'            => $d['title'],
        'description'     => $d['description'],
        'mainEntity'      => array( '@type' => 'ItemList', 'itemListElement' => $items ),
    ) );
}

add_action( 'wp_head', function () {
    $context = directory_seo_context();
    if ( ! $context ) {
        return;
    }

    $graph = 'single' === $context ? directory_seo_single_schema( get_queried_object_id() ) : directory_seo_list_schema();
    $graph = apply_filters( 'directory_schema_graph', $graph, $context );
    if ( ! $graph ) {
        return;
    }

    echo '<script type="application/ld+json">'
        . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
        . "</script>\n";
}, 6 );
