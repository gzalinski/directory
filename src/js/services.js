import { $ } from './form.js';

// Services list repeater: add / remove / move up / move down.
// New rows are cloned from <template id="services-row-template">
// (templates/form/repeater-row.php), so the markup lives in PHP only.
const rowTemplate = () => document.getElementById('services-row-template');

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
    const row = rowTemplate().content.firstElementChild.cloneNode(true);
    row.querySelector('.df-repeater-input').value = value;
    repeater.appendChild(row);
    refresh();
    return row;
  }

  $('#services-add-row').addEventListener('click', () => addRow().querySelector('.df-repeater-input').focus());

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
