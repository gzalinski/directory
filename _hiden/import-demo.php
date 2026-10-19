<?php
/**
 * Demo listings importer (demo-listings.json -> directory posts).
 *
 * Two ways to run it:
 *   - wp-admin: Tools > Directory Demo (Import / Delete buttons).
 *   - WP-CLI:   wp directory-demo import [--no-avatars]
 *               wp directory-demo delete
 *
 * Safe to re-run: listings are matched by their demo_id (stored in
 * _directory_demo_id) and updated instead of duplicated. Missing
 * categories / services are created. Delete removes only demo posts
 * and their generated avatars.
 *
 * Regenerate the JSON with: python3 _hiden/make-demo-listings.py _hiden/categories.json _hiden/demo-listings.json
 */
defined( 'ABSPATH' ) || exit;

const DIRECTORY_DEMO_META = '_directory_demo_id';

/** Read and decode demo-listings.json. */
function directory_demo_read() {
    $file = __DIR__ . '/demo-listings.json';
    if ( ! is_readable( $file ) ) {
        return new WP_Error( 'demo_missing', 'demo-listings.json not found.' );
    }
    $data = json_decode( file_get_contents( $file ), true );
    if ( ! is_array( $data ) || empty( $data['listings'] ) ) {
        return new WP_Error( 'demo_invalid', 'demo-listings.json is not valid.' );
    }
    return $data;
}

/** Term ID by name (under a parent, for categories); created if missing. */
function directory_demo_term( $name, $taxonomy, $parent_id = 0 ) {
    $existing = term_exists( $name, $taxonomy, $parent_id ?: null );
    if ( $existing ) {
        return (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
    }
    $created = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent_id ) );
    return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
}

/** Owner for imported listings: current user, else the first administrator. */
function directory_demo_owner() {
    if ( get_current_user_id() ) {
        return get_current_user_id();
    }
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    return $admins ? (int) $admins[0] : 1;
}

/**
 * 150x150 PNG with the provider's initials (GD). Returns attachment ID or 0.
 * Avoids remote photo services so the import works offline.
 */
function directory_demo_avatar( $name, $post_id ) {
    if ( ! function_exists( 'imagecreatetruecolor' ) ) {
        return 0;
    }
    $words    = preg_split( '/\s+/', preg_replace( '/[^A-Za-z ]/', '', $name ) );
    $initials = strtoupper( substr( $words[0] ?? 'D', 0, 1 ) . substr( $words[1] ?? '', 0, 1 ) );

    $palette = array( array( 30, 58, 95 ), array( 74, 122, 184 ), array( 18, 183, 106 ), array( 181, 121, 54 ), array( 120, 72, 160 ) );
    $rgb     = $palette[ abs( crc32( $name ) ) % count( $palette ) ];

    // Draw on a small canvas with GD's built-in font, then scale up.
    $small = imagecreatetruecolor( 50, 50 );
    imagefill( $small, 0, 0, imagecolorallocate( $small, $rgb[0], $rgb[1], $rgb[2] ) );
    $font = 5;
    $tw   = imagefontwidth( $font ) * strlen( $initials );
    $th   = imagefontheight( $font );
    imagestring( $small, $font, (int) ( ( 50 - $tw ) / 2 ), (int) ( ( 50 - $th ) / 2 ), $initials, imagecolorallocate( $small, 255, 255, 255 ) );

    $img = imagecreatetruecolor( 150, 150 );
    imagecopyresampled( $img, $small, 0, 0, 0, 0, 150, 150, 50, 50 );
    ob_start();
    imagepng( $img );
    $png = ob_get_clean();
    imagedestroy( $small );
    imagedestroy( $img );

    $upload = wp_upload_bits( 'directory-demo-' . sanitize_title( $name ) . '.png', null, $png );
    if ( ! empty( $upload['error'] ) ) {
        return 0;
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attachment_id = wp_insert_attachment( array(
        'post_mime_type' => 'image/png',
        'post_title'     => $name,
        'post_status'    => 'inherit',
    ), $upload['file'], $post_id );
    if ( is_wp_error( $attachment_id ) ) {
        return 0;
    }
    wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
    update_post_meta( $attachment_id, DIRECTORY_DEMO_META, 'avatar' );
    return (int) $attachment_id;
}

/** Existing demo post IDs keyed by demo_id. */
function directory_demo_existing() {
    $ids = get_posts( array(
        'post_type'      => 'directory',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_key'       => DIRECTORY_DEMO_META, // phpcs:ignore
    ) );
    $map = array();
    foreach ( $ids as $id ) {
        $map[ get_post_meta( $id, DIRECTORY_DEMO_META, true ) ] = $id;
    }
    return $map;
}

/**
 * Import (or update) all demo listings.
 *
 * @param bool $avatars Generate initials avatars for listings without one.
 * @return array|WP_Error { created, updated, errors }
 */
function directory_demo_import( $avatars = true ) {
    $data = directory_demo_read();
    if ( is_wp_error( $data ) ) {
        return $data;
    }
    if ( ! taxonomy_exists( 'directory_cat' ) || ! taxonomy_exists( 'directory_tag' ) ) {
        return new WP_Error( 'demo_tax', 'Directory taxonomies are not registered.' );
    }
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 300 ); // phpcs:ignore
    }

    $existing = directory_demo_existing();
    $owner    = directory_demo_owner();
    $stats    = array( 'created' => 0, 'updated' => 0, 'errors' => 0 );

    foreach ( $data['listings'] as $row ) {
        $demo_id = sanitize_key( $row['demo_id'] ?? '' );
        if ( ! $demo_id || empty( $row['provider_name'] ) ) {
            $stats['errors']++;
            continue;
        }

        $postarr = array(
            'post_type'   => 'directory',
            'post_title'  => sanitize_text_field( $row['provider_name'] ),
            'post_status' => 'publish',
            'post_author' => $owner,
        );
        if ( ! empty( $row['date'] ) ) {
            $postarr['post_date'] = $row['date'];
        }

        $is_update = isset( $existing[ $demo_id ] );
        if ( $is_update ) {
            $postarr['ID'] = $existing[ $demo_id ];
            $post_id       = wp_update_post( $postarr, true );
        } else {
            $post_id = wp_insert_post( $postarr, true );
        }
        if ( is_wp_error( $post_id ) ) {
            $stats['errors']++;
            continue;
        }

        // Category (parent + child, child assigned) and services.
        $parent_id = ! empty( $row['category_parent'] ) ? directory_demo_term( $row['category_parent'], 'directory_cat' ) : 0;
        $cat_id    = directory_demo_term( $row['category'], 'directory_cat', $parent_id );
        wp_set_object_terms( $post_id, $cat_id ? array( $cat_id ) : array(), 'directory_cat', false );

        $tag_ids = array_filter( array_map( fn( $t ) => directory_demo_term( $t, 'directory_tag' ), (array) ( $row['services'] ?? array() ) ) );
        wp_set_object_terms( $post_id, array_values( $tag_ids ), 'directory_tag', false );

        // Same meta keys as the frontend form (inc/meta.php).
        $loc   = $row['location'] ?? array();
        $phone = directory_normalize_us_phone( $row['contact']['phone'] ?? '' );
        $meta  = array(
            DIRECTORY_DEMO_META       => $demo_id,
            '_directory_owner_id'     => $owner,
            '_directory_short_desc'   => mb_substr( sanitize_textarea_field( $row['short_description'] ?? '' ), 0, 120 ),
            '_directory_full_desc'    => mb_substr( sanitize_textarea_field( $row['full_description'] ?? '' ), 0, 500 ),
            '_directory_services'     => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $row['services_list'] ?? array() ) ) ) ),
            '_directory_address'      => sanitize_text_field( $loc['address'] ?? '' ),
            '_directory_city'         => sanitize_text_field( $loc['city'] ?? '' ),
            '_directory_latlng'       => isset( $loc['lat'], $loc['lng'] ) ? (float) $loc['lat'] . ',' . (float) $loc['lng'] : '',
            '_directory_phone'        => $phone ? $phone : '',
            '_directory_email'        => sanitize_email( $row['contact']['email'] ?? '' ),
        );
        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
        directory_sync_geo_meta( $post_id ); // directory_lat / directory_lng for FacetWP

        if ( $avatars && ! has_post_thumbnail( $post_id ) ) {
            $avatar_id = directory_demo_avatar( $postarr['post_title'], $post_id );
            if ( $avatar_id ) {
                set_post_thumbnail( $post_id, $avatar_id );
            }
        }

        directory_facetwp_reindex( $post_id );
        $stats[ $is_update ? 'updated' : 'created' ]++;
    }

    return $stats;
}

/** Delete all demo listings and their generated avatars. Returns count. */
function directory_demo_delete() {
    $deleted = 0;
    foreach ( directory_demo_existing() as $post_id ) {
        $thumb = get_post_thumbnail_id( $post_id );
        if ( $thumb && get_post_meta( $thumb, DIRECTORY_DEMO_META, true ) ) {
            wp_delete_attachment( $thumb, true );
        }
        if ( wp_delete_post( $post_id, true ) ) {
            $deleted++;
        }
    }
    // FacetWP drops deleted posts from its index automatically.
    return $deleted;
}

/* -------------------------------------------------------------------------
 * wp-admin: Tools > Directory Demo
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
    add_management_page( 'Directory Demo', 'Directory Demo', 'manage_options', 'directory-demo', 'directory_demo_page' );
} );

function directory_demo_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $message = '';
    if ( isset( $_POST['directory_demo_action'] ) && check_admin_referer( 'directory_demo' ) ) {
        if ( 'import' === $_POST['directory_demo_action'] ) {
            $result  = directory_demo_import( ! empty( $_POST['avatars'] ) );
            $message = is_wp_error( $result )
                ? '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>'
                : sprintf( '<div class="notice notice-success"><p>Imported: %d created, %d updated, %d errors.</p></div>', $result['created'], $result['updated'], $result['errors'] );
        } elseif ( 'delete' === $_POST['directory_demo_action'] ) {
            $message = sprintf( '<div class="notice notice-success"><p>Deleted %d demo listings.</p></div>', directory_demo_delete() );
        }
    }

    $data  = directory_demo_read();
    $total = is_wp_error( $data ) ? 0 : count( $data['listings'] );
    $have  = count( directory_demo_existing() );
    ?>
    <div class="wrap">
        <h1>Directory Demo</h1>
        <?php echo wp_kses_post( $message ); ?>
        <p>
            <code>_hiden/demo-listings.json</code> holds <strong><?php echo (int) $total; ?></strong> fake listings
            (New York, Washington, Los Angeles and more). <strong><?php echo (int) $have; ?></strong> are currently imported.
        </p>
        <form method="post" style="margin-bottom:1rem">
            <?php wp_nonce_field( 'directory_demo' ); ?>
            <input type="hidden" name="directory_demo_action" value="import">
            <label><input type="checkbox" name="avatars" value="1" checked> Generate initials avatars</label>
            <p><button class="button button-primary">Import / update demo listings</button></p>
        </form>
        <form method="post" onsubmit="return confirm('Delete all demo listings?');">
            <?php wp_nonce_field( 'directory_demo' ); ?>
            <input type="hidden" name="directory_demo_action" value="delete">
            <button class="button button-link-delete" <?php disabled( 0, $have ); ?>>Delete demo listings</button>
        </form>
        <p class="description">After importing, run a full Re-index in Settings &gt; FacetWP if facets look empty.</p>
    </div>
    <?php
}

/* -------------------------------------------------------------------------
 * WP-CLI: wp directory-demo import|delete
 * ---------------------------------------------------------------------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'directory-demo', new class {
        /**
         * Import or update demo listings from demo-listings.json.
         *
         * [--no-avatars]
         * : Skip generating initials avatars.
         */
        public function import( $args, $assoc ) {
            $result = directory_demo_import( WP_CLI\Utils\get_flag_value( $assoc, 'avatars', true ) );
            if ( is_wp_error( $result ) ) {
                WP_CLI::error( $result->get_error_message() );
            }
            WP_CLI::success( sprintf( '%d created, %d updated, %d errors.', $result['created'], $result['updated'], $result['errors'] ) );
        }

        /** Delete all demo listings and their generated avatars. */
        public function delete() {
            WP_CLI::success( sprintf( 'Deleted %d demo listings.', directory_demo_delete() ) );
        }
    } );
}
