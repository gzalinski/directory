<?php
/**
 * Frontend create/edit form for a Directory listing ([directory_edit_form]).
 * Saved over AJAX by src/js/submit.js -> directory_save_listing().
 *
 * @var array     $values         From directory_form_values().
 * @var WP_Term[] $categories     directory_cat terms.
 * @var WP_Term[] $tags           directory_tag terms.
 * @var array     $contact_fields From directory_contact_fields().
 */
defined( 'ABSPATH' ) || exit;
?>
<form id="directory-edit-form" class="directory-form" novalidate>
    <input type="hidden" name="post_id" value="<?php echo esc_attr( $values['post_id'] ); ?>">
    <input type="hidden" name="avatar_id" id="avatar_id" value="<?php echo esc_attr( $values['avatar_id'] ); ?>">

    <p class="directory-form-status" data-role="status" hidden></p>

    <div class="df-field">
        <label for="provider_name">Provider Name</label>
        <input type="text" id="provider_name" name="provider_name" required
               value="<?php echo esc_attr( $values['provider_name'] ); ?>">
    </div>

    <div class="df-field">
        <label>Provider Avatar</label>
        <?php directory_template( 'form/avatar-upload', array(
            'avatar_id'  => $values['avatar_id'],
            'avatar_url' => $values['avatar_url'],
        ) ); ?>
        <small>Cropped and saved at exactly 150×150px.</small>
    </div>

    <div class="df-field">
        <label>Categories</label>
        <ul class="df-cat-tree">
            <?php directory_template( 'form/category-tree', array(
                'terms'     => is_array( $categories ) ? $categories : array(),
                'selected'  => $values['selected_cats'],
                'parent_id' => 0,
            ) ); ?>
        </ul>
    </div>

    <div class="df-field">
        <label>Services</label>
        <div class="df-checkbox-group">
            <?php foreach ( is_array( $tags ) ? $tags : array() as $tag ) : ?>
                <label class="df-checkbox">
                    <input type="checkbox" name="directory_tag[]" value="<?php echo esc_attr( $tag->term_id ); ?>"
                        <?php checked( in_array( (int) $tag->term_id, $values['selected_tags'], true ) ); ?>>
                    <?php echo esc_html( $tag->name ); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="df-field">
        <label for="short_description">Short Description <span class="df-count" data-max="120">0/120</span></label>
        <textarea id="short_description" name="short_description" maxlength="120" rows="2"><?php echo esc_textarea( $values['short_desc'] ); ?></textarea>
    </div>

    <div class="df-field">
        <label for="full_description">Full Description <span class="df-count" data-max="500">0/500</span></label>
        <textarea id="full_description" name="full_description" maxlength="500" rows="5"><?php echo esc_textarea( $values['full_desc'] ); ?></textarea>
    </div>

    <div class="df-field">
        <label>Services List</label>
        <div id="services-repeater" class="df-repeater">
            <?php foreach ( $values['services_list'] ? $values['services_list'] : array( '' ) as $line ) : ?>
                <?php directory_template( 'form/repeater-row', array( 'value' => $line ) ); ?>
            <?php endforeach; ?>
        </div>
        <template id="services-row-template"><?php directory_template( 'form/repeater-row' ); ?></template>
        <button type="button" id="services-add-row" class="df-btn-secondary">+ Add service</button>
        <input type="hidden" name="services_list" id="services_list">
    </div>

    <div class="df-field">
        <label for="location_address">Location</label>
        <input type="text" id="location_address" name="location_address" autocomplete="off"
               value="<?php echo esc_attr( $values['address'] ); ?>" placeholder="Start typing an address…">
        <input type="hidden" id="location_latlng" name="location_latlng" value="<?php echo esc_attr( $values['latlng'] ); ?>">
        <input type="hidden" id="location_city" name="location_city" value="<?php echo esc_attr( $values['city'] ); ?>">
    </div>

    <fieldset class="df-field df-contact">
        <legend>Contact Information</legend>
        <?php foreach ( $contact_fields as $key => $field ) : ?>
            <div class="df-field">
                <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
                <input type="<?php echo esc_attr( $field['type'] ); ?>"
                       id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
                       value="<?php echo esc_attr( $values['contact'][ $key ] ?? '' ); ?>"
                    <?php foreach ( $field['attrs'] ?? array() as $attr => $val ) : ?>
                        <?php echo esc_attr( $attr ); ?>="<?php echo esc_attr( $val ); ?>"
                    <?php endforeach; ?>>
            </div>
        <?php endforeach; ?>
    </fieldset>

    <button type="submit" class="df-btn-primary">Save Listing</button>
</form>

<?php directory_template( 'form/crop-modal' ); ?>
