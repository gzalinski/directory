import { form, $, showStatus } from './form.js';
import { getServices } from './services.js';
import { isValidPhone } from './phone.js';
import { isValidEmail } from './email.js';

// AJAX save (create or update) of the listing.
export function initSubmit() {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!$('#provider_name').value.trim()) {
      showStatus('Provider name is required.', true);
      return;
    }
    if (!isValidPhone($('#phone').value.trim())) {
      showStatus('Please enter a valid US phone number, e.g. (555) 123-4567.', true);
      return;
    }
    if (!isValidEmail($('#email').value.trim())) {
      $('#email').classList.add('is-invalid');
      showStatus('Please enter a valid email address.', true);
      return;
    }
    $('#services_list').value = JSON.stringify(getServices());

    const fd = new FormData(form);
    fd.append('action', 'directory_save_listing');
    fd.append('nonce', DirectoryForm.nonce);

    const btn = $('.df-btn-primary');
    btn.disabled = true;
    try {
      const res = await (await fetch(DirectoryForm.ajaxUrl, { method: 'POST', body: fd })).json();
      if (!res.success) throw new Error(res.data?.message || 'Save failed.');
      form.elements.post_id.value = res.data.post_id;
      history.replaceState(null, '', res.data.edit_url);
      showStatus('Listing saved.', false, { href: res.data.permalink, text: 'View your listing' });
    } catch (err) {
      showStatus(err.message, true);
    } finally {
      btn.disabled = false;
    }
  });
}
