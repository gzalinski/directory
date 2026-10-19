<?php
/**
 * Directory categories importer (categories.json -> taxonomy directory_cat).
 *
 * JSON format: { "Parent name": [ "Child 1", "Child 2", ... ], ... }
 *
 * Run once as an administrator by visiting:
 *   /wp-admin/?directory_import_categories=1
 *
 * Safe to re-run: existing terms are reused (matched by name + parent),
 * only missing ones are created.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', function () {

    if ( empty( $_GET['directory_import_categories'] ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $taxonomy = 'directory_cat';
    $file     = __DIR__ . '/categories.json';

    $notice = function ( $type, $message ) {
        add_action( 'admin_notices', function () use ( $type, $message ) {
            printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), wp_kses_post( $message ) );
        } );
    };

    if ( ! taxonomy_exists( $taxonomy ) ) {
        $notice( 'error', 'Taxonomy <code>directory_cat</code> is not registered.' );
        return;
    }

    if ( ! is_readable( $file ) ) {
        $notice( 'error', 'categories.json not found.' );
        return;
    }

    $categories = json_decode( file_get_contents( $file ), true );
    if ( ! is_array( $categories ) ) {
        $notice( 'error', 'categories.json is not valid JSON.' );
        return;
    }

    $created = 0;
    $skipped = 0;
    $errors  = 0;

    $get_or_create = function ( $name, $parent_id = 0 ) use ( $taxonomy, &$created, &$skipped, &$errors ) {
        $name = trim( wp_strip_all_tags( $name ) );
        if ( '' === $name ) {
            return 0;
        }

        $existing = term_exists( $name, $taxonomy, $parent_id );
        if ( $existing ) {
            $skipped++;
            return (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
        }

        $result = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent_id ) );
        if ( is_wp_error( $result ) ) {
            $errors++;
            return 0;
        }

        $created++;
        return (int) $result['term_id'];
    };

    foreach ( $categories as $parent_name => $children ) {
        $parent_id = $get_or_create( $parent_name );
        if ( ! $parent_id ) {
            continue;
        }
        foreach ( (array) $children as $child_name ) {
            $get_or_create( $child_name, $parent_id );
        }
    }

    $notice(
        $errors ? 'warning' : 'success',
        sprintf(
            '<strong>Directory categories import:</strong> %d created, %d already existed, %d errors.',
            $created,
            $skipped,
            $errors
        )
    );
} );
