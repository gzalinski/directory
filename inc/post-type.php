<?php
/**
 * Directory CPT + Taxonomies
 */
defined( 'ABSPATH' ) || exit;


add_action( 'init', function () {

    register_post_type( 'directory', array(
        'labels' => array(
            'name'               => 'Directory',
            'singular_name'      => 'Directory Listing',
            'add_new_item'       => 'Add New Listing',
            'edit_item'          => 'Edit Listing',
            'all_items'          => 'All Listings',
            'search_items'       => 'Search Listings',
            'not_found'          => 'No listings found',
        ),
        'public'             => true,
        'show_in_rest'       => true,
        'menu_icon'          => 'dashicons-location-alt',
        'supports'           => array( 'title', 'thumbnail', 'author' ),
        'has_archive'        => true,
        'rewrite'            => array( 'slug' => 'directory' ),
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
    ) );

    register_taxonomy( 'directory_cat', 'directory', array(
        'labels' => array(
            'name'          => 'Directory Categories',
            'singular_name' => 'Directory Category',
        ),
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'public'            => true,
        'rewrite'           => array( 'slug' => 'directory-category' ),
    ) );

    register_taxonomy( 'directory_tag', 'directory', array(
        'labels' => array(
            'name'          => 'Directory Services',
            'singular_name' => 'Directory Service',
        ),
        'hierarchical'      => false,
        'show_in_rest'      => true,
        'public'            => true,
        'rewrite'           => array( 'slug' => 'directory-service' ),
    ) );

} );