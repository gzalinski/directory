<?php
/**
 * Directory archive (CPT archive + directory_cat / directory_tag terms).
 *
 * Two views share one FacetWP listing (like facetwp.com/demo/state-parks):
 *  - List view:  full-width cards + pager, a map preview with "View Map".
 *  - Split view: toolbar + cards + map fit one screen; cards + pager scroll
 *                inside the left column, the Map facet fills the right one.
 *                The same toolbar button reads "Close Map". Map filtering
 *                (viewport = filter) is on while the map is open and off
 *                after closing (see src/archive.js).
 *
 * Rules from the FacetWP docs: facets stay OUTSIDE .facetwp-template, and
 * the Map facet must be on the page in both views (it is only hidden).
 */
defined( 'ABSPATH' ) || exit;

get_header();

$facets = directory_facet_names();
$term   = is_tax() ? get_queried_object() : null;
$title  = $term ? $term->name : 'Provider Directory';
?>
<main id="main" class="site-main directory-archive <?php echo directory_map_filtering_enabled() ? 'has-map-filtering' : 'no-map-filtering'; ?>"
      data-map-filtering="<?php echo directory_map_filtering_enabled() ? '1' : '0'; ?>"
      data-map-facet="<?php echo esc_attr( $facets['map'] ); ?>"
      data-proximity-facet="<?php echo esc_attr( $facets['proximity'] ); ?>"
      data-map-labels="<?php echo esc_attr( wp_json_encode( directory_map_button_labels() ) ); ?>">
    <div class="dir-container">

        <h1 class="dir-archive__title"><?php echo esc_html( $title ); ?></h1>

        <?php if ( ! function_exists( 'FWP' ) && current_user_can( 'manage_options' ) ) : ?>
            <p class="dir-admin-note">FacetWP is not active: filters and map are disabled.</p>
        <?php endif; ?>

        <section class="dir-search" aria-label="Search providers">
            <div class="dir-search__bar">
                <div class="dir-search__field dir-search__field--text">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <?php echo directory_facet( 'search' ); // phpcs:ignore ?>
                </div>
                <div class="dir-search__field dir-search__field--location">
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <?php echo directory_facet( 'proximity' ); // phpcs:ignore ?>
                </div>
                <button type="button" class="dir-btn dir-search__submit" data-dir-search>Search</button>
            </div>

            <div class="dir-search__filters">
                <div class="dir-filter dir-filter--category" title="Category">
                    <?php echo directory_facet( 'category' ); // phpcs:ignore ?>
                </div>
                <div class="dir-filter dir-filter--services" title="Speciality">
                    <?php echo directory_facet( 'services' ); // phpcs:ignore ?>
                </div>
                <!-- Mirrors the Proximity facet's own radius dropdown (hidden in the search bar). -->
                <label class="dir-filter dir-filter--distance" data-dir-distance-wrap hidden>
                    <span class="screen-reader-text">Distance</span>
                    <select data-dir-distance aria-label="Distance"></select>
                </label>
                <button type="button" class="dir-reset" data-dir-reset hidden>Clear filters</button>
            </div>
        </section>

        <div class="dir-toolbar">
            <div class="dir-toolbar__sort">
                <span class="dir-toolbar__label">Sort by:</span>
                <?php echo directory_facet( 'sort' ); // phpcs:ignore ?>
            </div>
            <!-- One toggle: "View Map" in list view, "Close Map" in split view. -->
            <button type="button" class="dir-map-preview" data-dir-toggle-map aria-controls="dir-mapview" aria-expanded="false">
                <span class="dir-map-preview__btn">
                    <span class="dir-map-preview__open"><i class="fa-regular fa-map" aria-hidden="true"></i> View Map</span>
                    <span class="dir-map-preview__close">Close Map <i class="fa-solid fa-xmark" aria-hidden="true"></i></span>
                </span>
            </button>
        </div>

        <div class="dir-stage">
            <div class="dir-results" data-dir-results>
                <div class="facetwp-template dir-list">
                    <?php if ( have_posts() ) : ?>
                        <?php while ( have_posts() ) : the_post(); ?>
                            <?php directory_template( 'parts/directory-card' ); ?>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <p class="dir-empty">No providers found. Try a wider distance or fewer filters.</p>
                    <?php endif; ?>
                </div>
                <div class="dir-pager"><?php echo directory_facet( 'pager' ); // phpcs:ignore ?></div>
            </div>

            <!-- Split view: map on the right. Hidden (but rendered) in list view. -->
            <div class="dir-mapview" id="dir-mapview" aria-hidden="true">
                <?php echo directory_facet( 'map' ); // phpcs:ignore ?>
            </div>
        </div>

    </div>
</main>
<?php
get_footer(); // must call wp_footer(): FacetWP loads its JS/CSS there.
