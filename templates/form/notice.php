<?php
/**
 * Plain notice shown instead of the form (not logged in / no permission).
 *
 * @var string $message
 */
defined( 'ABSPATH' ) || exit;
?>
<p class="directory-notice"><?php echo esc_html( $message ); ?></p>
