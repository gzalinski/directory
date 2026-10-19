// Avatar: pick -> crop (Cropper.js in fancybox) -> upload exactly 150x150.
// Same flow as the eAvatar module.
const AVATAR_SIZE = 150;

export function initAvatar() {
  const $ = window.jQuery;
  const av = '.avatar-upload';
  const avImage = '.avatar-upload__image';
  const avPreview = '.avatar-upload__preview';
  const avInput = '#avatar-input';
  const avMessages = '.avatar-upload__messages';
  const avButtonRemove = '#avatar-remove';
  const modal = '#modal-crop';
  const imageTemp = document.getElementById('sample_image');
  let cropper = null;

  function showError(msg) {
    $(avMessages).html('').append('<div class="upload-error"></div>').find('div').text(msg);
  }

  function readURL(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (!/^image\/(jpeg|png|gif)$/.test(file.type)) {
      showError('Please choose a JPG, PNG or GIF image.');
      return;
    }
    $(avMessages).html('');
    const reader = new FileReader();
    reader.onload = (e) => {
      imageTemp.src = e.target.result;
      $('[data-src="' + modal + '"]').trigger('click'); // open fancybox
    };
    reader.readAsDataURL(file);
  }

  function removeUpload() {
    $(av).removeClass('has-avatar uploaded');
    $(avPreview).hide();
    $(avButtonRemove).hide();
    $(avImage).removeAttr('src');
    $('#avatar_id').val('');
  }

  function cropperCreate() {
    cropper = new Cropper(imageTemp, { aspectRatio: 1, viewMode: 2 });
  }

  function cropperDestroy() {
    if (cropper) cropper.destroy();
    cropper = null;
  }

  function cropperCrop() {
    const canvas = cropper.getCroppedCanvas({
      width: AVATAR_SIZE,
      height: AVATAR_SIZE,
      imageSmoothingQuality: 'high',
    });
    if (!canvas) return;

    $.ajax({
      url: DirectoryForm.ajaxUrl,
      type: 'POST',
      data: {
        action: 'directory_upload_avatar',
        nonce: DirectoryForm.nonce,
        image: canvas.toDataURL('image/png'),
      },
      beforeSend: () => $(modal).addClass('loading'),
      success: (res) => {
        $(modal).removeClass('loading');
        if (!res.success) {
          alert((res.data && res.data.message) || 'Upload failed.');
          return;
        }
        $(av).addClass('uploaded has-avatar');
        $(avPreview).show();
        $(avButtonRemove).show();
        $(avImage).attr('src', res.data.preview_url);
        $('#avatar_id').val(res.data.attachment_id);
        $.fancybox.close();
      },
      error: () => {
        $(modal).removeClass('loading');
        alert('Upload failed.');
      },
    });
  }

  $('[data-src="' + modal + '"]').fancybox({
    touch: false,
    smallBtn: false,
    beforeShow: () => {
      cropperCreate();
      $('html').css('overflow', 'hidden');
    },
    beforeClose: () => cropperDestroy(),
    afterClose: () => $('html').css('overflow', ''),
  });

  $('#crop').on('click', cropperCrop);
  $(avInput).on('click', function () { $(this).val(''); });
  $(avInput).on('change', function () { readURL(this); });
  $(avButtonRemove).on('click', removeUpload);
}
