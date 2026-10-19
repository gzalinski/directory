<?php
/**
 * Nested checkbox tree for directory_cat (parents with their children
 * indented underneath, WP-admin style). Includes itself for each level.
 *
 * @var WP_Term[] $terms     Full flat list of terms.
 * @var int[]     $selected  Checked term IDs.
 * @var int       $parent_id Term whose children to render (0 = top level).
 */
defined( 'ABSPATH' ) || exit;

$parent_id = isset( $parent_id ) ? (int) $parent_id : 0;

foreach ( $terms as $term ) :
    if ( (int) $term->parent !== $parent_id ) {
        continue;
    }
    $children = directory_get_template( 'form/category-tree', array(
        'terms'     => $terms,
        'selected'  => $selected,
        'parent_id' => $term->term_id,
    ) );
    ?>
    <li>
        <label class="df-checkbox">
            <input type="checkbox" name="directory_cat[]" value="<?php echo esc_attr( $term->term_id ); ?>"
                <?php checked( in_array( (int) $term->term_id, $selected, true ) ); ?>>
            <?php echo esc_html( $term->name ); ?>
        </label>
        <?php if ( trim( $children ) ) : ?>
            <ul class="df-cat-children"><?php echo $children; // phpcs:ignore -- escaped in this template ?></ul>
        <?php endif; ?>
    </li>
<?php endforeach;
