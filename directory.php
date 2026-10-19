<?php
defined( 'ABSPATH' ) || exit;

define( 'TCK_API_KEY_GOOGLE_MAPS', 'AIzaSyBGlpPviBW9MQHHAvV0WnBRHMwy3H3BwhI' ); //todo from acf

// Map filtering in split view: true = moving / zooming the map filters the
// cards ("Search this area"); false = the map only shows markers.
define( 'TCK_DIRECTORY_MAP_FILTERING', true );

define( 'TCK_DIRECTORY_PATH', get_stylesheet_directory() . '/directory/' );
define( 'TCK_DIRECTORY_URL', get_stylesheet_directory_uri() . '/directory/' );

require_once TCK_DIRECTORY_PATH . 'inc/template.php';
require_once TCK_DIRECTORY_PATH . 'inc/post-type.php';
require_once TCK_DIRECTORY_PATH . 'inc/meta.php';
require_once TCK_DIRECTORY_PATH . 'inc/shortcode.php';
require_once TCK_DIRECTORY_PATH . 'inc/single.php';
require_once TCK_DIRECTORY_PATH . 'inc/archive.php';
require_once TCK_DIRECTORY_PATH . 'inc/facets.php';
require_once TCK_DIRECTORY_PATH . 'inc/seo.php';
require_once TCK_DIRECTORY_PATH . '_hiden/import-categories.php';
require_once TCK_DIRECTORY_PATH . '_hiden/import-demo.php';
