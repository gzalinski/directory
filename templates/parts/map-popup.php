<?php
/**
 * Map marker popup (Google info window) for one listing.
 * Must not call have_posts() / the_post() (FacetWP rule).
 *
 * @var array $card From directory_card_data().
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="dir-iw" data-post-id="<?php echo (int) $card['id']; ?>">
    <div class="dir-iw__head">
        <a class="dir-iw__avatar" href="<?php echo esc_url( $card['url'] ); ?>" tabindex="-1" aria-hidden="true">
            <?php echo directory_avatar_html( $card['id'], 70 ); // phpcs:ignore ?>
        </a>
        <div class="dir-iw__info">
            <div class="dir-iw__title">
                <strong><?php echo esc_html( $card['name'] ); ?></strong>
                <?php if ( $card['category'] ) : ?>
                    <span class="dir-dot" aria-hidden="true"></span>
                    <span class="dir-pill"><?php echo esc_html( $card['category']->name ); ?></span>
                <?php endif; ?>
            </div>
            <?php directory_template( 'parts/rating', array( 'rating' => $card['rating'] ) ); ?>
            <?php directory_template( 'parts/location', array( 'card' => $card ) ); ?>
        </div>
    </div>
    <a class="dir-btn dir-btn--block dir-iw__btn" href="<?php echo esc_url( $card['url'] ); ?>">View Profile</a>
</div>
