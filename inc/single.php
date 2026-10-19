<?php
/**
 * Single Directory template: loader, assets and helpers.
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'template_include', function ( $template ) {
    if ( is_singular( 'directory' ) ) {
        return TCK_DIRECTORY_PATH . 'templates/single-directory.php';
    }
    return $template;
} );

add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_singular( 'directory' ) ) {
        return;
    }

    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1' );
    wp_enqueue_style( 'directory-single', TCK_DIRECTORY_URL . 'assets/single.css', array(), filemtime( TCK_DIRECTORY_PATH . 'assets/single.css' ) );
    wp_enqueue_script( 'directory-single', TCK_DIRECTORY_URL . 'assets/single.js', array(), filemtime( TCK_DIRECTORY_PATH . 'assets/single.js' ), true );

    if ( TCK_API_KEY_GOOGLE_MAPS ) {
        wp_enqueue_script(
            'directory-google-maps',
            'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( TCK_API_KEY_GOOGLE_MAPS ) . '&callback=initDirectoryMap&loading=async',
            array( 'directory-single' ),
            null,
            true
        );
    }
} );

/**
 * Short location label: the saved city ("Wellington, FL"), else derived from
 * the address: US format -> "City, ST", any other format -> the city part only.
 */
function directory_city_state( $address, $post_id = 0 ) {
    if ( $post_id ) {
        $saved = get_post_meta( $post_id, '_directory_city', true );
        if ( $saved ) {
            return $saved;
        }
    }

    $parts = array_values( array_filter( array_map( 'trim', explode( ',', (string) $address ) ) ) );
    $n     = count( $parts );

    // ..., City, ST 12345[, USA]
    for ( $i = $n - 1; $i >= 1; $i-- ) {
        if ( preg_match( '/^[A-Z]{2}(\s+\d{5}(-\d{4})?)?$/', $parts[ $i ] ) ) {
            return $parts[ $i - 1 ] . ', ' . preg_replace( '/\s.*$/', '', $parts[ $i ] );
        }
    }

    // ..., City, Country
    return $n >= 3 ? $parts[ $n - 2 ] : (string) $address;
}

/** Primary category: deepest selected term, else first. */
function directory_primary_category( $post_id ) {
    $terms = wp_get_post_terms( $post_id, 'directory_cat' );
    if ( is_wp_error( $terms ) || ! $terms ) {
        return null;
    }
    usort( $terms, fn( $a, $b ) => ( $b->parent ? 1 : 0 ) <=> ( $a->parent ? 1 : 0 ) );
    return $terms[0];
}

/** Share links for the current listing. */
function directory_share_links( $post_id ) {
    $url   = rawurlencode( get_permalink( $post_id ) );
    $title = rawurlencode( get_the_title( $post_id ) );
    $img   = rawurlencode( (string) get_the_post_thumbnail_url( $post_id, 'full' ) );

    return array(
        'facebook'  => array( 'Facebook', 'fa-brands fa-facebook-f', "https://www.facebook.com/sharer/sharer.php?u={$url}" ),
        'twitter'   => array( 'X', 'fa-brands fa-x-twitter', "https://twitter.com/intent/tweet?url={$url}&text={$title}" ),
        'pinterest' => array( 'Pinterest', 'fa-brands fa-pinterest-p', "https://pinterest.com/pin/create/button/?url={$url}&media={$img}&description={$title}" ),
        'linkedin'  => array( 'LinkedIn', 'fa-brands fa-linkedin-in', "https://www.linkedin.com/sharing/share-offsite/?url={$url}" ),
        'whatsapp'  => array( 'WhatsApp', 'fa-brands fa-whatsapp', "https://wa.me/?text={$title}%20{$url}" ),
    );
}

/**
 * URL of the front-end edit form for a listing: the first published page
 * containing [directory_edit_form] + ?directory_id=ID. Empty if none exists.
 */
function directory_edit_url( $post_id ) {
    $page_id = (int) apply_filters( 'directory_edit_page_id', 0 );

    if ( ! $page_id ) {
        $pages = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            's'              => 'directory_edit_form',
            'posts_per_page' => 5,
            'fields'         => 'ids',
        ) );
        foreach ( $pages as $candidate ) {
            if ( has_shortcode( get_post_field( 'post_content', $candidate ), 'directory_edit_form' ) ) {
                $page_id = $candidate;
                break;
            }
        }
    }

    return $page_id ? add_query_arg( 'directory_id', $post_id, get_permalink( $page_id ) ) : '';
}
