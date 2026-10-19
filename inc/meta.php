<?php
/**
 * Directory meta fields + AJAX handlers (create/edit listing, avatar upload)
 */
defined( 'ABSPATH' ) || exit;

/** All simple text/textarea meta keys we handle generically. */
function directory_text_meta_keys() {
    return array(
        '_directory_short_desc',
        '_directory_full_desc',
        '_directory_address',
        '_directory_latlng',
        '_directory_city',
        '_directory_phone',
        '_directory_email',
    );
}

add_action( 'init', function () {
    foreach ( directory_text_meta_keys() as $key ) {
        register_post_meta( 'directory', $key, array(
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ) );
    }
    register_post_meta( 'directory', '_directory_services', array(
        'type'         => 'array',
        'single'       => true,
        'show_in_rest' => array(
            'schema' => array(
                'type'  => 'array',
                'items' => array( 'type' => 'string' ),
            ),
        ),
    ) );
    register_post_meta( 'directory', '_directory_owner_id', array(
        'type'   => 'integer',
        'single' => true,
    ) );
} );

/**
 * Normalize a US phone number to "(555) 123-4567". Returns '' for empty
 * input and false when the number is not a valid 10-digit US number.
 */
function directory_normalize_us_phone( $raw ) {
    $digits = preg_replace( '/\D/', '', (string) $raw );
    if ( '' === $digits ) {
        return '';
    }
    if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
        $digits = substr( $digits, 1 );
    }
    if ( 10 !== strlen( $digits ) || ! preg_match( '/^[2-9]\d{2}[2-9]\d{6}$/', $digits ) ) {
        return false;
    }
    return sprintf( '(%s) %s-%s', substr( $digits, 0, 3 ), substr( $digits, 3, 3 ), substr( $digits, 6 ) );
}

/**
 * Can the current user edit this listing (or create a new one)?
 */
function directory_user_can_edit_listing( $post_id ) {
    if ( ! is_user_logged_in() ) {
        return false;
    }
    if ( ! $post_id ) {
        return true; // creating new
    }
    $owner = (int) get_post_meta( $post_id, '_directory_owner_id', true );
    return current_user_can( 'manage_options' ) || $owner === get_current_user_id();
}

/**
 * AJAX: save (create or update) a listing.
 */
add_action( 'wp_ajax_directory_save_listing', 'directory_save_listing' );
function directory_save_listing() {
    check_ajax_referer( 'directory_form_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'You must be logged in.' ), 401 );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

    if ( ! directory_user_can_edit_listing( $post_id ) ) {
        wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
    }

    $provider_name = isset( $_POST['provider_name'] ) ? sanitize_text_field( wp_unslash( $_POST['provider_name'] ) ) : '';
    if ( '' === $provider_name ) {
        wp_send_json_error( array( 'message' => 'Provider name is required.' ) );
    }

    $phone = directory_normalize_us_phone( isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '' );
    if ( false === $phone ) {
        wp_send_json_error( array( 'message' => 'Please enter a valid US phone number, e.g. (555) 123-4567.' ) );
    }

    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    if ( '' !== trim( (string) ( $_POST['email'] ?? '' ) ) && ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
    }

    $postarr = array(
        'post_type'   => 'directory',
        'post_title'  => $provider_name,
        'post_status' => 'publish',
    );

    if ( $post_id ) {
        $postarr['ID'] = $post_id;
        $post_id       = wp_update_post( $postarr, true );
    } else {
        $post_id = wp_insert_post( $postarr, true );
    }

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
    }

    if ( empty( get_post_meta( $post_id, '_directory_owner_id', true ) ) ) {
        update_post_meta( $post_id, '_directory_owner_id', get_current_user_id() );
    }

    // Categories (multiple — parent categories and/or subcategories).
    if ( isset( $_POST['directory_cat'] ) && is_array( $_POST['directory_cat'] ) ) {
        $cat_ids = array_map( 'absint', $_POST['directory_cat'] );
        wp_set_object_terms( $post_id, $cat_ids, 'directory_cat', false );
    } else {
        wp_set_object_terms( $post_id, array(), 'directory_cat', false );
    }

    // Services / tags (multiple).
    if ( isset( $_POST['directory_tag'] ) && is_array( $_POST['directory_tag'] ) ) {
        $tag_ids = array_map( 'absint', $_POST['directory_tag'] );
        wp_set_object_terms( $post_id, $tag_ids, 'directory_tag', false );
    } else {
        wp_set_object_terms( $post_id, array(), 'directory_tag', false );
    }

    // Short / full description, with hard length limits matching the spec.
    if ( isset( $_POST['short_description'] ) ) {
        $short = sanitize_textarea_field( wp_unslash( $_POST['short_description'] ) );
        update_post_meta( $post_id, '_directory_short_desc', mb_substr( $short, 0, 120 ) );
    }
    if ( isset( $_POST['full_description'] ) ) {
        $full = sanitize_textarea_field( wp_unslash( $_POST['full_description'] ) );
        update_post_meta( $post_id, '_directory_full_desc', mb_substr( $full, 0, 500 ) );
    }

    // Services list repeater — arrives as a JSON array of strings.
    if ( isset( $_POST['services_list'] ) ) {
        $decoded = json_decode( wp_unslash( $_POST['services_list'] ), true );
        $clean   = array();
        if ( is_array( $decoded ) ) {
            foreach ( $decoded as $line ) {
                $line = sanitize_text_field( $line );
                if ( '' !== $line ) {
                    $clean[] = $line;
                }
            }
        }
        update_post_meta( $post_id, '_directory_services', $clean );
    }

    // Location.
    if ( isset( $_POST['location_address'] ) ) {
        update_post_meta( $post_id, '_directory_address', sanitize_text_field( wp_unslash( $_POST['location_address'] ) ) );
    }
    if ( isset( $_POST['location_city'] ) ) {
        update_post_meta( $post_id, '_directory_city', sanitize_text_field( wp_unslash( $_POST['location_city'] ) ) );
    }
    if ( isset( $_POST['location_latlng'] ) ) {
        update_post_meta( $post_id, '_directory_latlng', sanitize_text_field( wp_unslash( $_POST['location_latlng'] ) ) );
    }

    // Phone (already normalized).
    update_post_meta( $post_id, '_directory_phone', $phone );

    // Email (already validated).
    update_post_meta( $post_id, '_directory_email', $email );

    // Avatar (attachment ID already uploaded via directory_upload_avatar).
    if ( isset( $_POST['avatar_id'] ) ) {
        $avatar_id = absint( $_POST['avatar_id'] );
        if ( $avatar_id ) {
            set_post_thumbnail( $post_id, $avatar_id );
        } else {
            delete_post_thumbnail( $post_id );
        }
    }

    // FacetWP indexed the post on wp_insert_post, before meta/terms existed.
    directory_sync_geo_meta( $post_id );
    directory_facetwp_reindex( $post_id );

    wp_send_json_success( array(
        'post_id'   => $post_id,
        'permalink' => get_permalink( $post_id ),
        'edit_url' => add_query_arg( 'directory_id', $post_id, wp_get_referer() ?: home_url() ),
    ) );
}

/**
 * AJAX: upload the cropped avatar (base64 data URL from Cropper.js).
 * The image must be exactly 150x150; anything else is rejected.
 */
add_action( 'wp_ajax_directory_upload_avatar', 'directory_upload_avatar' );
function directory_upload_avatar() {
    check_ajax_referer( 'directory_form_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'You must be logged in.' ), 401 );
    }

    $data = isset( $_POST['image'] ) ? wp_unslash( $_POST['image'] ) : '';
    if ( ! preg_match( '#^data:image/(png|jpeg);base64,#', $data, $m ) ) {
        wp_send_json_error( array( 'message' => 'Invalid image.' ) );
    }

    $binary = base64_decode( substr( $data, strpos( $data, ',' ) + 1 ), true );
    if ( ! $binary || strlen( $binary ) > 2 * MB_IN_BYTES ) {
        wp_send_json_error( array( 'message' => 'Invalid or too large image.' ) );
    }

    $info = @getimagesizefromstring( $binary );
    if ( ! $info || 150 !== $info[0] || 150 !== $info[1] || ! in_array( $info['mime'], array( 'image/png', 'image/jpeg' ), true ) ) {
        wp_send_json_error( array( 'message' => 'Avatar must be exactly 150x150px.' ) );
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $ext      = 'image/png' === $info['mime'] ? 'png' : 'jpg';
    $filename = 'directory-avatar-' . get_current_user_id() . '-' . wp_generate_password( 8, false ) . '.' . $ext;
    $upload   = wp_upload_bits( $filename, null, $binary );
    if ( ! empty( $upload['error'] ) ) {
        wp_send_json_error( array( 'message' => $upload['error'] ) );
    }

    $attachment_id = wp_insert_attachment( array(
        'post_mime_type' => $info['mime'],
        'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
        'post_status'    => 'inherit',
    ), $upload['file'] );

    if ( is_wp_error( $attachment_id ) ) {
        wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
    }

    wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

    wp_send_json_success( array(
        'attachment_id' => $attachment_id,
        'preview_url'   => $upload['url'],
    ) );
}
