<?php
/**
 * Location line. With a location entered in the Proximity facet:
 * "[pin] 2.3 miles · Wellington, FL", otherwise just "Wellington, FL".
 *
 * @var array $card From directory_card_data().
 */
defined( 'ABSPATH' ) || exit;

$has_distance = null !== $card['distance'];
if ( ! $has_distance && ! $card['city'] ) {
    return;
}
?>
<div class="dir-loc">
    <?php if ( $has_distance ) : ?>
        <span class="dir-loc__distance">
            <span class="dir-loc__icon"><?php echo directory_icon( 'distance' ); // phpcs:ignore -- static SVG asset ?></span>
            <strong><?php echo esc_html( directory_format_miles( $card['distance'] ) ); ?></strong>
        </span>
        <?php if ( $card['city'] ) : ?>
            <span class="dir-dot" aria-hidden="true"></span>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ( $card['city'] ) : ?>
        <span class="dir-loc__city"><?php echo esc_html( $card['city'] ); ?></span>
    <?php endif; ?>
</div>
