<?php
/**
 * Avatar crop popup (fancybox + Cropper.js), opened by src/js/avatar.js.
 */
defined( 'ABSPATH' ) || exit;
?>
<a data-fancybox data-src="#modal-crop" href="javascript:;" style="display:none">Open Cropper Popup</a>
<div class="modal modal-cropper" id="modal-crop" tabindex="-1" role="dialog" aria-hidden="true" style="display:none;">
    <div class="modal-header">
        <h3 class="modal-title">Crop &amp; Upload Image</h3>
    </div>
    <div class="modal-body">
        <div class="img-container">
            <div class="img-crop-wrapper">
                <img src="" id="sample_image" alt="">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" id="crop" class="button button-primary">
            <span class="crop-text">Upload</span>
            <i class="avatar-loader"></i>
        </button>
        <button type="button" data-fancybox-close="" title="Cancel" class="button button-secondary">Cancel</button>
    </div>
</div>
