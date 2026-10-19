<?php
/**
 * Single Directory listing.
 * Hard-coded for now (no data source yet): verified badge, rating/reviews,
 * flag.
 */
defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
    the_post();

    $id        = get_the_ID();
    $name      = get_the_title();
    $avatar    = get_the_post_thumbnail_url( $id, 'thumbnail' );
    $cat       = directory_primary_category( $id );
    $short     = get_post_meta( $id, '_directory_short_desc', true );
    $full      = get_post_meta( $id, '_directory_full_desc', true );
    $services  = array_filter( (array) get_post_meta( $id, '_directory_services', true ) );
    $address   = get_post_meta( $id, '_directory_address', true );
    $latlng    = get_post_meta( $id, '_directory_latlng', true );
    $phone     = get_post_meta( $id, '_directory_phone', true );
    $city      = directory_city_state( $address, $id );
    $tel       = $phone ? 'tel:+1' . preg_replace( '/\D/', '', $phone ) : '';
    $shares    = directory_share_links( $id );
    $directions_url = add_query_arg( array(
        'api'         => 1,
        'destination' => $latlng ? $latlng : $address,
    ), 'https://www.google.com/maps/dir/' );
    $edit_url  = directory_user_can_edit_listing( $id ) ? directory_edit_url( $id ) : '';

    $avatar_html = $avatar
        ? '<img src="' . esc_url( $avatar ) . '" alt="' . esc_attr( $name ) . '" width="150" height="150">'
        : '<span class="dir-avatar-empty"><i class="fa-regular fa-user"></i></span>';

    $contact_btn = function ( $extra = '' ) use ( $tel ) {
        if ( $tel ) {
            return '<a class="dir-btn ' . $extra . '" href="' . esc_attr( $tel ) . '">Contact Provider</a>';
        }
        return '<span class="dir-btn is-disabled ' . $extra . '">Contact Provider</span>';
    };
    ?>
    <main id="main" class="site-main directory-single" data-latlng="<?php echo esc_attr( $latlng ); ?>">
        <div class="dir-container">

            <header class="dir-hero">
                <div class="dir-hero__avatar"><?php echo $avatar_html; // phpcs:ignore ?></div>

                <div class="dir-hero__info">
                    <div class="dir-hero__title">
                        <h1><?php echo esc_html( $name ); ?></h1>
                        <span class="dir-verified">Verified Vet Provider</span>
                    </div>
                    <?php if ( $cat ) : ?>
                        <span class="dir-pill"><?php echo esc_html( $cat->name ); ?></span>
                    <?php endif; ?>
                    <div class="dir-rating">
                        <i class="fa-solid fa-star"></i> <strong>4.5</strong>
                        <span class="dir-dot"></span> 83 Reviews
                    </div>
                    <a class="dir-link" href="#">Leave a review</a>
                    <?php if ( $short ) : ?>
                        <p class="dir-hero__short"><?php echo esc_html( $short ); ?></p>
                    <?php endif; ?>
                </div>

                <div class="dir-hero__side">
                    <div class="dir-hero__icons">
                        <?php if ( $edit_url ) : ?>
                            <a class="dir-icon-btn dir-edit" href="<?php echo esc_url( $edit_url ); ?>" title="Edit listing" aria-label="Edit listing"><i class="fa-solid fa-pencil"></i></a>
                        <?php endif; ?>
                        <button type="button" class="dir-icon-btn" data-copy-link title="Copy link" aria-label="Copy link"><i class="fa-solid fa-arrow-up-from-bracket"></i></button>
                        <button type="button" class="dir-icon-btn" title="Report" aria-label="Report"><i class="fa-regular fa-flag"></i></button>
                    </div>
                    <?php echo $contact_btn( 'dir-btn--wide' ); // phpcs:ignore ?>
                    <div class="dir-meta">
                        <i class="fa-regular fa-heart"></i>
                        <span class="dir-distance" hidden><span data-distance></span><span class="dir-dot"></span></span>
                        <?php echo esc_html( $city ); ?>
                    </div>
                </div>
            </header>

            <div class="dir-layout">
                <div class="dir-main">

                    <?php if ( $full ) : ?>
                        <section class="dir-section">
                            <h2 class="dir-label">Service Description</h2>
                            <div class="dir-text"><?php echo wpautop( esc_html( $full ) ); // phpcs:ignore ?></div>
                        </section>
                    <?php endif; ?>

                    <?php if ( $services ) : ?>
                        <section class="dir-included">
                            <h3>What's included:</h3>
                            <ul>
                                <?php foreach ( $services as $line ) : ?>
                                    <li><i class="fa-solid fa-circle-check"></i> <?php echo esc_html( $line ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>

                    <?php if ( $address ) : ?>
                        <section class="dir-location">
                            <h3><i class="fa-solid fa-location-dot"></i> Service Location: <?php echo esc_html( $address ); ?></h3>
                            <div class="dir-map-wrap">
                                <div id="directory-map" class="dir-map" data-latlng="<?php echo esc_attr( $latlng ); ?>"></div>
                                <a class="dir-directions" href="<?php echo esc_url( $directions_url ); ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="fa-solid fa-diamond-turn-right"></i> Get directions
                                </a>
                            </div>
                        </section>
                    <?php endif; ?>

                </div>

                <aside class="dir-sidebar">
                    <div class="dir-card">
                        <div class="dir-card__head">
                            <div class="dir-card__avatar"><?php echo $avatar_html; // phpcs:ignore ?></div>
                            <div>
                                <strong><?php echo esc_html( $name ); ?></strong>
                                <?php if ( $cat ) : ?>
                                    <span class="dir-pill"><?php echo esc_html( $cat->name ); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <ul class="dir-card__list">
                            <li><i class="fa-regular fa-calendar"></i> Member since <?php echo esc_html( get_the_date( 'Y' ) ); ?></li>
                            <li><i class="fa-regular fa-heart"></i> <span class="dir-distance" hidden><span data-distance></span><span class="dir-dot"></span></span><?php echo esc_html( $city ); ?></li>
                        </ul>
                        <?php echo $contact_btn( 'dir-btn--block' ); // phpcs:ignore ?>
                    </div>

                    <div class="dir-share">
                        <h4>Share this classified listing</h4>
                        <div class="dir-share__icons">
                            <?php foreach ( $shares as $key => $s ) : ?>
                                <a href="<?php echo esc_url( $s[2] ); ?>" target="_blank" rel="noopener noreferrer"
                                   title="<?php echo esc_attr( $s[0] ); ?>" aria-label="<?php echo esc_attr( 'Share on ' . $s[0] ); ?>">
                                    <i class="<?php echo esc_attr( $s[1] ); ?>"></i>
                                </a>
                            <?php endforeach; ?>
                            <button type="button" data-copy-link title="Copy link" aria-label="Copy link"><i class="fa-solid fa-link"></i></button>
                        </div>
                    </div>
                </aside>
            </div>

        </div>
    </main>
    <?php
endwhile;

get_footer();
