<?php
/**
 * [directory_edit_form] shortcode — frontend create/edit form for a
 * single Directory listing.
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
 * Render a nested <li> checkbox tree for a flat list of terms
 * (parents with their children indented underneath), WP-admin style.
 *
 * @param WP_Term[] $terms        Full flat list of terms for the taxonomy.
 * @param int[]     $selected_ids Term IDs that should be checked.
 * @param int       $parent_id    Term to render children of (0 = top level).
 */
function directory_render_category_tree( $terms, $selected_ids, $parent_id = 0 ) {
    $html = '';
    foreach ( $terms as $term ) {
        if ( (int) $term->parent !== (int) $parent_id ) {
            continue;
        }
        $children = directory_render_category_tree( $terms, $selected_ids, $term->term_id );
        $html .= '<li>';
        $html .= '<label class="df-checkbox">';
        $html .= '<input type="checkbox" name="directory_cat[]" value="' . esc_attr( $term->term_id ) . '"';
        $html .= checked( in_array( (int) $term->term_id, array_map( 'intval', (array) $selected_ids ), true ), true, false );
        $html .= '> ' . esc_html( $term->name );
        $html .= '</label>';
        if ( $children ) {
            $html .= '<ul class="df-cat-children">' . $children . '</ul>';
        }
        $html .= '</li>';
    }
    return $html;
}

add_shortcode( 'directory_edit_form', 'directory_render_edit_form' );
function directory_render_edit_form() {

    if ( ! is_user_logged_in() ) {
        return '<p class="directory-notice">Please log in to create or edit a directory listing.</p>';
    }

    $post_id = isset( $_GET['directory_id'] ) ? absint( $_GET['directory_id'] ) : 0;

    if ( $post_id && ! directory_user_can_edit_listing( $post_id ) ) {
        return '<p class="directory-notice">You do not have permission to edit this listing.</p>';
    }

    // Existing values (empty strings/arrays when creating new).
    $provider_name = $post_id ? get_the_title( $post_id ) : '';
    $avatar_url    = $post_id ? get_the_post_thumbnail_url( $post_id, 'thumbnail' ) : '';
    $avatar_id     = $post_id ? get_post_thumbnail_id( $post_id ) : 0;
    $short_desc    = $post_id ? get_post_meta( $post_id, '_directory_short_desc', true ) : '';
    $full_desc     = $post_id ? get_post_meta( $post_id, '_directory_full_desc', true ) : '';
    $services_list = $post_id ? (array) get_post_meta( $post_id, '_directory_services', true ) : array();
    $address       = $post_id ? get_post_meta( $post_id, '_directory_address', true ) : '';
    $latlng        = $post_id ? get_post_meta( $post_id, '_directory_latlng', true ) : '';
    $selected_cats = $post_id ? wp_get_post_terms( $post_id, 'directory_cat', array( 'fields' => 'ids' ) ) : array();
    $selected_tags = $post_id ? wp_get_post_terms( $post_id, 'directory_tag', array( 'fields' => 'ids' ) ) : array();

    $contact_fields = array(
        'phone'     => 'Phone',
        'email'     => 'Email',
    );

    $categories = get_terms( array( 'taxonomy' => 'directory_cat', 'hide_empty' => false ) );
    $tags       = get_terms( array( 'taxonomy' => 'directory_tag', 'hide_empty' => false ) );

    ob_start();
    ?>
    <form id="directory-edit-form" class="directory-form" novalidate>
        <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
        <input type="hidden" name="avatar_id" id="avatar_id" value="<?php echo esc_attr( $avatar_id ); ?>">

        <p class="directory-form-status" data-role="status" hidden></p>

        <div class="df-field">
            <label for="provider_name">Provider Name</label>
            <input type="text" id="provider_name" name="provider_name" required
                   value="<?php echo esc_attr( $provider_name ); ?>">
        </div>

        <div class="df-field">
            <label>Provider Avatar</label>
            <div class="avatar-upload <?php echo $avatar_id ? 'has-avatar' : ''; ?>">
                <div class="avatar-upload__controls">
                    <button type="button" id="avatar-remove" class="button button-secondary">Remove</button>
                </div>

                <div class="avatar-upload__wrap">
                    <span class="upload-text">
                        <img src="data:image/svg+xml,%3Csvg width='24' height='24' viewBox='0 0 24 24' fill='%23959595' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 15H13V9H16L12 4L8 9H11V15Z' fill='%23959595'/%3E%3Cpath d='M20 18H4V11H2V18C2 19.103 2.897 20 4 20H20C21.103 20 22 19.103 22 18V11H20V18Z' fill='%23959595'/%3E%3C/svg%3E%0A" alt="">
                        Upload<br>Photo
                    </span>
                    <input type="file" id="avatar-input" class="avatar-upload__input" accept=".jpg,.jpeg,.png,.gif">
                    <div class="avatar-upload__preview">
                        <img class="avatar-upload__image" src="<?php echo esc_url( $avatar_url ); ?>" alt="">
                    </div>
                </div>

                <div class="avatar-upload__messages"></div>
            </div>
            <small>Cropped and saved at exactly 150×150px.</small>
        </div>

        <div class="df-field">
            <label>Categories</label>
            <ul class="df-cat-tree">
                <?php echo directory_render_category_tree( $categories, $selected_cats ); ?>
            </ul>
        </div>

        <div class="df-field">
            <label>Services</label>
            <div class="df-checkbox-group">
                <?php foreach ( $tags as $tag ) : ?>
                    <label class="df-checkbox">
                        <input type="checkbox" name="directory_tag[]" value="<?php echo esc_attr( $tag->term_id ); ?>"
                            <?php checked( in_array( $tag->term_id, $selected_tags, true ) ); ?>>
                        <?php echo esc_html( $tag->name ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="df-field">
            <label for="short_description">Short Description <span class="df-count" data-max="120">0/120</span></label>
            <textarea id="short_description" name="short_description" maxlength="120" rows="2"><?php echo esc_textarea( $short_desc ); ?></textarea>
        </div>

        <div class="df-field">
            <label for="full_description">Full Description <span class="df-count" data-max="500">0/500</span></label>
            <textarea id="full_description" name="full_description" maxlength="500" rows="5"><?php echo esc_textarea( $full_desc ); ?></textarea>
        </div>

        <div class="df-field">
            <label>Services List</label>
            <div id="services-repeater" class="df-repeater">
                <?php if ( empty( $services_list ) ) : ?>
                    <div class="df-repeater-row">
                        <input type="text" class="df-repeater-input" placeholder="e.g. Free consultation">
                        <button type="button" class="df-repeater-up" title="Move up" aria-label="Move up">&uarr;</button>
                        <button type="button" class="df-repeater-down" title="Move down" aria-label="Move down">&darr;</button>
                        <button type="button" class="df-repeater-remove" title="Remove" aria-label="Remove">&times;</button>
                    </div>
                <?php else : foreach ( $services_list as $line ) : ?>
                    <div class="df-repeater-row">
                        <input type="text" class="df-repeater-input" value="<?php echo esc_attr( $line ); ?>">
                        <button type="button" class="df-repeater-up" title="Move up" aria-label="Move up">&uarr;</button>
                        <button type="button" class="df-repeater-down" title="Move down" aria-label="Move down">&darr;</button>
                        <button type="button" class="df-repeater-remove" title="Remove" aria-label="Remove">&times;</button>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <button type="button" id="services-add-row" class="df-btn-secondary">+ Add service</button>
            <input type="hidden" name="services_list" id="services_list">
        </div>

        <div class="df-field">
            <label for="location_address">Location</label>
            <input type="text" id="location_address" name="location_address" autocomplete="off"
                   value="<?php echo esc_attr( $address ); ?>" placeholder="Start typing an address…">
            <input type="hidden" id="location_latlng" name="location_latlng" value="<?php echo esc_attr( $latlng ); ?>">
            <input type="hidden" id="location_city" name="location_city" value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, '_directory_city', true ) : '' ); ?>">
        </div>

        <fieldset class="df-field df-contact">
            <legend>Contact Information</legend>
            <?php foreach ( $contact_fields as $field => $label ) : ?>
                <div class="df-field">
                    <label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label>
                    <input type="<?php echo 'email' === $field ? 'email' : ( 'phone' === $field ? 'tel' : 'text' ); ?>"
                           id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>"
                           <?php echo 'phone' === $field ? 'placeholder="(555) 123-4567" inputmode="tel" autocomplete="tel-national" maxlength="14"' : ''; ?>
                           value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, '_directory_' . $field, true ) : '' ); ?>">
                </div>
            <?php endforeach; ?>
        </fieldset>

        <button type="submit" class="df-btn-primary">Save Listing</button>
    </form>

    <a data-fancybox data-src="#modal-crop" href="javascript:;" style="display:none">Open Cropper Popup</a>
    <div class="modal modal-cropper" id="modal-crop" tabindex="-1" role="dialog" aria-hidden="true" style="display:none;">
        <div class="modal-header">
            <h3 class="modal-title">Crop &amp; Upload Image</h3>
        </div>
        <div class="modal-body">
            <div class="img-container">
                <div class="img-crop-wrapper">
                    <img src="" id="sample_image" alt="">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" id="crop" class="button button-primary">
                <span class="crop-text">Upload</span>
                <i class="avatar-loader"></i>
            </button>
            <button type="button" data-fancybox-close="" title="Cancel" class="button button-secondary">Cancel</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}