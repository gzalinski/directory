<?php
/**
 * Rating line: "★ 4.5 · 85 Reviews".
 *
 * @var array|null $rating { avg, count } or null to hide.
 */
defined( 'ABSPATH' ) || exit;

if ( empty( $rating['count'] ) ) {
    return;
}
$count = (int) $rating['count'];
?>
<div class="dir-rating">
    <span class="dir-rating__star"><?php echo directory_icon( 'star' ); // phpcs:ignore -- static SVG asset ?></span>
    <span class="dir-rating__avg"><?php echo esc_html( number_format_i18n( (float) $rating['avg'], 1 ) ); ?></span>
    <span class="dir-dot" aria-hidden="true"></span>
    <span class="dir-rating__count"><?php echo esc_html( number_format_i18n( $count ) . ' ' . _n( 'Review', 'Reviews', $count ) ); ?></span>
</div>
