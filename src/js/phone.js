import { $ } from './form.js';

// US phone mask: (555) 123-4567
function format(value) {
  let d = value.replace(/\D/g, '');
  if (d.length === 11 && d[0] === '1') d = d.slice(1);
  d = d.slice(0, 10);
  if (d.length < 4) return d.length ? '(' + d : '';
  if (d.length < 7) return '(' + d.slice(0, 3) + ') ' + d.slice(3);
  return '(' + d.slice(0, 3) + ') ' + d.slice(3, 6) + '-' + d.slice(6);
}

export function initPhone() {
  const input = $('#phone');
  input.value = format(input.value);
  input.addEventListener('input', () => {
    input.value = format(input.value);
  });
  input.addEventListener('paste', (e) => {
    e.preventDefault();
    input.value = format((e.clipboardData || window.clipboardData).getData('text'));
  });
}

export const isValidPhone = (v) => v === '' || /^\(\d{3}\) \d{3}-\d{4}$/.test(v);
