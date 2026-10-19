<?php
/**
 * Directory listing card (used inside the archive loop).
 * Must not call the_post(): it runs inside the loop already.
 */
defined( 'ABSPATH' ) || exit;

$card = directory_card_data( get_the_ID() );
?>
<article class="dir-card-row" data-latlng="<?php echo esc_attr( $card['latlng'] ); ?>">
    <a class="dir-card-row__avatar" href="<?php echo esc_url( $card['url'] ); ?>" tabindex="-1" aria-hidden="true">
        <?php echo directory_avatar_html( $card['id'], 56 ); // phpcs:ignore ?>
    </a>

    <div class="dir-card-row__info">
        <div class="dir-card-row__title">
            <h2><a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['name'] ); ?></a></h2>
            <?php if ( $card['category'] ) : ?>
                <span class="dir-dot" aria-hidden="true"></span>
                <span class="dir-pill"><?php echo esc_html( $card['category']->name ); ?></span>
            <?php endif; ?>
        </div>
        <?php directory_template( 'parts/rating', array( 'rating' => $card['rating'] ) ); ?>
        <?php directory_template( 'parts/location', array( 'card' => $card ) ); ?>
    </div>

    <div class="dir-card-row__about">
        <?php if ( $card['short'] ) : ?>
            <h3>More Information</h3>
            <p><?php echo esc_html( $card['short'] ); ?></p>
        <?php endif; ?>
    </div>

    <div class="dir-card-row__actions">
        <a class="dir-btn" href="<?php echo esc_url( $card['url'] ); ?>">View Profile</a>
        <?php if ( $card['message_url'] ) : ?>
            <a class="dir-btn dir-btn--outline" href="<?php echo esc_attr( $card['message_url'] ); ?>">Message Seller</a>
        <?php else : ?>
            <span class="dir-btn dir-btn--outline is-disabled">Message Seller</span>
        <?php endif; ?>
    </div>
</article>
