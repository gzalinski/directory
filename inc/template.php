<?php
/**
 * Template loader: all Directory markup lives in /templates.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Render a template from /templates and return its HTML.
 *
 * Each key of $args becomes a variable inside the template, e.g.
 * directory_get_template( 'form/notice', array( 'message' => 'Hi' ) ) gives $message.
 *
 * @param string $slug Path inside /templates without ".php" (e.g. 'form/edit-form').
 * @param array  $args Variables passed to the template.
 * @return string
 */
function directory_get_template( $slug, $args = array() ) {
    $file = TCK_DIRECTORY_PATH . 'templates/' . $slug . '.php';
    if ( ! is_readable( $file ) ) {
        _doing_it_wrong( __FUNCTION__, esc_html( "Template {$slug} not found." ), '' );
        return '';
    }
    ob_start();
    ( static function () use ( $file, $args ) {
        extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
        include $file;
    } )();
    return ob_get_clean();
}

/** Echo version of directory_get_template(). */
function directory_template( $slug, $args = array() ) {
    echo directory_get_template( $slug, $args ); // phpcs:ignore -- templates escape their own output.
}
