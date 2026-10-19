<?php
/**
 * One row of the "Services List" repeater. Also printed once inside a
 * <template> so src/js/services.js can clone new rows from it.
 *
 * @var string $value
 */
defined( 'ABSPATH' ) || exit;

$value = isset( $value ) ? $value : '';
?>
<div class="df-repeater-row">
    <input type="text" class="df-repeater-input" value="<?php echo esc_attr( $value ); ?>" placeholder="e.g. Free consultation">
    <button type="button" class="df-repeater-up" title="Move up" aria-label="Move up">&uarr;</button>
    <button type="button" class="df-repeater-down" title="Move down" aria-label="Move down">&darr;</button>
    <button type="button" class="df-repeater-remove" title="Remove" aria-label="Remove">&times;</button>
</div>
