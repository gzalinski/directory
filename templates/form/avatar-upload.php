<?php
/**
 * Avatar picker: file input + 150x150 preview. Cropping happens in the
 * crop modal (form/crop-modal); the uploaded attachment ID goes into #avatar_id.
 *
 * @var int    $avatar_id
 * @var string $avatar_url
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="avatar-upload <?php echo $avatar_id ? 'has-avatar' : ''; ?>">
    <div class="avatar-upload__controls">
        <button type="button" id="avatar-remove" class="button button-secondary">Remove</button>
    </div>

    <div class="avatar-upload__wrap">
        <span class="upload-text">
            <img src="data:image/svg+xml,%3Csvg width='24' height='24' viewBox='0 0 24 24' fill='%23959595' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 15H13V9H16L12 4L8 9H11V15Z' fill='%23959595'/%3E%3Cpath d='M20 18H4V11H2V18C2 19.103 2.897 20 4 20H20C21.103 20 22 19.103 22 18V11H20V18Z' fill='%23959595'/%3E%3C/svg%3E%0A" alt="">
            Upload<br>Photo
        </span>
        <input type="file" id="avatar-input" class="avatar-upload__input" accept=".jpg,.jpeg,.png,.gif">
        <div class="avatar-upload__preview">
            <img class="avatar-upload__image" src="<?php echo esc_url( $avatar_url ); ?>" alt="">
        </div>
    </div>

    <div class="avatar-upload__messages"></div>
</div>
