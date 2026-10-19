import { $ } from './form.js';

// Services list repeater: add / remove / move up / move down.
const ROW_HTML =
  '<input type="text" class="df-repeater-input" placeholder="e.g. Free consultation">' +
  '<button type="button" class="df-repeater-up" title="Move up" aria-label="Move up">&uarr;</button>' +
  '<button type="button" class="df-repeater-down" title="Move down" aria-label="Move down">&darr;</button>' +
  '<button type="button" class="df-repeater-remove" title="Remove" aria-label="Remove">&times;</button>';

export function initServices() {
  const repeater = $('#services-repeater');

  function refresh() {
    const rows = repeater.querySelectorAll('.df-repeater-row');
    rows.forEach((row, i) => {
      row.querySelector('.df-repeater-up').disabled = i === 0;
      row.querySelector('.df-repeater-down').disabled = i === rows.length - 1;
    });
  }

  function addRow(value = '') {
    const row = document.createElement('div');
    row.className = 'df-repeater-row';
    row.innerHTML = ROW_HTML;
    row.firstChild.value = value;
    repeater.appendChild(row);
    refresh();
    return row;
  }

  $('#services-add-row').addEventListener('click', () => addRow().firstChild.focus());

  repeater.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    const row = btn.closest('.df-repeater-row');

    if (btn.classList.contains('df-repeater-up') && row.previousElementSibling) {
      repeater.insertBefore(row, row.previousElementSibling);
    } else if (btn.classList.contains('df-repeater-down') && row.nextElementSibling) {
      repeater.insertBefore(row.nextElementSibling, row);
    } else if (btn.classList.contains('df-repeater-remove')) {
      const rows = repeater.querySelectorAll('.df-repeater-row');
      if (rows.length > 1) row.remove();
      else row.querySelector('input').value = '';
    }
    refresh();
  });

  refresh();
}

// Current values in DOM order, empty lines dropped.
export function getServices() {
  return [...document.querySelectorAll('#services-repeater .df-repeater-input')]
    .map((i) => i.value.trim())
    .filter(Boolean);
}
