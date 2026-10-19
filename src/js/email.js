import { $ } from './form.js';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
export const isValidEmail = (v) => v === '' || EMAIL_RE.test(v);

// Flag the field as invalid as soon as the user leaves it.
export function initEmail() {
  const input = $('#email');
  input.addEventListener('blur', () => {
    input.classList.toggle('is-invalid', !isValidEmail(input.value.trim()));
  });
  input.addEventListener('input', () => {
    if (isValidEmail(input.value.trim())) input.classList.remove('is-invalid');
  });
}
