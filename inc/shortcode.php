<?php
/**
 * [directory_edit_form] shortcode: frontend create/edit form for a
 * single Directory listing.
 *
 * This file only loads assets, checks permissions and prepares data.
 * All markup lives in templates/form/.
 */
defined( 'ABSPATH' ) || exit;

// TODO: put your own key here (Maps JavaScript API + Places library enabled).
if ( ! defined( 'TCK_API_KEY_GOOGLE_MAPS' ) ) {
    define( 'TCK_API_KEY_GOOGLE_MAPS', '' );
}

add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_singular() && ! is_page() ) {
        return;
    }
    global $post;
    if ( ! $post || ! has_shortcode( $post->post_content, 'directory_edit_form' ) ) {
        return;
    }

    wp_enqueue_style(
        'directory-form',
        TCK_DIRECTORY_URL . 'assets/public.css',
        array(),
        filemtime( TCK_DIRECTORY_PATH . 'assets/public.css' )
    );

    wp_enqueue_style( 'fancybox', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.css', array(), '3.5.7' );
    wp_enqueue_script( 'fancybox', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js', array( 'jquery' ), '3.5.7', true );
    wp_enqueue_style( 'cropperjs', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.11/cropper.min.css', array(), '1.5.11' );
    wp_enqueue_script( 'cropperjs', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.11/cropper.min.js', array(), '1.5.11', true );

    wp_enqueue_script(
        'directory-form',
        TCK_DIRECTORY_URL . 'assets/admin.js',
        array( 'jquery', 'fancybox', 'cropperjs' ),
        filemtime( TCK_DIRECTORY_PATH . 'assets/admin.js' ),
        true
    );

    if ( TCK_API_KEY_GOOGLE_MAPS ) {
        wp_enqueue_script(
            'google-places',
            'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( TCK_API_KEY_GOOGLE_MAPS ) . '&loading=async',
            array(),
            null,
            true
        );
    }

    wp_localize_script( 'directory-form', 'DirectoryForm', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'directory_form_nonce' ),
    ) );
} );

/**
 * Contact inputs shown in the form. Each entry: label, input type and
 * extra HTML attributes. Values are read from the _directory_{key} meta.
 */
function directory_contact_fields() {
    return apply_filters( 'directory_contact_fields', array(
        'phone' => array(
            'label' => 'Phone',
            'type'  => 'tel',
            'attrs' => array(
                'placeholder'  => '(555) 123-4567',
                'inputmode'    => 'tel',
                'autocomplete' => 'tel-national',
                'maxlength'    => '14',
            ),
        ),
        'email' => array(
            'label' => 'Email',
            'type'  => 'email',
            'attrs' => array( 'autocomplete' => 'email' ),
        ),
    ) );
}

/** Values for the form: empty strings/arrays when creating a new listing. */
function directory_form_values( $post_id ) {
    $meta = function ( $key ) use ( $post_id ) {
        return $post_id ? get_post_meta( $post_id, $key, true ) : '';
    };

    $contact = array();
    foreach ( array_keys( directory_contact_fields() ) as $key ) {
        $contact[ $key ] = $meta( '_directory_' . $key );
    }

    return array(
        'post_id'       => $post_id,
        'provider_name' => $post_id ? get_the_title( $post_id ) : '',
        'avatar_id'     => $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0,
        'avatar_url'    => $post_id ? (string) get_the_post_thumbnail_url( $post_id, 'thumbnail' ) : '',
        'short_desc'    => $meta( '_directory_short_desc' ),
        'full_desc'     => $meta( '_directory_full_desc' ),
        'services_list' => $post_id ? array_values( array_filter( (array) get_post_meta( $post_id, '_directory_services', true ) ) ) : array(),
        'address'       => $meta( '_directory_address' ),
        'latlng'        => $meta( '_directory_latlng' ),
        'city'          => $meta( '_directory_city' ),
        'selected_cats' => $post_id ? array_map( 'intval', wp_get_post_terms( $post_id, 'directory_cat', array( 'fields' => 'ids' ) ) ) : array(),
        'selected_tags' => $post_id ? array_map( 'intval', wp_get_post_terms( $post_id, 'directory_tag', array( 'fields' => 'ids' ) ) ) : array(),
        'contact'       => $contact,
    );
}

add_shortcode( 'directory_edit_form', 'directory_render_edit_form' );
function directory_render_edit_form() {

    if ( ! is_user_logged_in() ) {
        return directory_get_template( 'form/notice', array(
            'message' => 'Please log in to create or edit a directory listing.',
        ) );
    }

    $post_id = isset( $_GET['directory_id'] ) ? absint( $_GET['directory_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

    if ( $post_id && ! directory_user_can_edit_listing( $post_id ) ) {
        return directory_get_template( 'form/notice', array(
            'message' => 'You do not have permission to edit this listing.',
        ) );
    }

    return directory_get_template( 'form/edit-form', array(
        'values'         => directory_form_values( $post_id ),
        'categories'     => get_terms( array( 'taxonomy' => 'directory_cat', 'hide_empty' => false ) ),
        'tags'           => get_terms( array( 'taxonomy' => 'directory_tag', 'hide_empty' => false ) ),
        'contact_fields' => directory_contact_fields(),
    ) );
}
